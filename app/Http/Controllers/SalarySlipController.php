<?php

namespace App\Http\Controllers;

use App\Models\AllowanceType;
use App\Models\ChartOfAccount;
use App\Models\CompanySetting;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\FinancialYear;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\SalarySlip;
use App\Models\SalarySlipDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SalarySlipController extends Controller
{
    /**
     * Display a listing of the salary slips.
     */
    public function index(Request $request)
    {
        $query = SalarySlip::with(['employee.user', 'employee.department', 'employee.designation']);

        // Filter by month and year
        if ($request->has('month_year') && $request->month_year) {
            $monthYear = Carbon::parse($request->month_year . '-01');
            $query->whereYear('month_year', $monthYear->year)
                  ->whereMonth('month_year', $monthYear->month);
        }

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Filter by employee
        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        // Search by reference number or employee name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('employee.user', function ($query) use ($search) {
                      $query->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $salarySlips = $query->orderBy('month_year', 'desc')
            ->orderBy('reference_number', 'asc')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $employees = Employee::with('user')->where('is_active', true)->get();
        $companySetting = CompanySetting::first();

        // Get unique month-year combinations for the filter
        $monthYearOptions = SalarySlip::selectRaw('DISTINCT DATE_FORMAT(month_year, "%Y-%m") as month_year_value')
            ->orderBy('month_year', 'desc')
            ->limit(24) // Show last 2 years
            ->get()
            ->map(function ($item) {
                $date = Carbon::parse($item->month_year_value . '-01');
                return [
                    'value' => $item->month_year_value,
                    'label' => $date->format('F Y'),
                ];
            });

        // Calculate totals for current view
        $totals = [
            'basic_salary' => $salarySlips->sum('basic_salary'),
            'total_allowances' => $salarySlips->sum('total_allowances'),
            'total_deductions' => $salarySlips->sum('total_deductions'),
            'net_salary' => $salarySlips->sum('net_salary'),
        ];

        return Inertia::render('SalarySlips/Index', [
            'salarySlips' => $salarySlips,
            'departments' => $departments,
            'employees' => $employees,
            'monthYearOptions' => $monthYearOptions,
            'totals' => $totals,
            'filters' => $request->only(['month_year', 'department_id', 'employee_id', 'payment_status', 'search']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for generating new salary slips.
     */
    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $employees = Employee::with(['user', 'department', 'allowances.allowanceType', 'deductions.deductionType'])
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        // Get financial years for accounting
        $financialYears = FinancialYear::where('is_active', true)->get();

        // Generate default month-year (current month)
        $defaultMonthYear = Carbon::now()->format('Y-m');

        return Inertia::render('SalarySlips/Create', [
            'departments' => $departments,
            'employees' => $employees,
            'financialYears' => $financialYears,
            'defaultMonthYear' => $defaultMonthYear,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate salary slips based on filters.
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m',
            'department_id' => 'nullable|exists:departments,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'financial_year_id' => 'required|exists:financial_years,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $monthYear = Carbon::parse($request->month_year . '-01');
        $generatedCount = 0;
        $existingCount = 0;

        try {
            DB::beginTransaction();

            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';
            $salaryPrefix = $companySetting ? $companySetting->invoice_prefix : 'SAL-';

            // Get salary expense and liability accounts
            $salaryExpenseAccount = ChartOfAccount::whereHas('category', function ($query) {
                    $query->where('type', 'Expense');
                })
                ->where(function ($query) {
                    $query->where('name', 'like', '%Salary%')
                        ->orWhere('name', 'like', '%Wages%')
                        ->orWhere('name', 'like', '%Payroll%');
                })
                ->where('is_active', true)
                ->first();

            $salaryPayableAccount = ChartOfAccount::whereHas('category', function ($query) {
                    $query->where('type', 'Liability');
                })
                ->where(function ($query) {
                    $query->where('name', 'like', '%Salary Payable%')
                        ->orWhere('name', 'like', '%Wages Payable%')
                        ->orWhere('name', 'like', '%Accrued Payroll%');
                })
                ->where('is_active', true)
                ->first();

            if (!$salaryExpenseAccount || !$salaryPayableAccount) {
                throw new \Exception('Salary expense or payable account not found. Please configure Chart of Accounts first.');
            }

            foreach ($request->employee_ids as $employeeId) {
                // Check if salary slip already exists for this employee and month
                $existingSalarySlip = SalarySlip::where('employee_id', $employeeId)
                    ->whereYear('month_year', $monthYear->year)
                    ->whereMonth('month_year', $monthYear->month)
                    ->first();

                if ($existingSalarySlip) {
                    $existingCount++;
                    continue;
                }

                $employee = Employee::with(['allowances.allowanceType', 'deductions.deductionType'])
                    ->findOrFail($employeeId);

                // Generate reference number
                $lastSlip = SalarySlip::orderBy('id', 'desc')->first();
                $slipNumber = 1;

                if ($lastSlip) {
                    preg_match('/(\d+)$/', $lastSlip->reference_number, $matches);
                    if (isset($matches[1])) {
                        $slipNumber = (int) $matches[1] + 1;
                    }
                }

                $referenceNumber = $salaryPrefix . $monthYear->format('Ym') . '-' . str_pad($slipNumber, 4, '0', STR_PAD_LEFT);

                // Calculate totals
                $totalAllowances = $employee->allowances->sum('amount');
                $totalDeductions = $employee->deductions->sum('amount');
                $netSalary = $employee->basic_salary + $totalAllowances - $totalDeductions;

                // Create journal entry for accounting
                $journalEntry = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'SAL-' . $monthYear->format('Ym') . '-' . str_pad($slipNumber, 4, '0', STR_PAD_LEFT),
                    'financial_year_id' => $request->financial_year_id,
                    'entry_date' => $monthYear->endOfMonth()->format('Y-m-d'),
                    'narration' => 'Salary for ' . $employee->user->name . ' for ' . $monthYear->format('F Y'),
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Debit salary expense account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $salaryExpenseAccount->id,
                    'type' => 'debit',
                    'amount' => $netSalary,
                    'description' => 'Salary expense for ' . $employee->user->name . ' for ' . $monthYear->format('F Y'),
                ]);

                // Credit salary payable account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $salaryPayableAccount->id,
                    'type' => 'credit',
                    'amount' => $netSalary,
                    'description' => 'Salary payable for ' . $employee->user->name . ' for ' . $monthYear->format('F Y'),
                ]);

                // Create salary slip
                $salarySlip = SalarySlip::create([
                    'reference_number' => $referenceNumber,
                    'employee_id' => $employee->id,
                    'month_year' => $monthYear->format('Y-m-d'),
                    'basic_salary' => $employee->basic_salary,
                    'total_allowances' => $totalAllowances,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'payment_status' => 'unpaid',
                    'journal_entry_id' => $journalEntry->id,
                    'created_by' => Auth::id(),
                ]);

                // Create salary slip details for allowances
                foreach ($employee->allowances as $allowance) {
                    SalarySlipDetail::create([
                        'salary_slip_id' => $salarySlip->id,
                        'type' => 'allowance',
                        'name' => $allowance->allowanceType->name,
                        'amount' => $allowance->amount,
                    ]);
                }

                // Create salary slip details for deductions
                foreach ($employee->deductions as $deduction) {
                    SalarySlipDetail::create([
                        'salary_slip_id' => $salarySlip->id,
                        'type' => 'deduction',
                        'name' => $deduction->deductionType->name,
                        'amount' => $deduction->amount,
                    ]);
                }

                $generatedCount++;
            }

            DB::commit();

            if ($existingCount > 0) {
                $message = "Generated {$generatedCount} salary slips. {$existingCount} slips already existed and were skipped.";
            } else {
                $message = "Successfully generated {$generatedCount} salary slips.";
            }

            return redirect()->route('salary-slips.index', ['month_year' => $request->month_year])
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified salary slip.
     */
    public function show(SalarySlip $salarySlip)
    {
        $salarySlip->load([
            'employee.user',
            'employee.department',
            'employee.designation',
            'details',
            'journalEntry.items.account',
            'createdBy',
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('SalarySlips/Show', [
            'salarySlip' => $salarySlip,
            'allowances' => $salarySlip->details->where('type', 'allowance'),
            'deductions' => $salarySlip->details->where('type', 'deduction'),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Process payment for the salary slip.
     */
    public function processPayment(Request $request, SalarySlip $salarySlip)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if ($salarySlip->payment_status === 'paid') {
            return back()->with('error', 'This salary slip has already been paid.');
        }

        try {
            DB::beginTransaction();

            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            $financialYear = FinancialYear::where('is_active', true)->first();
            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            // Get salary payable account
            $salaryPayableAccount = ChartOfAccount::whereHas('category', function ($query) {
                    $query->where('type', 'Liability');
                })
                ->where(function ($query) {
                    $query->where('name', 'like', '%Salary Payable%')
                        ->orWhere('name', 'like', '%Wages Payable%')
                        ->orWhere('name', 'like', '%Accrued Payroll%');
                })
                ->where('is_active', true)
                ->first();

            if (!$salaryPayableAccount) {
                throw new \Exception('Salary payable account not found.');
            }

            // Create payment journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'PAY-SAL-' . $salarySlip->id,
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->payment_date,
                'narration' => 'Salary payment for ' . $salarySlip->employee->user->name . ' for ' . $salarySlip->month_year_formatted,
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Debit salary payable account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $salaryPayableAccount->id,
                'type' => 'debit',
                'amount' => $salarySlip->net_salary,
                'description' => 'Salary payment for ' . $salarySlip->employee->user->name . ' for ' . $salarySlip->month_year_formatted,
            ]);

            // Credit payment account (cash or bank)
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => $salarySlip->net_salary,
                'description' => 'Salary payment for ' . $salarySlip->employee->user->name . ' for ' . $salarySlip->month_year_formatted,
            ]);

            // Update salary slip
            $salarySlip->update([
                'payment_status' => 'paid',
                'payment_date' => $request->payment_date,
                'remarks' => $request->remarks,
            ]);

            DB::commit();

            return redirect()->route('salary-slips.show', $salarySlip->id)
                ->with('success', 'Salary payment processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Cancel a salary slip.
     */
    public function cancel(SalarySlip $salarySlip)
    {
        if ($salarySlip->payment_status === 'paid') {
            return back()->with('error', 'Cannot cancel a paid salary slip.');
        }

        try {
            DB::beginTransaction();

            // Create reversal journal entry
            $journalEntry = $salarySlip->journalEntry;

            if ($journalEntry) {
                $companySetting = CompanySetting::first();
                $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

                $financialYear = FinancialYear::where('is_active', true)->first();

                $reversalJournal = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'REV-SAL-' . $salarySlip->id,
                    'financial_year_id' => $financialYear->id,
                    'entry_date' => now(),
                    'narration' => 'Reversal of ' . $journalEntry->narration,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Create reversal journal items (debit becomes credit and vice versa)
                foreach ($journalEntry->items as $item) {
                    JournalItem::create([
                        'journal_entry_id' => $reversalJournal->id,
                        'account_id' => $item->account_id,
                        'type' => $item->type === 'debit' ? 'credit' : 'debit',
                        'amount' => $item->amount,
                        'description' => 'Reversal: ' . $item->description,
                    ]);
                }
            }

            // Delete salary slip details
            SalarySlipDetail::where('salary_slip_id', $salarySlip->id)->delete();

            // Delete salary slip
            $salarySlip->delete();

            DB::commit();

            return redirect()->route('salary-slips.index')
                ->with('success', 'Salary slip cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Print the salary slip.
     */
    public function print(SalarySlip $salarySlip)
    {
        $salarySlip->load([
            'employee.user',
            'employee.department',
            'employee.designation',
            'details',
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('SalarySlips/Print', [
            'salarySlip' => $salarySlip,
            'allowances' => $salarySlip->details->where('type', 'allowance'),
            'deductions' => $salarySlip->details->where('type', 'deduction'),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate payslip PDF.
     */
    public function generatePdf(SalarySlip $salarySlip)
    {
        $salarySlip->load([
            'employee.user',
            'employee.department',
            'employee.designation',
            'details',
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('SalarySlips/ExportPdf', [
            'salarySlip' => $salarySlip,
            'allowances' => $salarySlip->details->where('type', 'allowance'),
            'deductions' => $salarySlip->details->where('type', 'deduction'),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Export salary slips to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = SalarySlip::with(['employee.user', 'employee.department', 'employee.designation', 'details']);

        // Apply filters
        if ($request->has('month_year') && $request->month_year) {
            $monthYear = Carbon::parse($request->month_year . '-01');
            $query->whereYear('month_year', $monthYear->year)
                  ->whereMonth('month_year', $monthYear->month);
        }

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        $salarySlips = $query->orderBy('month_year', 'desc')
            ->orderBy('reference_number', 'asc')
            ->get();

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $companySetting = CompanySetting::first();

        // Calculate totals
        $totals = [
            'basic_salary' => $salarySlips->sum('basic_salary'),
            'total_allowances' => $salarySlips->sum('total_allowances'),
            'total_deductions' => $salarySlips->sum('total_deductions'),
            'net_salary' => $salarySlips->sum('net_salary'),
        ];

        return Inertia::render('SalarySlips/ExportList', [
            'salarySlips' => $salarySlips,
            'departments' => $departments,
            'totals' => $totals,
            'filters' => $request->only(['month_year', 'department_id', 'employee_id', 'payment_status']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate monthly payroll report.
     */
    public function monthlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $monthYear = Carbon::parse($request->month_year . '-01');

        $query = SalarySlip::with(['employee.user', 'employee.department', 'employee.designation'])
            ->whereYear('month_year', $monthYear->year)
            ->whereMonth('month_year', $monthYear->month);

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $salarySlips = $query->orderBy('reference_number', 'asc')->get();

        // Group by department
        $departmentData = [];
        foreach ($salarySlips as $slip) {
            $departmentId = $slip->employee->department_id;
            $departmentName = $slip->employee->department->name;

            if (!isset($departmentData[$departmentId])) {
                $departmentData[$departmentId] = [
                    'name' => $departmentName,
                    'basic_salary' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'employee_count' => 0,
                    'slips' => [],
                ];
            }

            $departmentData[$departmentId]['basic_salary'] += $slip->basic_salary;
            $departmentData[$departmentId]['total_allowances'] += $slip->total_allowances;
            $departmentData[$departmentId]['total_deductions'] += $slip->total_deductions;
            $departmentData[$departmentId]['net_salary'] += $slip->net_salary;
            $departmentData[$departmentId]['employee_count']++;
            $departmentData[$departmentId]['slips'][] = [
                'id' => $slip->id,
                'employee_name' => $slip->employee->user->name,
                'designation' => $slip->employee->designation->name,
                'basic_salary' => $slip->basic_salary,
                'total_allowances' => $slip->total_allowances,
                'total_deductions' => $slip->total_deductions,
                'net_salary' => $slip->net_salary,
                'payment_status' => $slip->payment_status,
            ];
        }

        // Calculate totals
        $totals = [
            'basic_salary' => $salarySlips->sum('basic_salary'),
            'total_allowances' => $salarySlips->sum('total_allowances'),
            'total_deductions' => $salarySlips->sum('total_deductions'),
            'net_salary' => $salarySlips->sum('net_salary'),
            'employee_count' => $salarySlips->count(),
        ];

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $companySetting = CompanySetting::first();

        return Inertia::render('SalarySlips/MonthlyReport', [
            'departmentData' => array_values($departmentData),
            'departments' => $departments,
            'totals' => $totals,
            'monthYear' => $monthYear->format('F Y'),
            'filters' => $request->only(['month_year', 'department_id']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate yearly payroll report.
     */
    public function yearlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|digits:4|integer|min:2000|max:' . (date('Y') + 1),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $year = $request->year;

        $query = SalarySlip::with(['employee.user', 'employee.department'])
            ->whereYear('month_year', $year);

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $salarySlips = $query->orderBy('month_year', 'asc')->get();

        // Group by month
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::createFromDate($year, $month, 1);
            $monthlyData[$month] = [
                'month' => $date->format('F'),
                'basic_salary' => 0,
                'total_allowances' => 0,
                'total_deductions' => 0,
                'net_salary' => 0,
                'employee_count' => 0,
            ];
        }

        foreach ($salarySlips as $slip) {
            $month = (int) $slip->month_year->format('n');
            $monthlyData[$month]['basic_salary'] += $slip->basic_salary;
            $monthlyData[$month]['total_allowances'] += $slip->total_allowances;
            $monthlyData[$month]['total_deductions'] += $slip->total_deductions;
            $monthlyData[$month]['net_salary'] += $slip->net_salary;
            $monthlyData[$month]['employee_count']++;
        }

        // Calculate department-wise totals for the year
        $departmentData = [];
        foreach ($salarySlips as $slip) {
            $departmentId = $slip->employee->department_id;
            $departmentName = $slip->employee->department->name;

            if (!isset($departmentData[$departmentId])) {
                $departmentData[$departmentId] = [
                    'name' => $departmentName,
                    'basic_salary' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'employee_count' => 0,
                ];
            }

            $departmentData[$departmentId]['basic_salary'] += $slip->basic_salary;
            $departmentData[$departmentId]['total_allowances'] += $slip->total_allowances;
            $departmentData[$departmentId]['total_deductions'] += $slip->total_deductions;
            $departmentData[$departmentId]['net_salary'] += $slip->net_salary;

            // Count unique employees
            if (!isset($departmentData[$departmentId]['employees'][$slip->employee_id])) {
                $departmentData[$departmentId]['employees'][$slip->employee_id] = true;
                $departmentData[$departmentId]['employee_count']++;
            }
        }

        // Remove the employees tracking array
        foreach ($departmentData as &$dept) {
            unset($dept['employees']);
        }

        // Calculate yearly totals
        $yearlyTotals = [
            'basic_salary' => $salarySlips->sum('basic_salary'),
            'total_allowances' => $salarySlips->sum('total_allowances'),
            'total_deductions' => $salarySlips->sum('total_deductions'),
            'net_salary' => $salarySlips->sum('net_salary'),
            'employee_count' => $salarySlips->pluck('employee_id')->unique()->count(),
        ];

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $companySetting = CompanySetting::first();

        // Get years for filter (last 5 years)
        $currentYear = date('Y');
        $yearOptions = [];
        for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
            $yearOptions[] = [
                'value' => $y,
                'label' => $y,
            ];
        }

        return Inertia::render('SalarySlips/YearlyReport', [
            'monthlyData' => array_values($monthlyData),
            'departmentData' => array_values($departmentData),
            'departments' => $departments,
            'yearlyTotals' => $yearlyTotals,
            'year' => $year,
            'yearOptions' => $yearOptions,
            'filters' => $request->only(['year', 'department_id']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show payment form for a salary slip.
     */
    public function showPaymentForm(SalarySlip $salarySlip)
    {
        if ($salarySlip->payment_status === 'paid') {
            return back()->with('error', 'This salary slip has already been paid.');
        }

        $salarySlip->load(['employee.user', 'employee.department', 'employee.designation']);

        // Get cash and bank accounts
        $paymentAccounts = ChartOfAccount::whereHas('category', function ($query) {
                $query->where('type', 'Asset');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Cash%')
                      ->orWhere('name', 'like', '%Bank%');
            })
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('SalarySlips/ProcessPayment', [
            'salarySlip' => $salarySlip,
            'paymentAccounts' => $paymentAccounts,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Bulk generate salary slips.
     */
    public function bulkGenerate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m',
            'department_id' => 'nullable|exists:departments,id',
            'financial_year_id' => 'required|exists:financial_years,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $monthYear = Carbon::parse($request->month_year . '-01');

        // Get employees
        $query = Employee::with(['user', 'allowances.allowanceType', 'deductions.deductionType'])
            ->where('is_active', true);

        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            return back()->with('error', 'No active employees found matching the criteria.');
        }

        $employeeIds = $employees->pluck('id')->toArray();

        // Call generate method with employee IDs
        $generateRequest = new Request([
            'month_year' => $request->month_year,
            'department_id' => $request->department_id,
            'employee_ids' => $employeeIds,
            'financial_year_id' => $request->financial_year_id,
        ]);

        return $this->generate($generateRequest);
    }

    /**
     * Bulk process payments for salary slips.
     */
    public function bulkProcessPayments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m',
            'payment_date' => 'required|date',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'department_id' => 'nullable|exists:departments,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $monthYear = Carbon::parse($request->month_year . '-01');

        // Get unpaid salary slips
        $query = SalarySlip::where('payment_status', 'unpaid')
            ->whereYear('month_year', $monthYear->year)
            ->whereMonth('month_year', $monthYear->month);

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $salarySlips = $query->get();

        if ($salarySlips->isEmpty()) {
            return back()->with('error', 'No unpaid salary slips found matching the criteria.');
        }

        $processedCount = 0;
        $errors = [];

        foreach ($salarySlips as $salarySlip) {
            try {
                // Create payment request for this salary slip
                $paymentRequest = new Request([
                    'payment_date' => $request->payment_date,
                    'payment_account_id' => $request->payment_account_id,
                    'remarks' => $request->remarks,
                ]);

                // Process payment
                $this->processPayment($paymentRequest, $salarySlip);
                $processedCount++;

            } catch (\Exception $e) {
                $errors[] = "Error processing salary slip #{$salarySlip->reference_number}: {$e->getMessage()}";
            }
        }

        if (count($errors) > 0) {
            $errorMessage = "Processed {$processedCount} payments, but encountered " . count($errors) . " errors. First error: " . $errors[0];
            return back()->with('error', $errorMessage);
        }

        return redirect()->route('salary-slips.index', ['month_year' => $request->month_year])
            ->with('success', "Successfully processed {$processedCount} salary payments.");
    }

    /**
     * Compare salary reports between months or departments.
     */
    public function compareReport(Request $request)
    {
        // Check for comparison parameters
        if (!$request->has('compare_type') || !in_array($request->compare_type, ['month', 'department'])) {
            $request->merge(['compare_type' => 'month']);
        }

        if ($request->compare_type === 'month') {
            $validator = Validator::make($request->all(), [
                'first_month' => 'required|date_format:Y-m',
                'second_month' => 'required|date_format:Y-m|different:first_month',
                'department_id' => 'nullable|exists:departments,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $firstMonth = Carbon::parse($request->first_month . '-01');
            $secondMonth = Carbon::parse($request->second_month . '-01');

            // Get department constraint if provided
            $departmentConstraint = null;
            if ($request->has('department_id') && $request->department_id) {
                $departmentConstraint = function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                };
            }

            // Get first month data
            $firstMonthSlips = SalarySlip::with(['employee.user', 'employee.department'])
                ->whereYear('month_year', $firstMonth->year)
                ->whereMonth('month_year', $firstMonth->month)
                ->when($departmentConstraint, function ($query) use ($departmentConstraint) {
                    $query->whereHas('employee', $departmentConstraint);
                })
                ->get();

            // Get second month data
            $secondMonthSlips = SalarySlip::with(['employee.user', 'employee.department'])
                ->whereYear('month_year', $secondMonth->year)
                ->whereMonth('month_year', $secondMonth->month)
                ->when($departmentConstraint, function ($query) use ($departmentConstraint) {
                    $query->whereHas('employee', $departmentConstraint);
                })
                ->get();

            // Calculate totals for first month
            $firstMonthTotals = [
                'month' => $firstMonth->format('F Y'),
                'basic_salary' => $firstMonthSlips->sum('basic_salary'),
                'total_allowances' => $firstMonthSlips->sum('total_allowances'),
                'total_deductions' => $firstMonthSlips->sum('total_deductions'),
                'net_salary' => $firstMonthSlips->sum('net_salary'),
                'employee_count' => $firstMonthSlips->count(),
            ];

            // Calculate totals for second month
            $secondMonthTotals = [
                'month' => $secondMonth->format('F Y'),
                'basic_salary' => $secondMonthSlips->sum('basic_salary'),
                'total_allowances' => $secondMonthSlips->sum('total_allowances'),
                'total_deductions' => $secondMonthSlips->sum('total_deductions'),
                'net_salary' => $secondMonthSlips->sum('net_salary'),
                'employee_count' => $secondMonthSlips->count(),
            ];

            // Calculate differences
            $differences = [
                'basic_salary' => $secondMonthTotals['basic_salary'] - $firstMonthTotals['basic_salary'],
                'basic_salary_percentage' => $firstMonthTotals['basic_salary'] > 0 ?
                    (($secondMonthTotals['basic_salary'] - $firstMonthTotals['basic_salary']) / $firstMonthTotals['basic_salary'] * 100) : 0,
                'total_allowances' => $secondMonthTotals['total_allowances'] - $firstMonthTotals['total_allowances'],
                'total_allowances_percentage' => $firstMonthTotals['total_allowances'] > 0 ?
                    (($secondMonthTotals['total_allowances'] - $firstMonthTotals['total_allowances']) / $firstMonthTotals['total_allowances'] * 100) : 0,
                'total_deductions' => $secondMonthTotals['total_deductions'] - $firstMonthTotals['total_deductions'],
                'total_deductions_percentage' => $firstMonthTotals['total_deductions'] > 0 ?
                    (($secondMonthTotals['total_deductions'] - $firstMonthTotals['total_deductions']) / $firstMonthTotals['total_deductions'] * 100) : 0,
                'net_salary' => $secondMonthTotals['net_salary'] - $firstMonthTotals['net_salary'],
                'net_salary_percentage' => $firstMonthTotals['net_salary'] > 0 ?
                    (($secondMonthTotals['net_salary'] - $firstMonthTotals['net_salary']) / $firstMonthTotals['net_salary'] * 100) : 0,
                'employee_count' => $secondMonthTotals['employee_count'] - $firstMonthTotals['employee_count'],
                'employee_count_percentage' => $firstMonthTotals['employee_count'] > 0 ?
                    (($secondMonthTotals['employee_count'] - $firstMonthTotals['employee_count']) / $firstMonthTotals['employee_count'] * 100) : 0,
            ];

            // Get departments for filter
            $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
            $selectedDepartment = null;

            if ($request->has('department_id') && $request->department_id) {
                $selectedDepartment = Department::find($request->department_id);
            }

            $companySetting = CompanySetting::first();

            // Render the month comparison view
            return Inertia::render('SalarySlips/CompareMonths', [
                'firstMonthTotals' => $firstMonthTotals,
                'secondMonthTotals' => $secondMonthTotals,
                'differences' => $differences,
                'departments' => $departments,
                'selectedDepartment' => $selectedDepartment,
                'filters' => $request->only(['first_month', 'second_month', 'department_id', 'compare_type']),
                'companySetting' => $companySetting,
            ]);

        } else { // Department comparison
            $validator = Validator::make($request->all(), [
                'first_department_id' => 'required|exists:departments,id',
                'second_department_id' => 'required|exists:departments,id|different:first_department_id',
                'month_year' => 'required|date_format:Y-m',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $monthYear = Carbon::parse($request->month_year . '-01');

            // Get first department data
            $firstDepartment = Department::findOrFail($request->first_department_id);
            $firstDepartmentSlips = SalarySlip::with(['employee.user', 'employee.department'])
                ->whereYear('month_year', $monthYear->year)
                ->whereMonth('month_year', $monthYear->month)
                ->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->first_department_id);
                })
                ->get();

            // Get second department data
            $secondDepartment = Department::findOrFail($request->second_department_id);
            $secondDepartmentSlips = SalarySlip::with(['employee.user', 'employee.department'])
                ->whereYear('month_year', $monthYear->year)
                ->whereMonth('month_year', $monthYear->month)
                ->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->second_department_id);
                })
                ->get();

            // Calculate totals for first department
            $firstDepartmentTotals = [
                'department' => $firstDepartment->name,
                'basic_salary' => $firstDepartmentSlips->sum('basic_salary'),
                'total_allowances' => $firstDepartmentSlips->sum('total_allowances'),
                'total_deductions' => $firstDepartmentSlips->sum('total_deductions'),
                'net_salary' => $firstDepartmentSlips->sum('net_salary'),
                'employee_count' => $firstDepartmentSlips->count(),
                'average_salary' => $firstDepartmentSlips->count() > 0 ?
                    ($firstDepartmentSlips->sum('net_salary') / $firstDepartmentSlips->count()) : 0,
            ];

            // Calculate totals for second department
            $secondDepartmentTotals = [
                'department' => $secondDepartment->name,
                'basic_salary' => $secondDepartmentSlips->sum('basic_salary'),
                'total_allowances' => $secondDepartmentSlips->sum('total_allowances'),
                'total_deductions' => $secondDepartmentSlips->sum('total_deductions'),
                'net_salary' => $secondDepartmentSlips->sum('net_salary'),
                'employee_count' => $secondDepartmentSlips->count(),
                'average_salary' => $secondDepartmentSlips->count() > 0 ?
                    ($secondDepartmentSlips->sum('net_salary') / $secondDepartmentSlips->count()) : 0,
            ];

            // Get all departments for filter
            $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
            $companySetting = CompanySetting::first();

            // Render the department comparison view
            return Inertia::render('SalarySlips/CompareDepartments', [
                'firstDepartmentTotals' => $firstDepartmentTotals,
                'secondDepartmentTotals' => $secondDepartmentTotals,
                'monthYear' => $monthYear->format('F Y'),
                'departments' => $departments,
                'filters' => $request->only(['first_department_id', 'second_department_id', 'month_year', 'compare_type']),
                'companySetting' => $companySetting,
            ]);
        }
    }
}
