<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index()
    {
        // Get current financial year
        $financialYear = \App\Models\FinancialYear::getActive();

        if (!$financialYear) {
            return Inertia::render('Dashboard/SetupRequired', [
                'message' => 'আপনাকে প্রথমে একটি সক্রিয় অর্থবছর সেট করতে হবে।',
                'setupUrl' => route('financial-years.index')
            ]);
        }

        // Get company settings
        $companySettings = \App\Models\CompanySetting::getDefault();

        // Calculate financial metrics
        $today = Carbon::today();
        $startOfMonth = Carbon::today()->startOfMonth();
        $endOfMonth = Carbon::today()->endOfMonth();

        // Total sales this month
        $salesThisMonth = Invoice::where('type', 'sales')
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        // Total purchases this month
        $purchasesThisMonth = Invoice::where('type', 'purchase')
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        // Outstanding receivables
        $outstandingReceivables = Invoice::where('type', 'sales')
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum(\DB::raw('total - amount_paid'));

        // Outstanding payables
        $outstandingPayables = Invoice::where('type', 'purchase')
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum(\DB::raw('total - amount_paid'));

        // Recent transactions
        $recentTransactions = JournalEntry::with(['createdBy', 'items.account'])
            ->where('status', 'posted')
            ->orderBy('entry_date', 'desc')
            ->limit(5)
            ->get();

        // Low stock products
        $lowStockProducts = Product::with('category')
            ->whereHas('stockBalances', function ($query) {
                $query->whereRaw('quantity <= products.reorder_level AND quantity > 0');
            })
            ->limit(5)
            ->get();

        // Monthly sales chart data (for the last 6 months)
        $salesChartData = $this->getMonthlySalesData(6);

        // Role-specific metrics and data
        $roleSpecificData = $this->getRoleSpecificData(Auth::user()->role);

        // Get totals by account type (only for admin and accountant)
        $accountingMetrics = [];
        if (in_array(Auth::user()->role, ['admin', 'accountant'])) {
            $accountingMetrics = $this->getAccountingMetrics();
        }

        return Inertia::render('Dashboard/Index', [
            'company' => $companySettings,
            'financialYear' => $financialYear,
            'metrics' => [
                'salesThisMonth' => $salesThisMonth,
                'purchasesThisMonth' => $purchasesThisMonth,
                'outstandingReceivables' => $outstandingReceivables,
                'outstandingPayables' => $outstandingPayables,
            ] + $accountingMetrics,
            'recentTransactions' => $recentTransactions,
            'lowStockProducts' => $lowStockProducts,
            'salesChartData' => $salesChartData,
            'roleSpecificData' => $roleSpecificData,
            'userRole' => Auth::user()->role,
        ]);
    }

    /**
     * Get monthly sales data for the chart.
     *
     * @param int $months
     * @return array
     */
    private function getMonthlySalesData($months = 6)
    {
        $data = [];
        $today = Carbon::today();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $today->copy()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            $sales = Invoice::where('type', 'sales')
                ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
                ->where('status', '!=', 'cancelled')
                ->sum('total');

            $data[] = [
                'month' => $month->format('F'),
                'total' => $sales,
            ];
        }

        return $data;
    }

    /**
     * Get role-specific data for the dashboard.
     *
     * @param string $role
     * @return array
     */
    private function getRoleSpecificData($role)
    {
        $data = [];

        switch ($role) {
            case 'admin':
                // Add any admin-specific dashboard data here
                $data['totalUsers'] = User::count();
                $data['totalContacts'] = Contact::count();
                $data['overdueTasks'] = $this->getOverdueTasks();
                break;

            case 'accountant':
                // Add accountant-specific dashboard data
                $data['unpostedJournalEntries'] = JournalEntry::where('status', 'draft')->count();
                $data['overdueInvoices'] = Invoice::whereNotIn('status', ['paid', 'cancelled'])
                    ->where('due_date', '<', now())
                    ->count();
                break;

            case 'manager':
                // Add manager-specific dashboard data
                $data['pendingPurchaseOrders'] = \App\Models\PurchaseOrder::where('status', 'draft')->count();
                $data['pendingSalesOrders'] = \App\Models\SalesOrder::where('status', 'draft')->count();
                $data['pendingLeaveApplications'] = \App\Models\LeaveApplication::where('status', 'pending')->count();
                break;

            case 'user':
                // Add regular user-specific dashboard data
                $data['assignedTasks'] = $this->getAssignedTasks();
                break;
        }

        return $data;
    }

    /**
     * Get accounting metrics.
     *
     * @return array
     */
    private function getAccountingMetrics()
    {
        $assetTotal = $this->getTotalByAccountType('Asset');
        $liabilityTotal = $this->getTotalByAccountType('Liability');
        $equityTotal = $this->getTotalByAccountType('Equity');
        $revenueTotal = $this->getTotalByAccountType('Revenue');
        $expenseTotal = $this->getTotalByAccountType('Expense');

        // Calculate profit/loss
        $profitLoss = $revenueTotal - $expenseTotal;

        return [
            'assetTotal' => $assetTotal,
            'liabilityTotal' => $liabilityTotal,
            'equityTotal' => $equityTotal,
            'revenueTotal' => $revenueTotal,
            'expenseTotal' => $expenseTotal,
            'profitLoss' => $profitLoss,
        ];
    }

    /**
     * Calculate total by account type.
     *
     * @param string $type
     * @return float
     */
    private function getTotalByAccountType($type)
    {
        $accounts = ChartOfAccount::byType($type)->get();
        $total = 0;

        foreach ($accounts as $account) {
            $total += $account->getBalance();
        }

        return $total;
    }

    /**
     * Get overdue tasks (placeholder function).
     *
     * @return array
     */
    private function getOverdueTasks()
    {
        // This is a placeholder. Implement task tracking if needed.
        return [];
    }

    /**
     * Get assigned tasks (placeholder function).
     *
     * @return array
     */
    private function getAssignedTasks()
    {
        // This is a placeholder. Implement task tracking if needed.
        return [];
    }
}
