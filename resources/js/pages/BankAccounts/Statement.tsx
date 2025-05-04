import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  ArrowLeft,
  Printer,
  Download,
  Calendar,
  RefreshCw,
  ChevronDown
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankAccountStatementProps } from '@/types';

export default function BankAccountStatement({
  bankAccount,
  statementData,
  fromDate,
  toDate,
  openingBalance,
  totalDebits,
  totalCredits,
  netMovement,
  closingBalance,
  formattedOpeningBalance,
  formattedTotalDebits,
  formattedTotalCredits,
  formattedNetMovement,
  formattedClosingBalance,
  currencySymbol,
  companySetting
}: BankAccountStatementProps) {
  const [newFromDate, setNewFromDate] = useState(fromDate);
  const [newToDate, setNewToDate] = useState(toDate);
  const [expanded, setExpanded] = useState(false);

  const refreshStatement = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('bank-accounts.statement', bankAccount.id), {
      from_date: newFromDate,
      to_date: newToDate
    });
  };

  const exportStatement = () => {
    router.get(route('bank-accounts.export-statement', bankAccount.id), {
      from_date: fromDate,
      to_date: toDate
    });
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  return (
    <AppLayout title={`Bank Statement: ${bankAccount.account_name}`}>
      <Head title={`${bankAccount.account_name} - Bank Statement - Tally Software`} />

      <div className="mb-6">
        <Link
          href={route('bank-accounts.show', bankAccount.id)}
          className="inline-flex items-center text-blue-600 hover:text-blue-900"
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to Account Details
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <div className="flex justify-between items-center">
            <h2 className="text-xl font-semibold text-gray-800">Bank Account Statement</h2>
            <div className="flex space-x-2">
              <button
                onClick={() => window.print()}
                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                title="Print Statement"
              >
                <Printer className="h-4 w-4 mr-2" />
                Print
              </button>
              <button
                onClick={exportStatement}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                title="Export as PDF"
              >
                <Download className="h-4 w-4 mr-2" />
                Export PDF
              </button>
            </div>
          </div>
        </div>

        <div className="p-6">
          {/* Account Details */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">Account Information</h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-2">
                <div>
                  <dt className="text-sm font-medium text-gray-500">Account Name</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Account Number</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_number}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Bank</dt>
                  <dd className="mt-1 text-sm text-gray-900">{bankAccount.bank_name}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">Statement Period</h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-2">
                <div>
                  <dt className="text-sm font-medium text-gray-500">From Date</dt>
                  <dd className="mt-1 text-sm text-gray-900">{formatDate(fromDate)}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">To Date</dt>
                  <dd className="mt-1 text-sm text-gray-900">{formatDate(toDate)}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">Statement Summary</h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-2">
                <div>
                  <dt className="text-sm font-medium text-gray-500">Opening Balance</dt>
                  <dd className="mt-1 text-sm text-gray-900">{currencySymbol} {formattedOpeningBalance}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Closing Balance</dt>
                  <dd className="mt-1 text-sm font-bold text-gray-900">{currencySymbol} {formattedClosingBalance}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Net Movement</dt>
                  <dd className={`mt-1 text-sm font-medium ${
                    netMovement >= 0 ? 'text-green-600' : 'text-red-600'
                  }`}>
                    {netMovement >= 0 ? '+' : ''}{currencySymbol} {formattedNetMovement}
                  </dd>
                </div>
              </dl>
            </div>
          </div>

          {/* Date Range Filter */}
          <div className={`mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg ${expanded ? '' : 'hidden md:block'}`}>
            <button
              className="w-full flex justify-between items-center md:hidden mb-2"
              onClick={() => setExpanded(!expanded)}
            >
              <span className="text-sm font-medium text-gray-700">Change Date Range</span>
              <ChevronDown className={`h-4 w-4 text-gray-500 transition-transform ${expanded ? 'transform rotate-180' : ''}`} />
            </button>
            <form onSubmit={refreshStatement} className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label htmlFor="from_date" className="block text-sm font-medium text-gray-700 mb-1">
                  From Date
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Calendar className="h-4 w-4 text-gray-400" />
                  </div>
                  <input
                    type="date"
                    id="from_date"
                    className="block w-full pl-10 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    value={newFromDate}
                    onChange={(e) => setNewFromDate(e.target.value)}
                    required
                  />
                </div>
              </div>
              <div>
                <label htmlFor="to_date" className="block text-sm font-medium text-gray-700 mb-1">
                  To Date
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Calendar className="h-4 w-4 text-gray-400" />
                  </div>
                  <input
                    type="date"
                    id="to_date"
                    className="block w-full pl-10 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    value={newToDate}
                    onChange={(e) => setNewToDate(e.target.value)}
                    required
                  />
                </div>
              </div>
              <div className="flex items-end">
                <button
                  type="submit"
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                >
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Update Statement
                </button>
              </div>
            </form>
          </div>

          {/* Mobile: Show Filters Button */}
          <div className="md:hidden mb-4">
            <button
              className="w-full flex justify-center items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              onClick={() => setExpanded(!expanded)}
            >
              {expanded ? 'Hide Filters' : 'Change Date Range'}
              <ChevronDown className={`ml-2 h-4 w-4 text-gray-500 transition-transform ${expanded ? 'transform rotate-180' : ''}`} />
            </button>
          </div>

          {/* Statement Transactions Table */}
          <div className="overflow-x-auto border border-gray-200 rounded-lg">
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
                    Debit
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Credit
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Balance
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {statementData.map((transaction, index) => (
                  <tr key={index} className={index === 0 ? 'bg-blue-50' : 'hover:bg-gray-50'}>
                    <td className="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(transaction.date)}
                    </td>
                    <td className="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                      {transaction.reference}
                    </td>
                    <td className="px-6 py-3 text-sm text-gray-900">
                      {transaction.description}
                    </td>
                    <td className="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                      {transaction.debit > 0 ? `${currencySymbol} ${transaction.formatted_debit}` : '-'}
                    </td>
                    <td className="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                      {transaction.credit > 0 ? `${currencySymbol} ${transaction.formatted_credit}` : '-'}
                    </td>
                    <td className="px-6 py-3 whitespace-nowrap text-sm text-right font-medium">
                      {currencySymbol} {transaction.formatted_balance}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50 font-medium">
                <tr>
                  <td colSpan={3} className="px-6 py-3 text-right text-sm text-gray-700">
                    Totals for Period:
                  </td>
                  <td className="px-6 py-3 whitespace-nowrap text-sm text-right text-green-600">
                    {currencySymbol} {formattedTotalDebits}
                  </td>
                  <td className="px-6 py-3 whitespace-nowrap text-sm text-right text-red-600">
                    {currencySymbol} {formattedTotalCredits}
                  </td>
                  <td className="px-6 py-3 whitespace-nowrap text-sm text-right text-blue-600 font-bold">
                    {currencySymbol} {formattedClosingBalance}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
          <div className="text-xs text-gray-500">
            <p>Statement generated on {new Date().toLocaleDateString()} at {new Date().toLocaleTimeString()}</p>
            <p className="mt-1">This is a computer-generated statement and does not require a signature.</p>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
