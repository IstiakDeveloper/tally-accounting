<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Models\AccountCategory;
use App\Models\ChartOfAccount;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Display a listing of the report templates.
     */
    public function index(Request $request)
    {
        $query = ReportTemplate::withCount('savedReports');

        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $templates = $query->orderBy('name', 'asc')->paginate(10)->withQueryString();

        // Recent saved reports
        $recentReports = SavedReport::with('reportTemplate')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Reports/Index', [
            'templates' => $templates,
            'recentReports' => $recentReports,
            'filters' => $request->only(['type', 'status', 'search']),
            'typeOptions' => [
                ['value' => 'all', 'label' => 'All Types'],
                ['value' => 'financial', 'label' => 'Financial'],
                ['value' => 'inventory', 'label' => 'Inventory'],
                ['value' => 'sales', 'label' => 'Sales'],
                ['value' => 'purchase', 'label' => 'Purchase'],
                ['value' => 'payroll', 'label' => 'Payroll'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new report template.
     */
    public function createTemplate()
    {
        return Inertia::render('Reports/CreateTemplate', [
            'typeOptions' => [
                ['value' => 'financial', 'label' => 'Financial'],
                ['value' => 'inventory', 'label' => 'Inventory'],
                ['value' => 'sales', 'label' => 'Sales'],
                ['value' => 'purchase', 'label' => 'Purchase'],
                ['value' => 'payroll', 'label' => 'Payroll'],
            ],
        ]);
    }

    /**
     * Store a newly created report template in storage.
     */
    public function storeTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:report_templates',
            'type' => 'required|in:financial,inventory,sales,purchase,payroll',
            'description' => 'nullable|string',
            'structure' => 'required|json',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $template = ReportTemplate::create([
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'structure' => $request->structure,
                'is_active' => $request->is_active ?? true,
            ]);

            return redirect()->route('reports.index')
                ->with('success', 'Report template created successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified report template.
     */
    public function editTemplate(ReportTemplate $template)
    {
        return Inertia::render('Reports/EditTemplate', [
            'template' => $template,
            'typeOptions' => [
                ['value' => 'financial', 'label' => 'Financial'],
                ['value' => 'inventory', 'label' => 'Inventory'],
                ['value' => 'sales', 'label' => 'Sales'],
                ['value' => 'purchase', 'label' => 'Purchase'],
                ['value' => 'payroll', 'label' => 'Payroll'],
            ],
        ]);
    }

    /**
     * Update the specified report template in storage.
     */
    public function updateTemplate(Request $request, ReportTemplate $template)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:report_templates,name,' . $template->id,
            'type' => 'required|in:financial,inventory,sales,purchase,payroll',
            'description' => 'nullable|string',
            'structure' => 'required|json',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $template->update([
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'structure' => $request->structure,
                'is_active' => $request->is_active ?? $template->is_active,
            ]);

            return redirect()->route('reports.index')
                ->with('success', 'Report template updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified report template from storage.
     */
    public function destroyTemplate(ReportTemplate $template)
    {
        try {
            // Check if there are saved reports using this template
            $savedReportsCount = $template->savedReports()->count();

            if ($savedReportsCount > 0) {
                return back()->with('error', "Cannot delete template. It is used by {$savedReportsCount} saved reports.");
            }

            $template->delete();

            return redirect()->route('reports.index')
                ->with('success', 'Report template deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Generate report based on template.
     */
    public function generate(Request $request, ReportTemplate $template)
    {
        if (!$template->is_active) {
            return back()->with('error', 'This report template is not active.');
        }

        // Load company settings for report headers
        $companySetting = CompanySetting::first();

        // Initialize parameters
        $parameters = [];
        $fromDate = null;
        $toDate = null;

        // Process parameters based on template type
        if ($template->type === 'financial') {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'financial_year_id' => 'nullable|exists:financial_years,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $fromDate = Carbon::parse($request->from_date);
            $toDate = Carbon::parse($request->to_date);

            // Get financial year
            $financialYear = null;
            if ($request->financial_year_id) {
                $financialYear = FinancialYear::find($request->financial_year_id);
            } else {
                $financialYear = FinancialYear::where('is_active', true)->first();

                if (!$financialYear) {
                    return back()->with('error', 'No active financial year found.');
                }
            }

            $parameters = [
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'financial_year_id' => $financialYear->id,
                'financial_year_name' => $financialYear->name,
            ];

            // Generate financial report data
            return $this->generateFinancialReport($template, $parameters, $companySetting);

        } elseif ($template->type === 'inventory') {
            $validator = Validator::make($request->all(), [
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'product_category_id' => 'nullable|exists:product_categories,id',
                'product_id' => 'nullable|exists:products,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            if ($request->from_date) {
                $fromDate = Carbon::parse($request->from_date);
            }

            if ($request->to_date) {
                $toDate = Carbon::parse($request->to_date);
            }

            $parameters = [
                'from_date' => $fromDate ? $fromDate->format('Y-m-d') : null,
                'to_date' => $toDate ? $toDate->format('Y-m-d') : null,
                'warehouse_id' => $request->warehouse_id,
                'product_category_id' => $request->product_category_id,
                'product_id' => $request->product_id,
            ];

            // Generate inventory report data
            return $this->generateInventoryReport($template, $parameters, $companySetting);

        } elseif ($template->type === 'sales') {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'customer_id' => 'nullable|exists:contacts,id',
                'product_id' => 'nullable|exists:products,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $fromDate = Carbon::parse($request->from_date);
            $toDate = Carbon::parse($request->to_date);

            $parameters = [
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'customer_id' => $request->customer_id,
                'product_id' => $request->product_id,
            ];

            // Generate sales report data
            return $this->generateSalesReport($template, $parameters, $companySetting);

        } elseif ($template->type === 'purchase') {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'supplier_id' => 'nullable|exists:contacts,id',
                'product_id' => 'nullable|exists:products,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $fromDate = Carbon::parse($request->from_date);
            $toDate = Carbon::parse($request->to_date);

            $parameters = [
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'supplier_id' => $request->supplier_id,
                'product_id' => $request->product_id,
            ];

            // Generate purchase report data
            return $this->generatePurchaseReport($template, $parameters, $companySetting);

        } elseif ($template->type === 'payroll') {
            $validator = Validator::make($request->all(), [
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'department_id' => 'nullable|exists:departments,id',
                'employee_id' => 'nullable|exists:employees,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            if ($request->from_date) {
                $fromDate = Carbon::parse($request->from_date);
            }

            if ($request->to_date) {
                $toDate = Carbon::parse($request->to_date);
            }

            $parameters = [
                'from_date' => $fromDate ? $fromDate->format('Y-m-d') : null,
                'to_date' => $toDate ? $toDate->format('Y-m-d') : null,
                'department_id' => $request->department_id,
                'employee_id' => $request->employee_id,
            ];

            // Generate payroll report data
            return $this->generatePayrollReport($template, $parameters, $companySetting);
        }

        return back()->with('error', 'Invalid report template type.');
    }

    /**
     * Save the generated report.
     */
    public function saveReport(Request $request, ReportTemplate $template)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parameters' => 'required|array',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'data' => 'required|json',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            SavedReport::create([
                'report_template_id' => $template->id,
                'name' => $request->name,
                'parameters' => $request->parameters,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'data' => $request->data,
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('reports.index')
                ->with('success', 'Report saved successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the saved report.
     */
    public function showSavedReport(SavedReport $report)
    {
        $report->load(['reportTemplate', 'createdBy']);
        $companySetting = CompanySetting::first();

        return Inertia::render('Reports/ShowSavedReport', [
            'report' => $report,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Delete the saved report.
     */
    public function destroySavedReport(SavedReport $report)
    {
        try {
            $report->delete();

            return redirect()->route('reports.index')
                ->with('success', 'Saved report deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Generate financial report data.
     */
    private function generateFinancialReport(ReportTemplate $template, array $parameters, $companySetting)
    {
        $structure = $template->structure;
        $reportType = $structure['report_type'] ?? 'balance_sheet';

        $fromDate = Carbon::parse($parameters['from_date']);
        $toDate = Carbon::parse($parameters['to_date']);
        $financialYearId = $parameters['financial_year_id'];

        $data = [];

        if ($reportType === 'balance_sheet') {
            // Generate balance sheet
            $data = $this->generateBalanceSheet($fromDate, $toDate, $financialYearId);

            return Inertia::render('Reports/BalanceSheet', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'income_statement') {
            // Generate income statement
            $data = $this->generateIncomeStatement($fromDate, $toDate, $financialYearId);

            return Inertia::render('Reports/IncomeStatement', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'cash_flow') {
            // Generate cash flow statement
            $data = $this->generateCashFlowStatement($fromDate, $toDate, $financialYearId);

            return Inertia::render('Reports/CashFlow', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'trial_balance') {
            // Generate trial balance
            $data = $this->generateTrialBalance($fromDate, $toDate, $financialYearId);

            return Inertia::render('Reports/TrialBalance', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'general_ledger') {
            // Get account if specified in parameters
            $accountId = $parameters['account_id'] ?? null;

            // Generate general ledger
            $data = $this->generateGeneralLedger($fromDate, $toDate, $financialYearId, $accountId);

            return Inertia::render('Reports/GeneralLedger', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);
        }

        return back()->with('error', 'Invalid financial report type.');
    }

    /**
     * Generate balance sheet data.
     */
    private function generateBalanceSheet($fromDate, $toDate, $financialYearId)
    {
        // Get asset accounts
        $assetAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })->with('category')->where('is_active', true)->get();

        // Get liability accounts
        $liabilityAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Liability');
        })->with('category')->where('is_active', true)->get();

        // Get equity accounts
        $equityAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Equity');
        })->with('category')->where('is_active', true)->get();

        // Calculate account balances
        $assetData = $this->calculateAccountBalances($assetAccounts, $fromDate, $toDate, $financialYearId);
        $liabilityData = $this->calculateAccountBalances($liabilityAccounts, $fromDate, $toDate, $financialYearId);
        $equityData = $this->calculateAccountBalances($equityAccounts, $fromDate, $toDate, $financialYearId);

        // Calculate retained earnings (Net Income for the period)
        $retainedEarnings = $this->calculateRetainedEarnings($fromDate, $toDate, $financialYearId);

        // Add retained earnings to equity
        $equityData[] = [
            'account_id' => null,
            'account_code' => null,
            'name' => 'Retained Earnings',
            'category' => 'Equity',
            'balance' => $retainedEarnings,
            'formatted_balance' => number_format($retainedEarnings, 2),
        ];

        // Calculate totals
        $totalAssets = collect($assetData)->sum('balance');
        $totalLiabilities = collect($liabilityData)->sum('balance');
        $totalEquity = collect($equityData)->sum('balance');

        return [
            'asset_accounts' => $assetData,
            'liability_accounts' => $liabilityData,
            'equity_accounts' => $equityData,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'formatted_total_assets' => number_format($totalAssets, 2),
            'formatted_total_liabilities' => number_format($totalLiabilities, 2),
            'formatted_total_equity' => number_format($totalEquity, 2),
            'balanced' => abs(($totalLiabilities + $totalEquity) - $totalAssets) < 0.01,
        ];
    }

    /**
     * Generate income statement data.
     */
    private function generateIncomeStatement($fromDate, $toDate, $financialYearId)
    {
        // Get revenue accounts
        $revenueAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Revenue');
        })->with('category')->where('is_active', true)->get();

        // Get expense accounts
        $expenseAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Expense');
        })->with('category')->where('is_active', true)->get();

        // Calculate account balances for the period
        $revenueData = $this->calculateAccountBalances($revenueAccounts, $fromDate, $toDate, $financialYearId, true);
        $expenseData = $this->calculateAccountBalances($expenseAccounts, $fromDate, $toDate, $financialYearId, true);

        // Calculate totals
        $totalRevenue = collect($revenueData)->sum('balance');
        $totalExpenses = collect($expenseData)->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'revenue_accounts' => $revenueData,
            'expense_accounts' => $expenseData,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome,
            'formatted_total_revenue' => number_format($totalRevenue, 2),
            'formatted_total_expenses' => number_format($totalExpenses, 2),
            'formatted_net_income' => number_format($netIncome, 2),
        ];
    }

    /**
     * Generate cash flow statement data.
     */
    private function generateCashFlowStatement($fromDate, $toDate, $financialYearId)
    {
        // Get cash and cash equivalent accounts
        $cashAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })->where(function ($query) {
            $query->where('name', 'like', '%Cash%')
                  ->orWhere('name', 'like', '%Bank%');
        })->with('category')->where('is_active', true)->get();

        // Get journal entries for cash accounts within the period
        $journalEntries = JournalEntry::whereIn('status', ['posted'])
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->where('financial_year_id', $financialYearId)
            ->with(['items' => function ($query) use ($cashAccounts) {
                $query->whereIn('account_id', $cashAccounts->pluck('id'));
            }])
            ->get();

        // Group by cash flow categories
        $operatingActivities = [];
        $investingActivities = [];
        $financingActivities = [];

        foreach ($journalEntries as $entry) {
            foreach ($entry->items as $item) {
                // Skip items with zero amount
                if ($item->amount == 0) {
                    continue;
                }

                // Calculate flow amount (positive for cash inflow, negative for outflow)
                $flowAmount = $item->type === 'debit' ? $item->amount : -$item->amount;

                // Categorize by narration keywords
                $narration = strtolower($entry->narration);

                // Operating activities
                if (strpos($narration, 'sale') !== false ||
                    strpos($narration, 'invoice') !== false ||
                    strpos($narration, 'payment') !== false ||
                    strpos($narration, 'receipt') !== false ||
                    strpos($narration, 'expense') !== false ||
                    strpos($narration, 'salary') !== false) {

                    $operatingActivities[] = [
                        'date' => $entry->entry_date,
                        'reference' => $entry->reference_number,
                        'description' => $entry->narration,
                        'account' => $item->account->name,
                        'amount' => $flowAmount,
                        'formatted_amount' => number_format(abs($flowAmount), 2) . ($flowAmount < 0 ? ' (Outflow)' : ' (Inflow)'),
                    ];
                }
                // Investing activities
                elseif (strpos($narration, 'purchase of asset') !== false ||
                       strpos($narration, 'sale of asset') !== false ||
                       strpos($narration, 'investment') !== false) {

                    $investingActivities[] = [
                        'date' => $entry->entry_date,
                        'reference' => $entry->reference_number,
                        'description' => $entry->narration,
                        'account' => $item->account->name,
                        'amount' => $flowAmount,
                        'formatted_amount' => number_format(abs($flowAmount), 2) . ($flowAmount < 0 ? ' (Outflow)' : ' (Inflow)'),
                    ];
                }
                // Financing activities
                elseif (strpos($narration, 'loan') !== false ||
                       strpos($narration, 'capital') !== false ||
                       strpos($narration, 'dividend') !== false ||
                       strpos($narration, 'share') !== false) {

                    $financingActivities[] = [
                        'date' => $entry->entry_date,
                        'reference' => $entry->reference_number,
                        'description' => $entry->narration,
                        'account' => $item->account->name,
                        'amount' => $flowAmount,
                        'formatted_amount' => number_format(abs($flowAmount), 2) . ($flowAmount < 0 ? ' (Outflow)' : ' (Inflow)'),
                    ];
                }
                // Other activities go to operating by default
                else {
                    $operatingActivities[] = [
                        'date' => $entry->entry_date,
                        'reference' => $entry->reference_number,
                        'description' => $entry->narration,
                        'account' => $item->account->name,
                        'amount' => $flowAmount,
                        'formatted_amount' => number_format(abs($flowAmount), 2) . ($flowAmount < 0 ? ' (Outflow)' : ' (Inflow)'),
                    ];
                }
            }
        }

        // Calculate totals
        $totalOperating = collect($operatingActivities)->sum('amount');
        $totalInvesting = collect($investingActivities)->sum('amount');
        $totalFinancing = collect($financingActivities)->sum('amount');
        $netCashFlow = $totalOperating + $totalInvesting + $totalFinancing;

        // Get opening cash balance
        $openingCashBalance = 0;
        foreach ($cashAccounts as $account) {
            $openingDebits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<', $fromDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'debit')
                ->sum('amount');

            $openingCredits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<', $fromDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'credit')
                ->sum('amount');

            $openingCashBalance += ($openingDebits - $openingCredits);
        }

        // Calculate closing cash balance
        $closingCashBalance = $openingCashBalance + $netCashFlow;

        return [
            'operating_activities' => $operatingActivities,
            'investing_activities' => $investingActivities,
            'financing_activities' => $financingActivities,
            'total_operating' => $totalOperating,
            'total_investing' => $totalInvesting,
            'total_financing' => $totalFinancing,
            'net_cash_flow' => $netCashFlow,
            'opening_cash_balance' => $openingCashBalance,
            'closing_cash_balance' => $closingCashBalance,
            'formatted_total_operating' => number_format($totalOperating, 2),
            'formatted_total_investing' => number_format($totalInvesting, 2),
            'formatted_total_financing' => number_format($totalFinancing, 2),
            'formatted_net_cash_flow' => number_format($netCashFlow, 2),
            'formatted_opening_cash_balance' => number_format($openingCashBalance, 2),
            'formatted_closing_cash_balance' => number_format($closingCashBalance, 2),
        ];
    }

     /**
     * Generate trial balance data.
     */
    private function generateTrialBalance($fromDate, $toDate, $financialYearId)
    {
        // Get all active accounts
        $accounts = ChartOfAccount::with('category')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $trialBalanceData = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            // Get all journal items for this account within the period
            $journalItems = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->whereBetween('entry_date', [$fromDate, $toDate])
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->get();

            $debitTotal = $journalItems->where('type', 'debit')->sum('amount');
            $creditTotal = $journalItems->where('type', 'credit')->sum('amount');

            // Only include accounts with activity
            if ($debitTotal > 0 || $creditTotal > 0) {
                $trialBalanceData[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'category' => $account->category->name,
                    'type' => $account->category->type,
                    'debit' => $debitTotal,
                    'credit' => $creditTotal,
                    'formatted_debit' => number_format($debitTotal, 2),
                    'formatted_credit' => number_format($creditTotal, 2),
                ];

                $totalDebits += $debitTotal;
                $totalCredits += $creditTotal;
            }
        }

        return [
            'accounts' => $trialBalanceData,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'formatted_total_debits' => number_format($totalDebits, 2),
            'formatted_total_credits' => number_format($totalCredits, 2),
            'balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ];
    }

    /**
     * Generate general ledger data.
     */
    private function generateGeneralLedger($fromDate, $toDate, $financialYearId, $accountId = null)
    {
        $query = ChartOfAccount::with('category')->where('is_active', true);

        // Filter by specific account if provided
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->orderBy('account_code')->get();
        $ledgerData = [];

        foreach ($accounts as $account) {
            // Get opening balance
            $openingDebits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<', $fromDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'debit')
                ->sum('amount');

            $openingCredits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<', $fromDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'credit')
                ->sum('amount');

            // Calculate opening balance based on account type
            $openingBalance = 0;
            if (in_array($account->category->type, ['Asset', 'Expense'])) {
                $openingBalance = $openingDebits - $openingCredits;
            } else {
                $openingBalance = $openingCredits - $openingDebits;
            }

            // Get journal entries for this account within the period
            $journalItems = JournalItem::with(['journalEntry'])
                ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->whereBetween('entry_date', [$fromDate, $toDate])
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->get()
                ->sortBy('journalEntry.entry_date');

            // Skip accounts with no activity and zero opening balance
            if ($journalItems->isEmpty() && $openingBalance == 0) {
                continue;
            }

            $transactions = [];
            $runningBalance = $openingBalance;

            // Add opening balance entry
            $transactions[] = [
                'date' => $fromDate->format('Y-m-d'),
                'reference' => 'Opening Balance',
                'description' => 'Opening Balance',
                'debit' => $openingBalance > 0 ? $openingBalance : 0,
                'credit' => $openingBalance < 0 ? abs($openingBalance) : 0,
                'balance' => $runningBalance,
                'formatted_debit' => $openingBalance > 0 ? number_format($openingBalance, 2) : '-',
                'formatted_credit' => $openingBalance < 0 ? number_format(abs($openingBalance), 2) : '-',
                'formatted_balance' => number_format($runningBalance, 2),
            ];

            // Add journal entries
            foreach ($journalItems as $item) {
                if (in_array($account->category->type, ['Asset', 'Expense'])) {
                    // For Asset and Expense accounts, debits increase, credits decrease
                    if ($item->type === 'debit') {
                        $runningBalance += $item->amount;
                    } else {
                        $runningBalance -= $item->amount;
                    }
                } else {
                    // For Liability, Equity, and Revenue accounts, credits increase, debits decrease
                    if ($item->type === 'credit') {
                        $runningBalance += $item->amount;
                    } else {
                        $runningBalance -= $item->amount;
                    }
                }

                $transactions[] = [
                    'date' => $item->journalEntry->entry_date,
                    'reference' => $item->journalEntry->reference_number,
                    'description' => $item->journalEntry->narration,
                    'debit' => $item->type === 'debit' ? $item->amount : 0,
                    'credit' => $item->type === 'credit' ? $item->amount : 0,
                    'balance' => $runningBalance,
                    'formatted_debit' => $item->type === 'debit' ? number_format($item->amount, 2) : '-',
                    'formatted_credit' => $item->type === 'credit' ? number_format($item->amount, 2) : '-',
                    'formatted_balance' => number_format($runningBalance, 2),
                ];
            }

            $ledgerData[] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'category' => $account->category->name,
                'type' => $account->category->type,
                'transactions' => $transactions,
                'opening_balance' => $openingBalance,
                'closing_balance' => $runningBalance,
                'formatted_opening_balance' => number_format($openingBalance, 2),
                'formatted_closing_balance' => number_format($runningBalance, 2),
            ];
        }

        return [
            'accounts' => $ledgerData,
            'from_date' => $fromDate->format('Y-m-d'),
            'to_date' => $toDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate account balances for the given accounts.
     */
    private function calculateAccountBalances($accounts, $fromDate, $toDate, $financialYearId, $periodOnly = false)
    {
        $accountData = [];

        foreach ($accounts as $account) {
            if ($periodOnly) {
                // Only calculate transactions within the period
                $debits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $toDate, $financialYearId) {
                        $query->where('status', 'posted')
                              ->whereBetween('entry_date', [$fromDate, $toDate])
                              ->where('financial_year_id', $financialYearId);
                    })
                    ->where('account_id', $account->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate, $toDate, $financialYearId) {
                        $query->where('status', 'posted')
                              ->whereBetween('entry_date', [$fromDate, $toDate])
                              ->where('financial_year_id', $financialYearId);
                    })
                    ->where('account_id', $account->id)
                    ->where('type', 'credit')
                    ->sum('amount');
            } else {
                // Calculate all transactions up to the end date
                $debits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                        $query->where('status', 'posted')
                              ->where('entry_date', '<=', $toDate)
                              ->where('financial_year_id', $financialYearId);
                    })
                    ->where('account_id', $account->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                        $query->where('status', 'posted')
                              ->where('entry_date', '<=', $toDate)
                              ->where('financial_year_id', $financialYearId);
                    })
                    ->where('account_id', $account->id)
                    ->where('type', 'credit')
                    ->sum('amount');
            }

            // Calculate balance based on account type
            $balance = 0;
            if (in_array($account->category->type, ['Asset', 'Expense'])) {
                $balance = $debits - $credits;
            } else {
                $balance = $credits - $debits;
            }

            // Only include accounts with non-zero balances
            if ($balance != 0) {
                $accountData[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'category' => $account->category->name,
                    'balance' => $balance,
                    'formatted_balance' => number_format($balance, 2),
                ];
            }
        }

        return $accountData;
    }

    /**
     * Calculate retained earnings for the period.
     */
    private function calculateRetainedEarnings($fromDate, $toDate, $financialYearId)
    {
        // Get revenue accounts
        $revenueAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Revenue');
        })->with('category')->where('is_active', true)->get();

        // Get expense accounts
        $expenseAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Expense');
        })->with('category')->where('is_active', true)->get();

        $totalRevenue = 0;
        $totalExpenses = 0;

        // Calculate revenue
        foreach ($revenueAccounts as $account) {
            $credits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<=', $toDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'credit')
                ->sum('amount');

            $debits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<=', $toDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'debit')
                ->sum('amount');

            $totalRevenue += ($credits - $debits);
        }

        // Calculate expenses
        foreach ($expenseAccounts as $account) {
            $debits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<=', $toDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) use ($toDate, $financialYearId) {
                    $query->where('status', 'posted')
                          ->where('entry_date', '<=', $toDate)
                          ->where('financial_year_id', $financialYearId);
                })
                ->where('account_id', $account->id)
                ->where('type', 'credit')
                ->sum('amount');

            $totalExpenses += ($debits - $credits);
        }

        // Retained earnings = Revenue - Expenses
        return $totalRevenue - $totalExpenses;
    }


    /**
     * Generate stock summary report.
     */
    private function generateStockSummary($warehouseId = null, $productCategoryId = null, $productId = null)
    {
        // Build the query based on provided filters
        $query = StockBalance::with(['product.category', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        } elseif ($productCategoryId) {
            $query->whereHas('product', function ($q) use ($productCategoryId) {
                $q->where('category_id', $productCategoryId);
            });
        }

        $stockBalances = $query->get();

        // Group by product
        $productSummary = [];
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($stockBalances as $balance) {
            $productId = $balance->product_id;
            $productName = $balance->product->name;
            $productCode = $balance->product->code;
            $categoryName = $balance->product->category->name;
            $warehouseName = $balance->warehouse->name;

            if (!isset($productSummary[$productId])) {
                $productSummary[$productId] = [
                    'product_id' => $productId,
                    'code' => $productCode,
                    'name' => $productName,
                    'category' => $categoryName,
                    'unit' => $balance->product->unit,
                    'total_quantity' => 0,
                    'total_value' => 0,
                    'warehouses' => [],
                ];
            }

            $value = $balance->quantity * $balance->average_cost;

            $productSummary[$productId]['warehouses'][] = [
                'warehouse_id' => $balance->warehouse_id,
                'warehouse_name' => $warehouseName,
                'quantity' => $balance->quantity,
                'average_cost' => $balance->average_cost,
                'value' => $value,
                'formatted_quantity' => number_format($balance->quantity, 2),
                'formatted_average_cost' => number_format($balance->average_cost, 2),
                'formatted_value' => number_format($value, 2),
            ];

            $productSummary[$productId]['total_quantity'] += $balance->quantity;
            $productSummary[$productId]['total_value'] += $value;

            $totalQuantity += $balance->quantity;
            $totalValue += $value;
        }

        // Format totals
        foreach ($productSummary as &$summary) {
            $summary['formatted_total_quantity'] = number_format($summary['total_quantity'], 2);
            $summary['formatted_total_value'] = number_format($summary['total_value'], 2);
        }

        return [
            'products' => array_values($productSummary),
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'formatted_total_quantity' => number_format($totalQuantity, 2),
            'formatted_total_value' => number_format($totalValue, 2),
        ];
    }

    /**
     * Generate stock movement report.
     */
    private function generateStockMovement($warehouseId = null, $productCategoryId = null, $productId = null, $fromDate = null, $toDate = null)
    {
        // Build the query based on provided filters
        $query = StockMovement::with(['product.category', 'warehouse', 'createdBy']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        } elseif ($productCategoryId) {
            $query->whereHas('product', function ($q) use ($productCategoryId) {
                $q->where('category_id', $productCategoryId);
            });
        }

        if ($fromDate) {
            $query->whereDate('transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('transaction_date', '<=', $toDate);
        }

        $stockMovements = $query->orderBy('transaction_date', 'desc')->get();

        // Group by product
        $productMovements = [];

        foreach ($stockMovements as $movement) {
            $productId = $movement->product_id;
            $productName = $movement->product->name;
            $productCode = $movement->product->code;
            $categoryName = $movement->product->category->name;

            if (!isset($productMovements[$productId])) {
                $productMovements[$productId] = [
                    'product_id' => $productId,
                    'code' => $productCode,
                    'name' => $productName,
                    'category' => $categoryName,
                    'unit' => $movement->product->unit,
                    'movements' => [],
                    'total_in' => 0,
                    'total_out' => 0,
                ];
            }

            $quantity = $movement->quantity;
            $direction = $quantity > 0 ? 'in' : 'out';
            $value = abs($quantity) * $movement->unit_price;

            $productMovements[$productId]['movements'][] = [
                'id' => $movement->id,
                'date' => $movement->transaction_date,
                'reference' => $movement->reference_number,
                'type' => $movement->type,
                'warehouse' => $movement->warehouse->name,
                'quantity' => abs($quantity),
                'direction' => $direction,
                'unit_price' => $movement->unit_price,
                'value' => $value,
                'remarks' => $movement->remarks,
                'created_by' => $movement->createdBy->name,
                'formatted_quantity' => number_format(abs($quantity), 2),
                'formatted_unit_price' => number_format($movement->unit_price, 2),
                'formatted_value' => number_format($value, 2),
            ];

            if ($quantity > 0) {
                $productMovements[$productId]['total_in'] += $quantity;
            } else {
                $productMovements[$productId]['total_out'] += abs($quantity);
            }
        }

        // Calculate net movement and format totals
        $totalIn = 0;
        $totalOut = 0;

        foreach ($productMovements as &$movement) {
            $movement['net_movement'] = $movement['total_in'] - $movement['total_out'];
            $movement['formatted_total_in'] = number_format($movement['total_in'], 2);
            $movement['formatted_total_out'] = number_format($movement['total_out'], 2);
            $movement['formatted_net_movement'] = number_format($movement['net_movement'], 2);

            $totalIn += $movement['total_in'];
            $totalOut += $movement['total_out'];
        }

        $totalNet = $totalIn - $totalOut;

        return [
            'products' => array_values($productMovements),
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_net' => $totalNet,
            'formatted_total_in' => number_format($totalIn, 2),
            'formatted_total_out' => number_format($totalOut, 2),
            'formatted_total_net' => number_format($totalNet, 2),
        ];
    }

    /**
     * Generate stock valuation report.
     */
    private function generateStockValuation($warehouseId = null, $productCategoryId = null, $productId = null)
    {
        // Build query for stock balances
        $query = StockBalance::with(['product.category', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        } elseif ($productCategoryId) {
            $query->whereHas('product', function ($q) use ($productCategoryId) {
                $q->where('category_id', $productCategoryId);
            });
        }

        $stockBalances = $query->get();

        // Get products with stock balances
        $products = Product::with('category')
            ->whereIn('id', $stockBalances->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        // Calculate valuation
        $valuationData = [];
        $totalCostValue = 0;
        $totalMarketValue = 0;
        $totalProfitLoss = 0;

        foreach ($stockBalances as $balance) {
            $productId = $balance->product_id;
            $product = $products[$productId];

            if (!isset($valuationData[$productId])) {
                $valuationData[$productId] = [
                    'product_id' => $productId,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'unit' => $product->unit,
                    'purchase_price' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'total_quantity' => 0,
                    'cost_value' => 0,
                    'market_value' => 0,
                    'profit_loss' => 0,
                    'warehouses' => [],
                ];
            }

            $costValue = $balance->quantity * $balance->average_cost;
            $marketValue = $balance->quantity * $product->selling_price;
            $profitLoss = $marketValue - $costValue;

            $valuationData[$productId]['warehouses'][] = [
                'warehouse_id' => $balance->warehouse_id,
                'warehouse_name' => $balance->warehouse->name,
                'quantity' => $balance->quantity,
                'average_cost' => $balance->average_cost,
                'cost_value' => $costValue,
                'market_value' => $marketValue,
                'profit_loss' => $profitLoss,
                'formatted_quantity' => number_format($balance->quantity, 2),
                'formatted_average_cost' => number_format($balance->average_cost, 2),
                'formatted_cost_value' => number_format($costValue, 2),
                'formatted_market_value' => number_format($marketValue, 2),
                'formatted_profit_loss' => number_format($profitLoss, 2),
            ];

            $valuationData[$productId]['total_quantity'] += $balance->quantity;
            $valuationData[$productId]['cost_value'] += $costValue;
            $valuationData[$productId]['market_value'] += $marketValue;
            $valuationData[$productId]['profit_loss'] += $profitLoss;

            $totalCostValue += $costValue;
            $totalMarketValue += $marketValue;
            $totalProfitLoss += $profitLoss;
        }

        // Format totals for each product
        foreach ($valuationData as &$data) {
            $data['formatted_purchase_price'] = number_format($data['purchase_price'], 2);
            $data['formatted_selling_price'] = number_format($data['selling_price'], 2);
            $data['formatted_total_quantity'] = number_format($data['total_quantity'], 2);
            $data['formatted_cost_value'] = number_format($data['cost_value'], 2);
            $data['formatted_market_value'] = number_format($data['market_value'], 2);
            $data['formatted_profit_loss'] = number_format($data['profit_loss'], 2);
            $data['profit_percentage'] = $data['cost_value'] > 0 ?
                ($data['profit_loss'] / $data['cost_value'] * 100) : 0;
            $data['formatted_profit_percentage'] = number_format($data['profit_percentage'], 2) . '%';
        }

        return [
            'products' => array_values($valuationData),
            'total_cost_value' => $totalCostValue,
            'total_market_value' => $totalMarketValue,
            'total_profit_loss' => $totalProfitLoss,
            'formatted_total_cost_value' => number_format($totalCostValue, 2),
            'formatted_total_market_value' => number_format($totalMarketValue, 2),
            'formatted_total_profit_loss' => number_format($totalProfitLoss, 2),
            'total_profit_percentage' => $totalCostValue > 0 ?
                ($totalProfitLoss / $totalCostValue * 100) : 0,
            'formatted_total_profit_percentage' => $totalCostValue > 0 ?
                number_format(($totalProfitLoss / $totalCostValue * 100), 2) . '%' : '0.00%',
        ];
    }

     /**
     * Generate reorder report.
     */
    private function generateReorderReport($warehouseId = null, $productCategoryId = null)
    {
        // Get all products
        $query = Product::with('category')->where('is_active', true);

        if ($productCategoryId) {
            $query->where('category_id', $productCategoryId);
        }

        $products = $query->get();

        // Get stock balances
        $stockQuery = StockBalance::with(['product', 'warehouse']);

        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stockBalances = $stockQuery->get();

        // Group stock balances by product
        $productStocks = [];
        foreach ($stockBalances as $balance) {
            $productId = $balance->product_id;

            if (!isset($productStocks[$productId])) {
                $productStocks[$productId] = [
                    'total_quantity' => 0,
                    'warehouses' => [],
                ];
            }

            $productStocks[$productId]['total_quantity'] += $balance->quantity;
            $productStocks[$productId]['warehouses'][] = [
                'warehouse_id' => $balance->warehouse_id,
                'warehouse_name' => $balance->warehouse->name,
                'quantity' => $balance->quantity,
            ];
        }

        // Calculate reorder status
        $reorderData = [];

        foreach ($products as $product) {
            $currentStock = $productStocks[$product->id]['total_quantity'] ?? 0;
            $reorderLevel = $product->reorder_level;

            // Only include products that need reordering
            if ($currentStock <= $reorderLevel) {
                $reorderData[] = [
                    'product_id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'unit' => $product->unit,
                    'current_stock' => $currentStock,
                    'reorder_level' => $reorderLevel,
                    'shortage' => $reorderLevel - $currentStock,
                    'purchase_price' => $product->purchase_price,
                    'estimated_cost' => ($reorderLevel - $currentStock) * $product->purchase_price,
                    'warehouses' => $productStocks[$product->id]['warehouses'] ?? [],
                    'formatted_current_stock' => number_format($currentStock, 2),
                    'formatted_reorder_level' => number_format($reorderLevel, 2),
                    'formatted_shortage' => number_format($reorderLevel - $currentStock, 2),
                    'formatted_purchase_price' => number_format($product->purchase_price, 2),
                    'formatted_estimated_cost' => number_format(($reorderLevel - $currentStock) * $product->purchase_price, 2),
                ];
            }
        }

        // Sort by shortage (highest first)
        usort($reorderData, function ($a, $b) {
            return $b['shortage'] <=> $a['shortage'];
        });

        // Calculate totals
        $totalShortage = array_sum(array_column($reorderData, 'shortage'));
        $totalEstimatedCost = array_sum(array_column($reorderData, 'estimated_cost'));

        return [
            'products' => $reorderData,
            'total_products' => count($reorderData),
            'total_shortage' => $totalShortage,
            'total_estimated_cost' => $totalEstimatedCost,
            'formatted_total_shortage' => number_format($totalShortage, 2),
            'formatted_total_estimated_cost' => number_format($totalEstimatedCost, 2),
        ];
    }

     /**
     * Generate sales report data.
     */
    private function generateSalesReport(ReportTemplate $template, array $parameters, $companySetting)
    {
        $structure = $template->structure;
        $reportType = $structure['report_type'] ?? 'sales_summary';

        $fromDate = Carbon::parse($parameters['from_date']);
        $toDate = Carbon::parse($parameters['to_date']);
        $customerId = $parameters['customer_id'] ?? null;
        $productId = $parameters['product_id'] ?? null;

        if ($reportType === 'sales_summary') {
            // Generate sales summary report
            $data = $this->generateSalesSummary($fromDate, $toDate, $customerId, $productId);

            return Inertia::render('Reports/SalesSummary', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'sales_by_customer') {
            // Generate sales by customer report
            $data = $this->generateSalesByCustomer($fromDate, $toDate, $productId);

            return Inertia::render('Reports/SalesByCustomer', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'sales_by_product') {
            // Generate sales by product report
            $data = $this->generateSalesByProduct($fromDate, $toDate, $customerId);

            return Inertia::render('Reports/SalesByProduct', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'sales_profit') {
            // Generate sales profit report
            $data = $this->generateSalesProfit($fromDate, $toDate, $customerId, $productId);

            return Inertia::render('Reports/SalesProfit', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);
        }

        return back()->with('error', 'Invalid sales report type.');
    }

    /**
     * Generate sales summary.
     */
    private function generateSalesSummary($fromDate, $toDate, $customerId = null, $productId = null)
    {
        // Get sales invoices
        $invoiceQuery = Invoice::with(['contact', 'salesOrder.items.product'])
            ->where('type', 'sales')
            ->whereIn('status', ['paid', 'partially_paid'])
            ->whereBetween('invoice_date', [$fromDate, $toDate]);

        if ($customerId) {
            $invoiceQuery->where('contact_id', $customerId);
        }

        $invoices = $invoiceQuery->get();

        // Filter by product if specified
        if ($productId) {
            $invoices = $invoices->filter(function ($invoice) use ($productId) {
                if (!$invoice->salesOrder) {
                    return false;
                }

                return $invoice->salesOrder->items->contains('product_id', $productId);
            });
        }

        // Prepare monthly data
        $monthlyData = [];
        $currentDate = $fromDate->copy()->startOfMonth();
        $endDate = $toDate->copy()->endOfMonth();

        while ($currentDate <= $endDate) {
            $month = $currentDate->format('Y-m');
            $monthlyData[$month] = [
                'month' => $currentDate->format('F Y'),
                'invoices' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
            ];

            $currentDate->addMonth();
        }

        // Populate monthly data
        $totalInvoices = 0;
        $totalAmount = 0;
        $totalPaid = 0;
        $totalDue = 0;

        foreach ($invoices as $invoice) {
            $month = Carbon::parse($invoice->invoice_date)->format('Y-m');

            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['invoices']++;
                $monthlyData[$month]['total_amount'] += $invoice->total;
                $monthlyData[$month]['paid_amount'] += $invoice->amount_paid;
                $monthlyData[$month]['due_amount'] += ($invoice->total - $invoice->amount_paid);

                $totalInvoices++;
                $totalAmount += $invoice->total;
                $totalPaid += $invoice->amount_paid;
                $totalDue += ($invoice->total - $invoice->amount_paid);
            }
        }

        // Format monthly data
        foreach ($monthlyData as &$data) {
            $data['formatted_total_amount'] = number_format($data['total_amount'], 2);
            $data['formatted_paid_amount'] = number_format($data['paid_amount'], 2);
            $data['formatted_due_amount'] = number_format($data['due_amount'], 2);
        }

        return [
            'monthly_data' => array_values($monthlyData),
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'formatted_total_amount' => number_format($totalAmount, 2),
            'formatted_total_paid' => number_format($totalPaid, 2),
            'formatted_total_due' => number_format($totalDue, 2),
        ];
    }

     /**
     * Generate sales by customer.
     */
    private function generateSalesByCustomer($fromDate, $toDate, $productId = null)
    {
        // Get sales invoices
        $invoiceQuery = Invoice::with(['contact', 'salesOrder.items.product'])
            ->where('type', 'sales')
            ->whereIn('status', ['paid', 'partially_paid'])
            ->whereBetween('invoice_date', [$fromDate, $toDate]);

        $invoices = $invoiceQuery->get();

        // Filter by product if specified
        if ($productId) {
            $invoices = $invoices->filter(function ($invoice) use ($productId) {
                if (!$invoice->salesOrder) {
                    return false;
                }

                return $invoice->salesOrder->items->contains('product_id', $productId);
            });
        }

        // Group by customer
        $customerData = [];

        foreach ($invoices as $invoice) {
            $customerId = $invoice->contact_id;
            $customerName = $invoice->contact->name;

            if (!isset($customerData[$customerId])) {
                $customerData[$customerId] = [
                    'customer_id' => $customerId,
                    'name' => $customerName,
                    'invoices' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'due_amount' => 0,
                    'invoice_list' => [],
                ];
            }

            $customerData[$customerId]['invoices']++;
            $customerData[$customerId]['total_amount'] += $invoice->total;
            $customerData[$customerId]['paid_amount'] += $invoice->amount_paid;
            $customerData[$customerId]['due_amount'] += ($invoice->total - $invoice->amount_paid);

            $customerData[$customerId]['invoice_list'][] = [
                'id' => $invoice->id,
                'reference' => $invoice->reference_number,
                'date' => $invoice->invoice_date,
                'amount' => $invoice->total,
                'paid' => $invoice->amount_paid,
                'due' => $invoice->total - $invoice->amount_paid,
                'status' => $invoice->status,
            ];
        }

        // Sort by total amount
        uasort($customerData, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        // Format customer data
        foreach ($customerData as &$data) {
            $data['formatted_total_amount'] = number_format($data['total_amount'], 2);
            $data['formatted_paid_amount'] = number_format($data['paid_amount'], 2);
            $data['formatted_due_amount'] = number_format($data['due_amount'], 2);

            foreach ($data['invoice_list'] as &$invoice) {
                $invoice['formatted_amount'] = number_format($invoice['amount'], 2);
                $invoice['formatted_paid'] = number_format($invoice['paid'], 2);
                $invoice['formatted_due'] = number_format($invoice['due'], 2);
            }
        }

        // Calculate totals
        $totalInvoices = array_sum(array_column($customerData, 'invoices'));
        $totalAmount = array_sum(array_column($customerData, 'total_amount'));
        $totalPaid = array_sum(array_column($customerData, 'paid_amount'));
        $totalDue = array_sum(array_column($customerData, 'due_amount'));

        return [
            'customers' => array_values($customerData),
            'total_customers' => count($customerData),
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'formatted_total_amount' => number_format($totalAmount, 2),
            'formatted_total_paid' => number_format($totalPaid, 2),
            'formatted_total_due' => number_format($totalDue, 2),
        ];
    }

    /**
     * Generate sales by product.
     */
    private function generateSalesByProduct($fromDate, $toDate, $customerId = null)
    {
        // Get sales orders with items
        $salesOrderQuery = SalesOrder::with(['customer', 'items.product'])
            ->whereIn('status', ['confirmed', 'delivered'])
            ->whereBetween('order_date', [$fromDate, $toDate]);

        if ($customerId) {
            $salesOrderQuery->where('customer_id', $customerId);
        }

        $salesOrders = $salesOrderQuery->get();

        // Group by product
        $productData = [];

        foreach ($salesOrders as $order) {
            foreach ($order->items as $item) {
                $productId = $item->product_id;
                $productName = $item->product->name;
                $productCode = $item->product->code;
                $categoryName = $item->product->category->name;

                if (!isset($productData[$productId])) {
                    $productData[$productId] = [
                        'product_id' => $productId,
                        'code' => $productCode,
                        'name' => $productName,
                        'category' => $categoryName,
                        'unit' => $item->product->unit,
                        'total_quantity' => 0,
                        'total_amount' => 0,
                        'orders' => [],
                    ];
                }

                $productData[$productId]['total_quantity'] += $item->quantity;
                $productData[$productId]['total_amount'] += $item->total;

                $productData[$productId]['orders'][] = [
                    'order_id' => $order->id,
                    'reference' => $order->reference_number,
                    'date' => $order->order_date,
                    'customer' => $order->customer->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'total' => $item->total,
                ];
            }
        }

        // Sort by total amount
        uasort($productData, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        // Format product data
        foreach ($productData as &$data) {
            $data['formatted_total_quantity'] = number_format($data['total_quantity'], 2);
            $data['formatted_total_amount'] = number_format($data['total_amount'], 2);
            $data['average_price'] = $data['total_quantity'] > 0 ?
                ($data['total_amount'] / $data['total_quantity']) : 0;
            $data['formatted_average_price'] = number_format($data['average_price'], 2);

            foreach ($data['orders'] as &$order) {
                $order['formatted_quantity'] = number_format($order['quantity'], 2);
                $order['formatted_unit_price'] = number_format($order['unit_price'], 2);
                $order['formatted_discount'] = number_format($order['discount'], 2);
                $order['formatted_total'] = number_format($order['total'], 2);
            }
        }

        // Calculate totals
        $totalQuantity = array_sum(array_column($productData, 'total_quantity'));
        $totalAmount = array_sum(array_column($productData, 'total_amount'));

        return [
            'products' => array_values($productData),
            'total_products' => count($productData),
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'formatted_total_quantity' => number_format($totalQuantity, 2),
            'formatted_total_amount' => number_format($totalAmount, 2),
        ];
    }

    /**
     * Generate sales profit report.
     */
    private function generateSalesProfit($fromDate, $toDate, $customerId = null, $productId = null)
    {
        // Get sales orders with items
        $salesOrderQuery = SalesOrder::with(['customer', 'items.product'])
            ->whereIn('status', ['confirmed', 'delivered'])
            ->whereBetween('order_date', [$fromDate, $toDate]);

        if ($customerId) {
            $salesOrderQuery->where('customer_id', $customerId);
        }

        $salesOrders = $salesOrderQuery->get();

        // Filter by product if specified
        if ($productId) {
            $salesOrders = $salesOrders->map(function ($order) use ($productId) {
                $filteredItems = $order->items->filter(function ($item) use ($productId) {
                    return $item->product_id == $productId;
                });

                if ($filteredItems->isEmpty()) {
                    return null;
                }

                $order->items = $filteredItems;
                return $order;
            })->filter();
        }

        // Calculate profit by sale
        $salesData = [];
        $totalSales = 0;
        $totalCost = 0;
        $totalProfit = 0;

        foreach ($salesOrders as $order) {
            $orderSales = 0;
            $orderCost = 0;
            $orderProfit = 0;
            $orderItems = [];

            foreach ($order->items as $item) {
                $productCost = $item->product->purchase_price * $item->quantity;
                $productSales = $item->total;
                $productProfit = $productSales - $productCost;

                $orderSales += $productSales;
                $orderCost += $productCost;
                $orderProfit += $productProfit;

                $orderItems[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_code' => $item->product->code,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->product->purchase_price,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'total_cost' => $productCost,
                    'total_sales' => $productSales,
                    'profit' => $productProfit,
                    'margin' => $productCost > 0 ? ($productProfit / $productCost * 100) : 0,
                    'formatted_quantity' => number_format($item->quantity, 2),
                    'formatted_unit_cost' => number_format($item->product->purchase_price, 2),
                    'formatted_unit_price' => number_format($item->unit_price, 2),
                    'formatted_discount' => number_format($item->discount, 2),
                    'formatted_total_cost' => number_format($productCost, 2),
                    'formatted_total_sales' => number_format($productSales, 2),
                    'formatted_profit' => number_format($productProfit, 2),
                    'formatted_margin' => number_format($productCost > 0 ? ($productProfit / $productCost * 100) : 0, 2) . '%',
                ];
            }

            $salesData[] = [
                'order_id' => $order->id,
                'reference' => $order->reference_number,
                'date' => $order->order_date,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer->name,
                'total_sales' => $orderSales,
                'total_cost' => $orderCost,
                'profit' => $orderProfit,
                'margin' => $orderCost > 0 ? ($orderProfit / $orderCost * 100) : 0,
                'formatted_total_sales' => number_format($orderSales, 2),
                'formatted_total_cost' => number_format($orderCost, 2),
                'formatted_profit' => number_format($orderProfit, 2),
                'formatted_margin' => number_format($orderCost > 0 ? ($orderProfit / $orderCost * 100) : 0, 2) . '%',
                'items' => $orderItems,
            ];

            $totalSales += $orderSales;
            $totalCost += $orderCost;
            $totalProfit += $orderProfit;
        }

        // Sort by profit (highest first)
        usort($salesData, function ($a, $b) {
            return $b['profit'] <=> $a['profit'];
        });

        return [
            'sales' => $salesData,
            'total_orders' => count($salesData),
            'total_sales' => $totalSales,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'total_margin' => $totalCost > 0 ? ($totalProfit / $totalCost * 100) : 0,
            'formatted_total_sales' => number_format($totalSales, 2),
            'formatted_total_cost' => number_format($totalCost, 2),
            'formatted_total_profit' => number_format($totalProfit, 2),
            'formatted_total_margin' => number_format($totalCost > 0 ? ($totalProfit / $totalCost * 100) : 0, 2) . '%',
        ];
    }

    /**
     * Generate purchase report data.
     */
    private function generatePurchaseReport(ReportTemplate $template, array $parameters, $companySetting)
    {
        $structure = $template->structure;
        $reportType = $structure['report_type'] ?? 'purchase_summary';

        $fromDate = Carbon::parse($parameters['from_date']);
        $toDate = Carbon::parse($parameters['to_date']);
        $supplierId = $parameters['supplier_id'] ?? null;
        $productId = $parameters['product_id'] ?? null;

        if ($reportType === 'purchase_summary') {
            // Generate purchase summary report
            $data = $this->generatePurchaseSummary($fromDate, $toDate, $supplierId, $productId);

            return Inertia::render('Reports/PurchaseSummary', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'purchase_by_supplier') {
            // Generate purchase by supplier report
            $data = $this->generatePurchaseBySupplier($fromDate, $toDate, $productId);

            return Inertia::render('Reports/PurchaseBySupplier', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'purchase_by_product') {
            // Generate purchase by product report
            $data = $this->generatePurchaseByProduct($fromDate, $toDate, $supplierId);

            return Inertia::render('Reports/PurchaseByProduct', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);
        }

        return back()->with('error', 'Invalid purchase report type.');
    }

    /**
     * Generate purchase summary.
     */
    private function generatePurchaseSummary($fromDate, $toDate, $supplierId = null, $productId = null)
    {
        // Similar structure to salesSummary but for purchases
        // Implementation logic for purchase summary report

        // Get purchase invoices
        $invoiceQuery = Invoice::with(['contact', 'purchaseOrder.items.product'])
            ->where('type', 'purchase')
            ->whereIn('status', ['paid', 'partially_paid', 'unpaid'])
            ->whereBetween('invoice_date', [$fromDate, $toDate]);

        if ($supplierId) {
            $invoiceQuery->where('contact_id', $supplierId);
        }

        $invoices = $invoiceQuery->get();

        // Filter by product if specified
        if ($productId) {
            $invoices = $invoices->filter(function ($invoice) use ($productId) {
                if (!$invoice->purchaseOrder) {
                    return false;
                }

                return $invoice->purchaseOrder->items->contains('product_id', $productId);
            });
        }

        // Prepare monthly data
        $monthlyData = [];
        $currentDate = $fromDate->copy()->startOfMonth();
        $endDate = $toDate->copy()->endOfMonth();

        while ($currentDate <= $endDate) {
            $month = $currentDate->format('Y-m');
            $monthlyData[$month] = [
                'month' => $currentDate->format('F Y'),
                'invoices' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
            ];

            $currentDate->addMonth();
        }

        // Populate monthly data
        $totalInvoices = 0;
        $totalAmount = 0;
        $totalPaid = 0;
        $totalDue = 0;

        foreach ($invoices as $invoice) {
            $month = Carbon::parse($invoice->invoice_date)->format('Y-m');

            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['invoices']++;
                $monthlyData[$month]['total_amount'] += $invoice->total;
                $monthlyData[$month]['paid_amount'] += $invoice->amount_paid;
                $monthlyData[$month]['due_amount'] += ($invoice->total - $invoice->amount_paid);

                $totalInvoices++;
                $totalAmount += $invoice->total;
                $totalPaid += $invoice->amount_paid;
                $totalDue += ($invoice->total - $invoice->amount_paid);
            }
        }

        // Format monthly data
        foreach ($monthlyData as &$data) {
            $data['formatted_total_amount'] = number_format($data['total_amount'], 2);
            $data['formatted_paid_amount'] = number_format($data['paid_amount'], 2);
            $data['formatted_due_amount'] = number_format($data['due_amount'], 2);
        }

        return [
            'monthly_data' => array_values($monthlyData),
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'formatted_total_amount' => number_format($totalAmount, 2),
            'formatted_total_paid' => number_format($totalPaid, 2),
            'formatted_total_due' => number_format($totalDue, 2),
        ];
    }

    /**
     * Generate purchase by supplier.
     */
    private function generatePurchaseBySupplier($fromDate, $toDate, $productId = null)
{
    // Get purchase invoices
    $invoiceQuery = Invoice::with(['contact', 'purchaseOrder.items.product'])
        ->where('type', 'purchase')
        ->whereIn('status', ['paid', 'partially_paid', 'unpaid'])
        ->whereBetween('invoice_date', [$fromDate, $toDate]);

    $invoices = $invoiceQuery->get();

    // Filter by product if specified
    if ($productId) {
        $invoices = $invoices->filter(function ($invoice) use ($productId) {
            if (!$invoice->purchaseOrder) {
                return false;
            }

            return $invoice->purchaseOrder->items->contains('product_id', $productId);
        });
    }

    // Group by supplier
    $supplierData = [];

    foreach ($invoices as $invoice) {
        $supplierId = $invoice->contact_id;
        $supplierName = $invoice->contact->name;

        if (!isset($supplierData[$supplierId])) {
            $supplierData[$supplierId] = [
                'supplier_id' => $supplierId,
                'name' => $supplierName,
                'invoices' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'invoice_list' => [],
            ];
        }

        $supplierData[$supplierId]['invoices']++;
        $supplierData[$supplierId]['total_amount'] += $invoice->total;
        $supplierData[$supplierId]['paid_amount'] += $invoice->paid_amount;
        $supplierData[$supplierId]['due_amount'] += $invoice->due_amount;
        $supplierData[$supplierId]['invoice_list'][] = $invoice;
    }

    return $supplierData;
}




    /**
     * Generate inventory report data.
     */
    private function generateInventoryReport(ReportTemplate $template, array $parameters, $companySetting)
    {
        $structure = $template->structure;
        $reportType = $structure['report_type'] ?? 'stock_summary';

        $warehouseId = $parameters['warehouse_id'] ?? null;
        $productCategoryId = $parameters['product_category_id'] ?? null;
        $productId = $parameters['product_id'] ?? null;
        $fromDate = $parameters['from_date'] ? Carbon::parse($parameters['from_date']) : null;
        $toDate = $parameters['to_date'] ? Carbon::parse($parameters['to_date']) : null;

        if ($reportType === 'stock_summary') {
            // Generate stock summary report
            $data = $this->generateStockSummary($warehouseId, $productCategoryId, $productId);

            return Inertia::render('Reports/StockSummary', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'stock_movement') {
            // Generate stock movement report
            $data = $this->generateStockMovement($warehouseId, $productCategoryId, $productId, $fromDate, $toDate);

            return Inertia::render('Reports/StockMovement', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'stock_valuation') {
            // Generate stock valuation report
            $data = $this->generateStockValuation($warehouseId, $productCategoryId, $productId);

            return Inertia::render('Reports/StockValuation', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);

        } elseif ($reportType === 'reorder_report') {
            // Generate reorder report
            $data = $this->generateReorderReport($warehouseId, $productCategoryId);

            return Inertia::render('Reports/ReorderReport', [
                'template' => $template,
                'parameters' => $parameters,
                'data' => $data,
                'companySetting' => $companySetting,
            ]);
        }

        return back()->with('error', 'Invalid inventory report type.');
    }

    /**
     * Generate purchase by product.
     */
    private function generatePurchaseByProduct($fromDate, $toDate, $supplierId = null)
    {
        // Get purchase orders with items
        $purchaseOrderQuery = PurchaseOrder::with(['supplier', 'items.product'])
            ->whereIn('status', ['confirmed', 'received'])
            ->whereBetween('order_date', [$fromDate, $toDate]);

        if ($supplierId) {
            $purchaseOrderQuery->where('supplier_id', $supplierId);
        }

        $purchaseOrders = $purchaseOrderQuery->get();

        // Group by product
        $productData = [];

        foreach ($purchaseOrders as $order) {
            foreach ($order->items as $item) {
                $productId = $item->product_id;
                $productName = $item->product->name;
                $productCode = $item->product->code;
                $categoryName = $item->product->category->name;

                if (!isset($productData[$productId])) {
                    $productData[$productId] = [
                        'product_id' => $productId,
                        'code' => $productCode,
                        'name' => $productName,
                        'category' => $categoryName,
                        'unit' => $item->product->unit,
                        'total_quantity' => 0,
                        'total_amount' => 0,
                        'orders' => [],
                    ];
                }

                $productData[$productId]['total_quantity'] += $item->quantity;
                $productData[$productId]['total_amount'] += $item->total;

                $productData[$productId]['orders'][] = [
                    'order_id' => $order->id,
                    'reference' => $order->reference_number,
                    'date' => $order->order_date,
                    'supplier' => $order->supplier->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'total' => $item->total,
                ];
            }
        }

        // Sort by total amount
        uasort($productData, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        // Format product data
        foreach ($productData as &$data) {
            $data['formatted_total_quantity'] = number_format($data['total_quantity'], 2);
            $data['formatted_total_amount'] = number_format($data['total_amount'], 2);
            $data['average_price'] = $data['total_quantity'] > 0 ?
                ($data['total_amount'] / $data['total_quantity']) : 0;
            $data['formatted_average_price'] = number_format($data['average_price'], 2);

            foreach ($data['orders'] as &$order) {
                $order['formatted_quantity'] = number_format($order['quantity'], 2);
                $order['formatted_unit_price'] = number_format($order['unit_price'], 2);
                $order['formatted_discount'] = number_format($order['discount'], 2);
                $order['formatted_total'] = number_format($order['total'], 2);
            }
        }

        // Calculate totals
        $totalQuantity = array_sum(array_column($productData, 'total_quantity'));
        $totalAmount = array_sum(array_column($productData, 'total_amount'));

        return [
            'products' => array_values($productData),
            'total_products' => count($productData),
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'formatted_total_quantity' => number_format($totalQuantity, 2),
            'formatted_total_amount' => number_format($totalAmount, 2),
        ];
    }

    /**
     * Generate payroll report data.
     */
    private function generatePayrollReport(ReportTemplate $template, array $parameters, $companySetting)
    {
        // Get parameters
        $fromDate = $parameters['from_date'] ? Carbon::parse($parameters['from_date']) : null;
        $toDate = $parameters['to_date'] ? Carbon::parse($parameters['to_date']) : null;
        $departmentId = $parameters['department_id'] ?? null;
        $employeeId = $parameters['employee_id'] ?? null;

        // Build query for salary slips
        $query = \App\Models\SalarySlip::with(['employee.user', 'employee.department', 'employee.designation', 'details']);

        if ($fromDate && $toDate) {
            $query->whereBetween('month_year', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $query->where('month_year', '>=', $fromDate);
        } elseif ($toDate) {
            $query->where('month_year', '<=', $toDate);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        } elseif ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $salarySlips = $query->orderBy('month_year', 'desc')->get();

        // Group by month
        $monthlyData = [];

        if ($fromDate && $toDate) {
            $currentDate = $fromDate->copy()->startOfMonth();
            $endDate = $toDate->copy()->endOfMonth();

            while ($currentDate <= $endDate) {
                $month = $currentDate->format('Y-m');
                $monthlyData[$month] = [
                    'month' => $currentDate->format('F Y'),
                    'basic_salary' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'employee_count' => 0,
                    'employees' => [],
                ];

                $currentDate->addMonth();
            }
        }

        // Group by department
        $departmentData = [];

        // Populate data
        foreach ($salarySlips as $slip) {
            $month = Carbon::parse($slip->month_year)->format('Y-m');
            $departmentId = $slip->employee->department_id;
            $departmentName = $slip->employee->department->name;

            // Add to monthly data
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['basic_salary'] += $slip->basic_salary;
                $monthlyData[$month]['total_allowances'] += $slip->total_allowances;
                $monthlyData[$month]['total_deductions'] += $slip->total_deductions;
                $monthlyData[$month]['net_salary'] += $slip->net_salary;

                if (!in_array($slip->employee_id, $monthlyData[$month]['employees'])) {
                    $monthlyData[$month]['employees'][] = $slip->employee_id;
                    $monthlyData[$month]['employee_count']++;
                }
            }

            // Add to department data
            if (!isset($departmentData[$departmentId])) {
                $departmentData[$departmentId] = [
                    'department_id' => $departmentId,
                    'name' => $departmentName,
                    'basic_salary' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'employee_count' => 0,
                    'employees' => [],
                ];
            }

            $departmentData[$departmentId]['basic_salary'] += $slip->basic_salary;
            $departmentData[$departmentId]['total_allowances'] += $slip->total_allowances;
            $departmentData[$departmentId]['total_deductions'] += $slip->total_deductions;
            $departmentData[$departmentId]['net_salary'] += $slip->net_salary;

            if (!in_array($slip->employee_id, $departmentData[$departmentId]['employees'])) {
                $departmentData[$departmentId]['employees'][] = $slip->employee_id;
                $departmentData[$departmentId]['employee_count']++;
            }
        }

        // Format monthly data
        foreach ($monthlyData as &$data) {
            unset($data['employees']);
            $data['formatted_basic_salary'] = number_format($data['basic_salary'], 2);
            $data['formatted_total_allowances'] = number_format($data['total_allowances'], 2);
            $data['formatted_total_deductions'] = number_format($data['total_deductions'], 2);
            $data['formatted_net_salary'] = number_format($data['net_salary'], 2);
        }

        // Format department data
        foreach ($departmentData as &$data) {
            unset($data['employees']);
            $data['formatted_basic_salary'] = number_format($data['basic_salary'], 2);
            $data['formatted_total_allowances'] = number_format($data['total_allowances'], 2);
            $data['formatted_total_deductions'] = number_format($data['total_deductions'], 2);
            $data['formatted_net_salary'] = number_format($data['net_salary'], 2);
            $data['average_salary'] = $data['employee_count'] > 0 ?
                ($data['net_salary'] / $data['employee_count']) : 0;
            $data['formatted_average_salary'] = number_format($data['average_salary'], 2);
        }

        // Calculate totals
        $totalBasicSalary = array_sum(array_column($departmentData, 'basic_salary'));
        $totalAllowances = array_sum(array_column($departmentData, 'total_allowances'));
        $totalDeductions = array_sum(array_column($departmentData, 'total_deductions'));
        $totalNetSalary = array_sum(array_column($departmentData, 'net_salary'));
        $totalEmployees = count(array_unique(array_merge(...array_column($departmentData, 'employees'))));

        // Prepare period string
        $periodText = '';
        if ($fromDate && $toDate) {
            $periodText = $fromDate->format('F Y') . ' to ' . $toDate->format('F Y');
        } elseif ($fromDate) {
            $periodText = 'From ' . $fromDate->format('F Y');
        } elseif ($toDate) {
            $periodText = 'Up to ' . $toDate->format('F Y');
        } else {
            $periodText = 'All Time';
        }

        return Inertia::render('Reports/PayrollReport', [
            'template' => $template,
            'parameters' => $parameters,
            'data' => [
                'monthly_data' => array_values($monthlyData),
                'department_data' => array_values($departmentData),
                'total_basic_salary' => $totalBasicSalary,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'total_net_salary' => $totalNetSalary,
                'total_employees' => $totalEmployees,
                'formatted_total_basic_salary' => number_format($totalBasicSalary, 2),
                'formatted_total_allowances' => number_format($totalAllowances, 2),
                'formatted_total_deductions' => number_format($totalDeductions, 2),
                'formatted_total_net_salary' => number_format($totalNetSalary, 2),
                'period_text' => $periodText,
            ],
            'companySetting' => $companySetting,
        ]);
    }
}



