import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Plus,
    Eye,
    Edit2,
    Trash2,
    AlertTriangle,
    Search,
    RefreshCw,
    ExternalLink,
    ArrowUpDown
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankAccountIndexProps } from '@/types';
import BusinessSelector from '@/components/BusinessSelector';

// Extend interface to include business information
interface ExtendedBankAccountIndexProps extends BankAccountIndexProps {
    businesses: any[];
    activeBusiness: any;
}

export default function BankAccountsIndex({
    bankAccounts,
    totalBalance,
    formattedTotalBalance,
    filters,
    companySetting,
    businesses,
    activeBusiness
}: ExtendedBankAccountIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [bankNameFilter, setBankNameFilter] = useState(filters.bank_name || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [accountToDelete, setAccountToDelete] = useState<number | null>(null);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('bank-accounts.index'),
            { search: searchQuery, bank_name: bankNameFilter, status: statusFilter },
            { preserveState: true }
        );
    };

    const resetFilters = () => {
        setSearchQuery('');
        setBankNameFilter('');
        setStatusFilter('');
        router.get(route('bank-accounts.index'), {}, { preserveState: true });
    };

    const confirmDelete = (id: number) => {
        setAccountToDelete(id);
        setDeleteModalOpen(true);
    };

    const handleDelete = () => {
        if (accountToDelete) {
            router.delete(route('bank-accounts.destroy', accountToDelete), {
                onSuccess: () => {
                    setDeleteModalOpen(false);
                    setAccountToDelete(null);
                },
            });
        }
    };

    const toggleStatus = (id: number) => {
        router.patch(route('bank-accounts.toggle-status', id), {}, {
            preserveState: true,
        });
    };

    return (
        <AppLayout title="Bank Accounts">
            <Head title="Bank Accounts - Tally Software" />

            <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-semibold text-gray-800">Bank Accounts</h2>
                <div className="flex items-center space-x-4">
                    <BusinessSelector businesses={businesses} activeBusiness={activeBusiness} />
                    <Link
                        href={route('bank-accounts.create')}
                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4 mr-2" />
                        New Account
                    </Link>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white shadow-md rounded-lg p-4 mb-6">
                <form onSubmit={handleSearch} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label htmlFor="search" className="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div className="relative rounded-md shadow-sm">
                            <input
                                type="text"
                                id="search"
                                className="block w-full pr-10 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Search accounts..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                            <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <Search className="h-4 w-4 text-gray-400" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label htmlFor="bank_name" className="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                        <input
                            type="text"
                            id="bank_name"
                            className="block w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="Filter by bank..."
                            value={bankNameFilter}
                            onChange={(e) => setBankNameFilter(e.target.value)}
                        />
                    </div>

                    <div>
                        <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select
                            id="status"
                            className="block w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div className="flex items-end space-x-2">
                        <button
                            type="submit"
                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                        >
                            Apply Filters
                        </button>
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            {/* Summary */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div className="flex justify-between items-center">
                    <div className="text-blue-800">
                        <h3 className="text-lg font-semibold">Total Bank Balance</h3>
                        <p className="text-2xl font-bold mt-1">{formattedTotalBalance}</p>
                    </div>
                    <div className="flex space-x-3">
                        <Link
                            href={route('bank-accounts.deposit')}
                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700"
                        >
                            Make Deposit
                        </Link>
                        <Link
                            href={route('bank-accounts.withdrawal')}
                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700"
                        >
                            Make Withdrawal
                        </Link>
                        <Link
                            href={route('bank-accounts.transfer')}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Transfer Funds
                        </Link>
                    </div>
                </div>
            </div>

            {/* Bank accounts list */}
            <div className="bg-white shadow-md rounded-lg overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bank
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Account Details
                                </th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Balance
                                </th>
                                <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {bankAccounts.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                                        No bank accounts found
                                    </td>
                                </tr>
                            ) : (
                                bankAccounts.data.map((account) => (
                                    <tr key={account.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{account.bank_name}</div>
                                            <div className="text-sm text-gray-500">{account.branch_name}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm font-medium text-gray-900">{account.account_name}</div>
                                            <div className="text-sm text-gray-500">A/C No: {account.account_number}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right">
                                            <div className={`text-sm font-semibold ${account.balance && account.balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {companySetting?.currency_symbol} {account.formatted_balance}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-center">
                                            <button
                                                onClick={() => toggleStatus(account.id)}
                                                className={`px-2 py-1 text-xs rounded-full font-semibold ${account.is_active
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                    }`}
                                            >
                                                {account.is_active ? 'Active' : 'Inactive'}
                                            </button>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end space-x-2">
                                                <Link
                                                    href={route('bank-accounts.show', account.id)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                    title="View"
                                                >
                                                    <Eye className="h-5 w-5" />
                                                </Link>
                                                <Link
                                                    href={route('bank-accounts.edit', account.id)}
                                                    className="text-yellow-600 hover:text-yellow-900"
                                                    title="Edit"
                                                >
                                                    <Edit2 className="h-5 w-5" />
                                                </Link>
                                                {/* Only allow delete if no transactions exist */}
                                                <button
                                                    onClick={() => confirmDelete(account.id)}
                                                    className="text-red-600 hover:text-red-900"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-5 w-5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {bankAccounts.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-gray-700">
                                    Showing <span className="font-medium">{bankAccounts.from}</span> to{' '}
                                    <span className="font-medium">{bankAccounts.to}</span> of{' '}
                                    <span className="font-medium">{bankAccounts.total}</span> results
                                </p>
                            </div>
                            <div>
                                <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    {bankAccounts.links.map((link, i) => (
                                        <Link
                                            key={i}
                                            href={link.url || '#'}
                                            className={`relative inline-flex items-center px-4 py-2 text-sm font-medium ${link.url
                                                    ? link.active
                                                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                    : 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                                                } ${i === 0 ? 'rounded-l-md' : ''} ${i === bankAccounts.links.length - 1 ? 'rounded-r-md' : ''
                                                } border`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </nav>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Delete Confirmation Modal */}
            {deleteModalOpen && accountToDelete && (
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
                        <div className="flex items-start">
                            <div className="flex-shrink-0">
                                <AlertTriangle className="h-6 w-6 text-red-600" />
                            </div>
                            <div className="ml-3">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Delete Bank Account
                                </h3>
                                <div className="mt-2">
                                    <p className="text-sm text-gray-500">
                                        Are you sure you want to delete this bank account? This action cannot be undone. Any associated financial data may be affected.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end space-x-3">
                            <button
                                type="button"
                                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                onClick={() => setDeleteModalOpen(false)}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700"
                                onClick={handleDelete}
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
