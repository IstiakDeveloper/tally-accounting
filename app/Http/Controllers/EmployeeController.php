<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\AllowanceType;
use App\Models\DeductionType;
use App\Models\LeaveApplication;
use App\Models\SalarySlip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(Request $request)
    {
        $query = Employee::with(['user', 'department', 'designation']);

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by designation
        if ($request->has('designation_id') && $request->designation_id) {
            $query->where('designation_id', $request->designation_id);
        }

        // Filter by employment status
        if ($request->has('employment_status') && $request->employment_status !== 'all') {
            $query->where('employment_status', $request->employment_status);
        }

        // Filter by active status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by employee_id or user name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($query) use ($search) {
                      $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $employees = $query->orderBy('employee_id', 'asc')->paginate(10)->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $designations = Designation::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'departments' => $departments,
            'designations' => $designations,
            'filters' => $request->only([
                'department_id',
                'designation_id',
                'employment_status',
                'status',
                'search'
            ]),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $designations = Designation::where('is_active', true)->orderBy('name', 'asc')->get();

        // Get salary expense accounts
        $salaryAccounts = ChartOfAccount::whereHas('category', function ($query) {
                $query->where('type', 'Expense');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Salary%')
                      ->orWhere('name', 'like', '%Wages%')
                      ->orWhere('name', 'like', '%Payroll%');
            })
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        // Generate unique employee ID
        $lastEmployee = Employee::orderBy('id', 'desc')->first();
        $employeeId = 'EMP-0001';

        if ($lastEmployee) {
            $lastId = (int) substr($lastEmployee->employee_id, 4);
            $employeeId = 'EMP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        }

        return Inertia::render('Employees/Create', [
            'departments' => $departments,
            'designations' => $designations,
            'salaryAccounts' => $salaryAccounts,
            'employeeId' => $employeeId,
            'employmentStatuses' => [
                'permanent' => 'স্থায়ী',
                'probation' => 'প্রবেশন',
                'contract' => 'চুক্তিভিত্তিক',
                'part-time' => 'খণ্ডকালীন',
            ],
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|string|unique:employees',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'joining_date' => 'required|date',
            'employment_status' => 'required|in:permanent,probation,contract,part-time',
            'contract_end_date' => 'nullable|date|after:joining_date',
            'basic_salary' => 'required|numeric|min:0',
            'salary_account_id' => 'required|exists:chart_of_accounts,id',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'is_active' => $request->is_active ?? true,
            ]);

            // Create employee record
            $employee = Employee::create([
                'employee_id' => $request->employee_id,
                'user_id' => $user->id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'joining_date' => $request->joining_date,
                'employment_status' => $request->employment_status,
                'contract_end_date' => $request->contract_end_date,
                'basic_salary' => $request->basic_salary,
                'salary_account_id' => $request->salary_account_id,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'tax_identification_number' => $request->tax_identification_number,
                'is_active' => $request->is_active ?? true,
            ]);

            DB::commit();

            return redirect()->route('employees.show', $employee->id)
                ->with('success', 'Employee created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee)
    {
        $employee->load([
            'user',
            'department',
            'designation',
            'salaryAccount',
            'allowances.allowanceType',
            'deductions.deductionType',
        ]);

        // Get recent salary slips
        $salarySlips = SalarySlip::where('employee_id', $employee->id)
            ->orderBy('month_year', 'desc')
            ->limit(12)
            ->get();

        // Get recent leave applications
        $leaveApplications = LeaveApplication::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'salarySlips' => $salarySlips,
            'leaveApplications' => $leaveApplications,
            'employmentStatuses' => [
                'permanent' => 'স্থায়ী',
                'probation' => 'প্রবেশন',
                'contract' => 'চুক্তিভিত্তিক',
                'part-time' => 'খণ্ডকালীন',
            ],
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee)
    {
        $employee->load(['user', 'department', 'designation', 'salaryAccount']);

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();

        // Get designations for the current department
        $designations = Designation::where('department_id', $employee->department_id)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        // Get salary expense accounts
        $salaryAccounts = ChartOfAccount::whereHas('category', function ($query) {
                $query->where('type', 'Expense');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Salary%')
                      ->orWhere('name', 'like', '%Wages%')
                      ->orWhere('name', 'like', '%Payroll%');
            })
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'departments' => $departments,
            'designations' => $designations,
            'salaryAccounts' => $salaryAccounts,
            'employmentStatuses' => [
                'permanent' => 'স্থায়ী',
                'probation' => 'প্রবেশন',
                'contract' => 'চুক্তিভিত্তিক',
                'part-time' => 'খণ্ডকালীন',
            ],
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|string|unique:employees,employee_id,' . $employee->id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $employee->user_id,
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'joining_date' => 'required|date',
            'employment_status' => 'required|in:permanent,probation,contract,part-time',
            'contract_end_date' => 'nullable|date|after:joining_date',
            'basic_salary' => 'required|numeric|min:0',
            'salary_account_id' => 'required|exists:chart_of_accounts,id',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Update user information
            $user = User::find($employee->user_id);
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_active' => $request->is_active ?? $user->is_active,
            ]);

            // Update password if provided
            if ($request->has('password') && $request->password) {
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            }

            // Update employee record
            $employee->update([
                'employee_id' => $request->employee_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'joining_date' => $request->joining_date,
                'employment_status' => $request->employment_status,
                'contract_end_date' => $request->contract_end_date,
                'basic_salary' => $request->basic_salary,
                'salary_account_id' => $request->salary_account_id,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'tax_identification_number' => $request->tax_identification_number,
                'is_active' => $request->is_active ?? $employee->is_active,
            ]);

            DB::commit();

            return redirect()->route('employees.show', $employee->id)
                ->with('success', 'Employee updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Employee $employee)
    {
        try {
            DB::beginTransaction();

            // Check if any salary slips exist
            $salarySlipCount = SalarySlip::where('employee_id', $employee->id)->count();
            if ($salarySlipCount > 0) {
                return back()->with('error', 'Cannot delete employee. They have ' . $salarySlipCount . ' salary records.');
            }

            // Check if any leave applications exist
            $leaveApplicationCount = LeaveApplication::where('employee_id', $employee->id)->count();
            if ($leaveApplicationCount > 0) {
                return back()->with('error', 'Cannot delete employee. They have ' . $leaveApplicationCount . ' leave applications.');
            }

            // Delete allowances and deductions
            EmployeeAllowance::where('employee_id', $employee->id)->delete();
            EmployeeDeduction::where('employee_id', $employee->id)->delete();

            // Get user ID for later deletion
            $userId = $employee->user_id;

            // Delete employee record
            $employee->delete();

            // Delete user account
            User::find($userId)->delete();

            DB::commit();

            return redirect()->route('employees.index')
                ->with('success', 'Employee deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the status of the employee.
     */
    public function toggleStatus(Employee $employee)
    {
        try {
            DB::beginTransaction();

            // Update employee status
            $employee->update([
                'is_active' => !$employee->is_active,
            ]);

            // Update user status
            $user = User::find($employee->user_id);
            $user->update([
                'is_active' => !$user->is_active,
            ]);

            DB::commit();

            return back()->with('success', 'Employee status updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for managing employee allowances.
     */
    public function manageAllowances(Employee $employee)
    {
        $employee->load(['user', 'allowances.allowanceType']);

        $allowanceTypes = AllowanceType::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $existingAllowanceTypeIds = $employee->allowances->pluck('allowance_type_id')->toArray();

        return Inertia::render('Employees/ManageAllowances', [
            'employee' => $employee,
            'allowanceTypes' => $allowanceTypes,
            'existingAllowanceTypeIds' => $existingAllowanceTypeIds,
        ]);
    }

    /**
     * Store employee allowances.
     */
    public function storeAllowances(Request $request, Employee $employee)
    {
        $validator = Validator::make($request->all(), [
            'allowances' => 'required|array',
            'allowances.*.allowance_type_id' => 'required|exists:allowance_types,id',
            'allowances.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Delete existing allowances
            EmployeeAllowance::where('employee_id', $employee->id)->delete();

            // Create new allowances
            foreach ($request->allowances as $allowance) {
                EmployeeAllowance::create([
                    'employee_id' => $employee->id,
                    'allowance_type_id' => $allowance['allowance_type_id'],
                    'amount' => $allowance['amount'],
                ]);
            }

            DB::commit();

            return redirect()->route('employees.show', $employee->id)
                ->with('success', 'Employee allowances updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for managing employee deductions.
     */
    public function manageDeductions(Employee $employee)
    {
        $employee->load(['user', 'deductions.deductionType']);

        $deductionTypes = DeductionType::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $existingDeductionTypeIds = $employee->deductions->pluck('deduction_type_id')->toArray();

        return Inertia::render('Employees/ManageDeductions', [
            'employee' => $employee,
            'deductionTypes' => $deductionTypes,
            'existingDeductionTypeIds' => $existingDeductionTypeIds,
        ]);
    }

    /**
     * Store employee deductions.
     */
    public function storeDeductions(Request $request, Employee $employee)
    {
        $validator = Validator::make($request->all(), [
            'deductions' => 'required|array',
            'deductions.*.deduction_type_id' => 'required|exists:deduction_types,id',
            'deductions.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Delete existing deductions
            EmployeeDeduction::where('employee_id', $employee->id)->delete();

            // Create new deductions
            foreach ($request->deductions as $deduction) {
                EmployeeDeduction::create([
                    'employee_id' => $employee->id,
                    'deduction_type_id' => $deduction['deduction_type_id'],
                    'amount' => $deduction['amount'],
                ]);
            }

            DB::commit();

            return redirect()->route('employees.show', $employee->id)
                ->with('success', 'Employee deductions updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Get designations by department for dynamic loading in forms.
     */
    public function getDesignationsByDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid department'], 422);
        }

        $designations = Designation::where('department_id', $request->department_id)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($designations);
    }

    /**
     * Export employees data to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = Employee::with(['user', 'department', 'designation']);

        // Apply filters
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('designation_id') && $request->designation_id) {
            $query->where('designation_id', $request->designation_id);
        }

        if ($request->has('employment_status') && $request->employment_status !== 'all') {
            $query->where('employment_status', $request->employment_status);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $employees = $query->orderBy('employee_id', 'asc')->get();

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();
        $designations = Designation::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Employees/ExportPdf', [
            'employees' => $employees,
            'departments' => $departments,
            'designations' => $designations,
            'filters' => $request->only([
                'department_id',
                'designation_id',
                'employment_status',
                'status'
            ]),
            'employmentStatuses' => [
                'permanent' => 'স্থায়ী',
                'probation' => 'প্রবেশন',
                'contract' => 'চুক্তিভিত্তিক',
                'part-time' => 'খণ্ডকালীন',
            ],
        ]);
    }

    /**
     * Generate department-wise employee report.
     */
    public function departmentReport()
    {
        $departments = Department::with(['designations', 'manager'])
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $departmentStats = [];

        foreach ($departments as $department) {
            $employees = Employee::where('department_id', $department->id)->get();
            $activeEmployees = $employees->where('is_active', true);

            $designationStats = [];
            foreach ($department->designations as $designation) {
                $designationEmployees = $employees->where('designation_id', $designation->id);
                $activeDesignationEmployees = $designationEmployees->where('is_active', true);

                $designationStats[] = [
                    'id' => $designation->id,
                    'name' => $designation->name,
                    'total_employees' => $designationEmployees->count(),
                    'active_employees' => $activeDesignationEmployees->count(),
                ];
            }

            $departmentStats[] = [
                'id' => $department->id,
                'name' => $department->name,
                'manager' => $department->manager ? $department->manager->name : 'Not Assigned',
                'total_employees' => $employees->count(),
                'active_employees' => $activeEmployees->count(),
                'designations' => $designationStats,
            ];
        }

        return Inertia::render('Employees/DepartmentReport', [
            'departmentStats' => $departmentStats,
        ]);
    }

    /**
     * Generate salary summary report.
     */
    public function salarySummaryReport(Request $request)
    {
        $query = Employee::with(['user', 'department', 'designation', 'allowances.allowanceType', 'deductions.deductionType'])
            ->where('is_active', true);

        // Apply filters
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->orderBy('employee_id', 'asc')->get();

        $salarySummary = [];
        $totalBasicSalary = 0;
        $totalAllowances = 0;
        $totalDeductions = 0;
        $totalNetSalary = 0;

        foreach ($employees as $employee) {
            $employeeAllowances = $employee->allowances->sum('amount');
            $employeeDeductions = $employee->deductions->sum('amount');
            $netSalary = $employee->basic_salary + $employeeAllowances - $employeeDeductions;

            $salarySummary[] = [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->user->name,
                'department' => $employee->department->name,
                'designation' => $employee->designation->name,
                'basic_salary' => $employee->basic_salary,
                'allowances' => $employeeAllowances,
                'deductions' => $employeeDeductions,
                'net_salary' => $netSalary,
            ];

            $totalBasicSalary += $employee->basic_salary;
            $totalAllowances += $employeeAllowances;
            $totalDeductions += $employeeDeductions;
            $totalNetSalary += $netSalary;
        }

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Employees/SalarySummaryReport', [
            'salarySummary' => $salarySummary,
            'departments' => $departments,
            'filters' => $request->only(['department_id']),
            'totals' => [
                'basic_salary' => $totalBasicSalary,
                'allowances' => $totalAllowances,
                'deductions' => $totalDeductions,
                'net_salary' => $totalNetSalary,
            ],
        ]);
    }
}
