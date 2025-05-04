import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  ArrowLeft,
  Edit2,
  Calendar,
  DollarSign,
  FileText,
  BarChart2,
  Download,
  ExternalLink,
  RefreshCw,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankAccountShowProps } from '@/types';

export default function BankAccountShow({
  bankAccount,
  balance,
  formattedBalance,
  recentTransactions,
  companySetting
}: BankAccountShowProps) {
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [statementModalOpen, setStatementModalOpen] = useState(false);

  const viewStatement = (e: React.FormEvent) => {
    e.preventDefault();
    if (fromDate && toDate) {
      // Redirect to statement page with date range
      router.get(route('bank-accounts.statement', bankAccount.id), {
        from_date: fromDate,
        to_date: toDate
      });
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(
      undefined,
      { year: 'numeric', month: 'short', day: 'numeric' }
    );
  };

  return (
    <AppLayout title={`Bank Account: ${bankAccount.account_name}`}>
      <Head title={`${bankAccount.account_name} - Bank Account Details - Tally Software`} />

      <div className="mb-6">
        <Link href={route('bank-accounts.index')} className="inline-flex items-center text-blue-600 hover:text-blue-900">
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to All Bank Accounts
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <div className="flex justify-between items-center">
            <h2 className="text-xl font-semibold text-gray-800">Bank Account Details</h2>
            <div className="flex space-x-2">
              <Link
                href={route('bank-accounts.edit', bankAccount.id)}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
              >
                <Edit2 className="h-4 w-4 mr-2" />
                Edit
              </Link>
              <button
                onClick={() => setStatementModalOpen(true)}
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50"
              >
                <FileText className="h-4 w-4 mr-2" />
                Generate Statement
              </button>
            </div>
          </div>
        </div>

        <div className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <dt className="text-sm font-medium text-gray-500">Account Name</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Account Number</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_number}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Status</dt>
                  <dd className="mt-1 text-sm">
                    <span className={`px-2 py-1 text-xs rounded-full font-semibold ${
                      bankAccount.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {bankAccount.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Bank Name</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.bank_name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Branch</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.branch_name || '-'}</dd>
                </div>
                {bankAccount.swift_code && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">SWIFT Code</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.swift_code}</dd>
                  </div>
                )}
                {bankAccount.routing_number && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Routing Number</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.routing_number}</dd>
                  </div>
                )}
              </dl>
            </div>

            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Contact Information</h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-3">
                {bankAccount.address && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Address</dt>
                    <dd className="mt-1 text-sm text-gray-900 whitespace-pre-line">{bankAccount.address}</dd>
                  </div>
                )}
                {bankAccount.contact_person && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Contact Person</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.contact_person}</dd>
                  </div>
                )}
                {bankAccount.contact_number && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Contact Number</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.contact_number}</dd>
                  </div>
                )}
              </dl>

              <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <h4 className="text-md font-medium text-blue-800 mb-2">Current Balance</h4>
                <p className="text-2xl font-bold text-blue-800">{formattedBalance}</p>
                <div className="mt-3 flex space-x-2">
                  <Link
                    href={route('bank-accounts.withdrawal', { account_id: bankAccount.id })}
                    className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700"
                  >
                    Withdraw
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Transactions */}
      <div className="bg-white shadow-md rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <div className="flex justify-between items-center">
            <h3 className="text-lg font-medium text-gray-900">Recent Transactions</h3>
            <Link
              href={route('bank-accounts.statement', bankAccount.id)}
              className="inline-flex items-center text-sm text-blue-600 hover:text-blue-900"
            >
              View Full Statement
              <ExternalLink className="h-4 w-4 ml-1" />
            </Link>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Reference
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Description
                </th>
                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Amount
                </th>
                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Balance
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {recentTransactions.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                    No recent transactions found
                  </td>
                </tr>
              ) : (
                recentTransactions.map((transaction, index) => (
                  <tr key={index} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(transaction.date)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {transaction.reference}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500">
                      {transaction.description}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <span className={transaction.type === 'debit'
                        ? 'text-green-600 font-medium'
                        : 'text-red-600 font-medium'}>
                        {transaction.type === 'debit' ? '+' : '-'} {companySetting.currency_symbol} {transaction.formatted_amount}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right font-medium">
                      {companySetting.currency_symbol} {transaction.formatted_balance}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Generate Statement Modal */}
      {statementModalOpen && (
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                Generate Account Statement
              </h3>
              <button
                type="button"
                className="text-gray-400 hover:text-gray-500"
                onClick={() => setStatementModalOpen(false)}
              >
                <span className="sr-only">Close</span>
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <form onSubmit={viewStatement}>
              <div className="mb-4">
                <label htmlFor="from_date" className="block text-sm font-medium text-gray-700 mb-1">
                  From Date
                </label>
                <input
                  type="date"
                  id="from_date"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  required
                  value={fromDate}
                  onChange={(e) => setFromDate(e.target.value)}
                />
              </div>

              <div className="mb-6">
                <label htmlFor="to_date" className="block text-sm font-medium text-gray-700 mb-1">
                  To Date
                </label>
                <input
                  type="date"
                  id="to_date"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  required
                  value={toDate}
                  onChange={(e) => setToDate(e.target.value)}
                />
              </div>

              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  onClick={() => setStatementModalOpen(false)}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                >
                  Generate Statement
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}

