import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  Plus,
  Edit2,
  Trash2,
  AlertTriangle,
  Search,
  Eye,
  Filter,
  X,
  ToggleLeft,
  ToggleRight
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { ChartOfAccount, AccountCategory } from '@/types';
import Pagination from '@/components/Pagination';

interface ChartOfAccountsIndexProps {
  accounts: {
    data: ChartOfAccount[];
    meta: Pagination;
  };
  categories: AccountCategory[];
  filters: {
    search: string;
    category_type: string;
    status: string;
  };
}

export default function ChartOfAccountsIndex({ accounts, categories, filters }: ChartOfAccountsIndexProps) {
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [accountToDelete, setAccountToDelete] = useState<ChartOfAccount | null>(null);
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [showFilters, setShowFilters] = useState(false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters({ search: searchTerm });
  };

  const applyFilters = (updatedFilters: Partial<typeof filters>) => {
    router.get(route('chart-of-accounts.index'), {
      ...filters,
      ...updatedFilters,
    }, {
      preserveState: true,
      replace: true,
    });
  };

  const resetFilters = () => {
    router.get(route('chart-of-accounts.index'), {
      search: '',
      category_type: '',
      status: '',
    }, {
      preserveState: true,
      replace: true,
    });
    setSearchTerm('');
  };

  const handleDelete = () => {
    if (accountToDelete) {
      router.delete(route('chart-of-accounts.destroy', accountToDelete.id), {
        onSuccess: () => {
          setDeleteModalOpen(false);
          setAccountToDelete(null);
        },
      });
    }
  };

  const showDeleteModal = (account: ChartOfAccount) => {
    setAccountToDelete(account);
    setDeleteModalOpen(true);
  };

  const toggleAccountStatus = (id: number) => {
    router.patch(route('chart-of-accounts.toggle-status', id));
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'Asset':
        return 'bg-blue-100 text-blue-800';
      case 'Liability':
        return 'bg-red-100 text-red-800';
      case 'Equity':
        return 'bg-purple-100 text-purple-800';
      case 'Revenue':
        return 'bg-green-100 text-green-800';
      case 'Expense':
        return 'bg-orange-100 text-orange-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <AppLayout title="Chart of Accounts">
      <Head title="Chart of Accounts - Tally Software" />

      <div className="mb-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold text-gray-800">Chart of Accounts</h2>
          <Link
            href={route('chart-of-accounts.create')}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
          >
            <Plus className="h-4 w-4 mr-2" />
            New Account
          </Link>
        </div>

        {/* Search and Filter */}
        <div className="bg-white shadow-md rounded-lg overflow-hidden mb-6">
          <div className="p-4">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <form onSubmit={handleSearch} className="flex-1">
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Search className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type="text"
                    placeholder="Search by name, code or description..."
                    className="h-10 pl-10 pr-4 py-2 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm text-sm border-gray-300 rounded-md"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                  {searchTerm && (
                    <button
                      type="button"
                      className="absolute inset-y-0 right-0 pr-3 flex items-center"
                      onClick={() => {
                        setSearchTerm('');
                        if (filters.search) {
                          applyFilters({ search: '' });
                        }
                      }}
                    >
                      <X className="h-4 w-4 text-gray-400" />
                    </button>
                  )}
                </div>
              </form>

              <div className="flex space-x-2">
                <button
                  type="button"
                  onClick={() => setShowFilters(!showFilters)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white text-gray-700 hover:bg-gray-50"
                >
                  <Filter className="h-4 w-4 mr-2" />
                  Filters
                </button>

                {(filters.search || filters.category_type || filters.status) && (
                  <button
                    type="button"
                    onClick={resetFilters}
                    className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white text-gray-700 hover:bg-gray-50"
                  >
                    <X className="h-4 w-4 mr-2" />
                    Clear
                  </button>
                )}
              </div>
            </div>

            {/* Advanced Filters */}
            {showFilters && (
              <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="category_type" className="block text-sm font-medium text-gray-700 mb-1">
                    Account Type
                  </label>
                  <select
                    id="category_type"
                    className="h-10 px-4 py-2 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm text-sm border-gray-300 rounded-md"
                    value={filters.category_type}
                    onChange={(e) => applyFilters({ category_type: e.target.value })}
                  >
                    <option value="">All Types</option>
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Revenue">Revenue</option>
                    <option value="Expense">Expense</option>
                  </select>
                </div>

                <div>
                  <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">
                    Status
                  </label>
                  <select
                    id="status"
                    className="h-10 px-4 py-2 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm text-sm border-gray-300 rounded-md"
                    value={filters.status}
                    onChange={(e) => applyFilters({ status: e.target.value })}
                  >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Accounts Table */}
        <div className="bg-white shadow-md rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Code
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Name
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Category
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {accounts.data.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                      No accounts found
                    </td>
                  </tr>
                ) : (
                  accounts.data.map((account) => (
                    <tr key={account.id} className={!account.is_active ? 'bg-gray-50' : ''}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {account.account_code}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-900">
                        {account.name}
                        {account.description && (
                          <p className="text-xs text-gray-500 mt-1 truncate max-w-xs">{account.description}</p>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${getTypeColor(account.category.type)}`}>
                          {account.category.name}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${account.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {account.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex justify-end space-x-2">
                          <Link
                            href={route('chart-of-accounts.show', account.id)}
                            className="text-indigo-600 hover:text-indigo-900"
                            title="View Details"
                          >
                            <Eye className="h-5 w-5" />
                          </Link>
                          <Link
                            href={route('chart-of-accounts.edit', account.id)}
                            className="text-blue-600 hover:text-blue-900"
                            title="Edit"
                          >
                            <Edit2 className="h-5 w-5" />
                          </Link>
                          <button
                            onClick={() => toggleAccountStatus(account.id)}
                            className={`${account.is_active ? 'text-gray-600 hover:text-gray-900' : 'text-green-600 hover:text-green-900'}`}
                            title={account.is_active ? 'Deactivate' : 'Activate'}
                          >
                            {account.is_active ? (
                              <ToggleRight className="h-5 w-5" />
                            ) : (
                              <ToggleLeft className="h-5 w-5" />
                            )}
                          </button>
                          <button
                            onClick={() => showDeleteModal(account)}
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
          {accounts.data.length > 0 && (
            <div className="px-4 py-3 border-t border-gray-200 bg-gray-50">
              <Pagination meta={accounts.meta} />
            </div>
          )}
        </div>
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
                  Delete Account
                </h3>
                <div className="mt-2">
                  <p className="text-sm text-gray-500">
                    Are you sure you want to delete the account "{accountToDelete.name}" ({accountToDelete.account_code})? This action cannot be undone.
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
