import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    BarChart2,
    DollarSign,
    ShoppingCart,
    TrendingUp,
    AlertCircle,
    Users,
    Clock,
    FileText,
    AlertTriangle
} from 'lucide-react';
import {
    FinancialYear,
    CompanySetting,
    JournalEntry,
    Product,
    User
} from '@/types';

interface DashboardProps {
    company: CompanySetting;
    financialYear: FinancialYear;
    metrics: {
        salesThisMonth: number;
        purchasesThisMonth: number;
        outstandingReceivables: number;
        outstandingPayables: number;
        assetTotal?: number;
        liabilityTotal?: number;
        equityTotal?: number;
        revenueTotal?: number;
        expenseTotal?: number;
        profitLoss?: number;
    };
    recentTransactions: JournalEntry[];
    lowStockProducts: Product[];
    salesChartData: Array<{
        month: string;
        total: number;
    }>;
    roleSpecificData: {
        totalUsers?: number;
        totalContacts?: number;
        overdueTasks?: any[];
        unpostedJournalEntries?: number;
        overdueInvoices?: number;
        pendingPurchaseOrders?: number;
        pendingSalesOrders?: number;
        pendingLeaveApplications?: number;
        assignedTasks?: any[];
    };
    userRole: 'admin' | 'accountant' | 'manager' | 'user';
}

export default function Dashboard({
    company,
    financialYear,
    metrics,
    recentTransactions,
    lowStockProducts,
    salesChartData,
    roleSpecificData,
    userRole
}: DashboardProps) {
    const formatCurrency = (amount?: number) => {
        if (typeof amount !== 'number') return `${company.currency_symbol} 0.00`;
        return `${company.currency_symbol} ${amount.toLocaleString('en-US')}`;
    };


    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    };

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard - Tally Software" />

            {/* Financial Year Info */}
            <div className="bg-white shadow rounded-lg p-4 mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Active Financial Year: {financialYear.name}</h2>
                        <p className="text-sm text-gray-500">
                            {formatDate(financialYear.start_date)} to {formatDate(financialYear.end_date)}
                        </p>
                    </div>
                    {userRole === 'admin' || userRole === 'accountant' ? (
                        <Link
                            href={route('financial-years.index')}
                            className="text-sm text-blue-600 hover:text-blue-800"
                        >
                            Change Financial Year
                        </Link>
                    ) : null}
                </div>
            </div>

            {/* Metrics Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                {/* Sales This Month */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0 bg-green-100 rounded-md p-3">
                                <DollarSign className="h-6 w-6 text-green-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">Sales This Month</dt>
                                    <dd>
                                        <div className="text-lg font-medium text-gray-900">
                                            {formatCurrency(metrics.salesThisMonth)}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('reports.sales')} className="font-medium text-blue-600 hover:text-blue-900">
                                View Details
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Purchases This Month */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                <ShoppingCart className="h-6 w-6 text-blue-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">Purchases This Month</dt>
                                    <dd>
                                        <div className="text-lg font-medium text-gray-900">
                                            {formatCurrency(metrics.purchasesThisMonth)}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('reports.purchases')} className="font-medium text-blue-600 hover:text-blue-900">
                                View Details
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Profit/Loss */}
                {metrics.profitLoss !== undefined && (
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0 bg-purple-100 rounded-md p-3">
                                    <TrendingUp className="h-6 w-6 text-purple-600" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Profit/Loss</dt>
                                        <dd>
                                            <div className={`text-lg font-medium ${metrics.profitLoss >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {formatCurrency(metrics.profitLoss)}
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div className="bg-gray-50 px-5 py-3">
                            <div className="text-sm">
                                <Link href={route('reports.income-statement')} className="font-medium text-blue-600 hover:text-blue-900">
                                    View Income Statement
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                {/* Outstanding Receivables */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                                <DollarSign className="h-6 w-6 text-yellow-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">Outstanding Receivables</dt>
                                    <dd>
                                        <div className="text-lg font-medium text-gray-900">
                                            {formatCurrency(metrics.outstandingReceivables)}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('reports.customer')} className="font-medium text-blue-600 hover:text-blue-900">
                                View Details
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Outstanding Payables */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0 bg-red-100 rounded-md p-3">
                                <DollarSign className="h-6 w-6 text-red-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">Outstanding Payables</dt>
                                    <dd>
                                        <div className="text-lg font-medium text-gray-900">
                                            {formatCurrency(metrics.outstandingPayables)}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('reports.supplier')} className="font-medium text-blue-600 hover:text-blue-900">
                                View Details
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Role-specific cards */}
                {userRole === 'admin' && roleSpecificData.totalUsers && (
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0 bg-indigo-100 rounded-md p-3">
                                    <Users className="h-6 w-6 text-indigo-600" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                        <dd>
                                            <div className="text-lg font-medium text-gray-900">
                                                {roleSpecificData.totalUsers}
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div className="bg-gray-50 px-5 py-3">
                            <div className="text-sm">
                                <Link href={route('admin.users.index')} className="font-medium text-blue-600 hover:text-blue-900">
                                    Manage Users
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                {userRole === 'accountant' && roleSpecificData.unpostedJournalEntries !== undefined && (
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0 bg-orange-100 rounded-md p-3">
                                    <FileText className="h-6 w-6 text-orange-600" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Unposted Journal Entries</dt>
                                        <dd>
                                            <div className="text-lg font-medium text-gray-900">
                                                {roleSpecificData.unpostedJournalEntries}
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div className="bg-gray-50 px-5 py-3">
                            <div className="text-sm">
                                <Link href={route('journal-entries.index')} className="font-medium text-blue-600 hover:text-blue-900">
                                    Manage Journal Entries
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                {userRole === 'manager' && roleSpecificData.pendingLeaveApplications !== undefined && (
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0 bg-teal-100 rounded-md p-3">
                                    <Clock className="h-6 w-6 text-teal-600" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Pending Leave Applications</dt>
                                        <dd>
                                            <div className="text-lg font-medium text-gray-900">
                                                {roleSpecificData.pendingLeaveApplications}
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div className="bg-gray-50 px-5 py-3">
                            <div className="text-sm">
                                <Link href={route('leaves.index')} className="font-medium text-blue-600 hover:text-blue-900">
                                    Manage Leave Applications
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Main Content Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Recent Transactions */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 className="text-lg leading-6 font-medium text-gray-900">Recent Transactions</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Reference
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {recentTransactions.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-4 text-center text-gray-500">
                                            No recent transactions found
                                        </td>
                                    </tr>
                                ) : (
                                    recentTransactions.map((transaction) => (
                                        <tr key={transaction.id}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <Link
                                                    href={route('journal-entries.show', transaction.id)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    {transaction.reference_number}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(transaction.entry_date)}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                {transaction.narration}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                                {formatCurrency(transaction.total_debit)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('journal-entries.index')} className="font-medium text-blue-600 hover:text-blue-900">
                                View All Transactions
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Low Stock Products */}
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 className="text-lg leading-6 font-medium text-gray-900">Low Stock Products</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Code
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current Stock
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Reorder Level
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {lowStockProducts.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                                            No low stock products found
                                        </td>
                                    </tr>
                                ) : (
                                    lowStockProducts.map((product) => (
                                        <tr key={product.id}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <Link
                                                    href={route('products.show', product.id)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    {product.name}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {product.code}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {product.category.name}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                                <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    {product.stock_balance} {product.unit}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                                {product.reorder_level} {product.unit}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                        <div className="text-sm">
                            <Link href={route('products.index')} className="font-medium text-blue-600 hover:text-blue-900">
                                View All Products
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
