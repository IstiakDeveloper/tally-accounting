import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  ArrowLeft,
  Edit2,
  DollarSign,
  Calendar,
  User,
  Clipboard,
  FileText
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { ChartOfAccount, JournalItem } from '@/types';

interface ChartOfAccountsShowProps {
  account: ChartOfAccount;
  balance: number;
  journalItems: JournalItem[];
}

export default function ChartOfAccountsShow({ account, balance, journalItems }: ChartOfAccountsShowProps) {
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'BDT'
    }).format(amount);
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
    <AppLayout title={`Account: ${account.name}`}>
      <Head title={`${account.name} - Tally Software`} />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('chart-of-accounts.index')}
          className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Chart of Accounts
        </Link>
        <Link
          href={route('chart-of-accounts.edit', account.id)}
          className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          <Edit2 className="h-4 w-4 mr-2" />
          Edit Account
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Account Details Card */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-md overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Account Details</h3>
            </div>
            <div className="p-6">
              <dl className="divide-y divide-gray-200">
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <Clipboard className="h-4 w-4 mr-2" />
                    Code
                  </dt>
                  <dd className="text-sm font-bold text-gray-900 col-span-2">{account.account_code}</dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <FileText className="h-4 w-4 mr-2" />
                    Name
                  </dt>
                  <dd className="text-sm text-gray-900 col-span-2">{account.name}</dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500">Category</dt>
                  <dd className="text-sm text-gray-900 col-span-2">
                    <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${getTypeColor(account.category.type)}`}>
                      {account.category.name} ({account.category.type})
                    </span>
                  </dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500">Status</dt>
                  <dd className="text-sm text-gray-900 col-span-2">
                    <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${account.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                      {account.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <DollarSign className="h-4 w-4 mr-2" />
                    Balance
                  </dt>
                  <dd className={`text-sm font-bold col-span-2 ${balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {formatCurrency(balance)}
                  </dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <User className="h-4 w-4 mr-2" />
                    Created By
                  </dt>
                  <dd className="text-sm text-gray-900 col-span-2">{account.created_by.name}</dd>
                </div>
                <div className="py-3 grid grid-cols-3 gap-4">
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <Calendar className="h-4 w-4 mr-2" />
                    Created At
                  </dt>
                  <dd className="text-sm text-gray-900 col-span-2">{formatDate(account.created_at)}</dd>
                </div>
                {account.description && (
                  <div className="py-3">
                    <dt className="text-sm font-medium text-gray-500 mb-1">Description</dt>
                    <dd className="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{account.description}</dd>
                  </div>
                )}
              </dl>
            </div>
          </div>
        </div>

        {/* Recent Transactions Card */}
        <div className="lg:col-span-2">
          <div className="bg-white rounded-lg shadow-md overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
              <h3 className="text-lg font-medium text-gray-900">Recent Transactions</h3>
              <Link
                href={route('journal-entries.index', { account_id: account.id })}
                className="text-sm text-blue-600 hover:text-blue-900"
              >
                View All
              </Link>
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
                      Debit
                    </th>
                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Credit
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {journalItems.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                        No transactions found for this account
                      </td>
                    </tr>
                  ) : (
                    journalItems.map((item) => (
                      <tr key={item.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {formatDate(item.journalEntry.entry_date)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          <Link
                            href={route('journal-entries.show', item.journalEntry.id)}
                            className="text-blue-600 hover:text-blue-900"
                          >
                            {item.journalEntry.reference_number}
                          </Link>
                        </td>
                        <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                          {item.description || item.journalEntry.narration}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                          {item.type === 'debit' ? formatCurrency(item.amount) : '-'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                          {item.type === 'credit' ? formatCurrency(item.amount) : '-'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
                {/* Summary row */}
                {journalItems.length > 0 && (
                  <tfoot className="bg-gray-50">
                    <tr>
                      <td colSpan={3} className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500 text-right">
                        Balance:
                      </td>
                      <td colSpan={2} className={`px-6 py-4 whitespace-nowrap text-sm font-medium text-right ${balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {formatCurrency(balance)}
                      </td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
