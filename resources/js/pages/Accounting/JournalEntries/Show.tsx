import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  ArrowLeft,
  Edit2,
  Printer,
  Check,
  XCircle,
  AlertTriangle,
  User,
  Calendar,
  Clock
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { JournalEntry } from '@/types';

interface JournalEntriesShowProps {
  journalEntry: JournalEntry;
  totalDebit: number;
  totalCredit: number;
}

export default function JournalEntriesShow({
  journalEntry,
  totalDebit,
  totalCredit
}: JournalEntriesShowProps) {
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'BDT',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'draft':
        return 'bg-yellow-100 text-yellow-800';
      case 'posted':
        return 'bg-green-100 text-green-800';
      case 'cancelled':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <AppLayout title={`Journal Entry: ${journalEntry.reference_number}`}>
      <Head title={`Journal Entry ${journalEntry.reference_number} - Tally Software`} />

      <div className="flex flex-col space-y-6">
        {/* Action Bar */}
        <div className="flex justify-between items-center">
          <Link
            href={route('journal-entries.index')}
            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Journal Entries
          </Link>
          <div className="flex space-x-2">
            <button
              type="button"
              onClick={() => window.print()}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <Printer className="h-4 w-4 mr-2" />
              Print
            </button>

            {journalEntry.status === 'draft' && (
              <>
                <Link
                  href={route('journal-entries.edit', journalEntry.id)}
                  className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                >
                  <Edit2 className="h-4 w-4 mr-2" />
                  Edit
                </Link>
                <Link
                  href={route('journal-entries.post', journalEntry.id)}
                  method="patch"
                  as="button"
                  className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                >
                  <Check className="h-4 w-4 mr-2" />
                  Post
                </Link>
              </>
            )}

            {journalEntry.status === 'posted' && (
              <Link
                href={route('journal-entries.cancel', journalEntry.id)}
                method="patch"
                as="button"
                className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                <XCircle className="h-4 w-4 mr-2" />
                Cancel
              </Link>
            )}
          </div>
        </div>

        {/* Journal Entry Card */}
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 className="text-lg font-medium text-gray-900">Journal Entry Details</h3>
            <span className={`px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full ${getStatusBadgeClass(journalEntry.status)}`}>
              {journalEntry.status.charAt(0).toUpperCase() + journalEntry.status.slice(1)}
            </span>
          </div>

          <div className="px-6 py-4 border-b border-gray-200">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <h4 className="text-sm font-medium text-gray-500">Reference Number</h4>
                <p className="mt-1 text-lg font-bold text-gray-900">{journalEntry.reference_number}</p>
              </div>
              <div>
                <h4 className="text-sm font-medium text-gray-500">Date</h4>
                <p className="mt-1 text-lg text-gray-900">{formatDate(journalEntry.entry_date)}</p>
              </div>
              <div className="md:col-span-2">
                <h4 className="text-sm font-medium text-gray-500">Description</h4>
                <p className="mt-1 text-base text-gray-900">{journalEntry.narration}</p>
              </div>
              <div>
                <h4 className="text-sm font-medium text-gray-500">Financial Year</h4>
                <p className="mt-1 text-base text-gray-900">{journalEntry.financialYear.name}</p>
              </div>
              <div>
                <h4 className="text-sm font-medium text-gray-500">Amount</h4>
                <p className="mt-1 text-lg font-bold text-gray-900">{formatCurrency(totalDebit)}</p>
              </div>
            </div>
          </div>

          {/* Journal Items Table */}
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Account
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
                {journalEntry.items.map((item) => (
                  <tr key={item.id} className={item.type === 'debit' ? 'bg-green-50' : 'bg-blue-50'}>
                    <td className="px-6 py-4 text-sm">
                      <div className="font-medium text-gray-900">{item.account.name}</div>
                      <div className="text-xs text-gray-500">{item.account.account_code}</div>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500">
                      {item.description || '-'}
                    </td>
                    <td className="px-6 py-4 text-sm text-right font-medium text-gray-900">
                      {item.type === 'debit' ? formatCurrency(item.amount) : '-'}
                    </td>
                    <td className="px-6 py-4 text-sm text-right font-medium text-gray-900">
                      {item.type === 'credit' ? formatCurrency(item.amount) : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50 font-medium">
                <tr>
                  <td colSpan={2} className="px-6 py-4 text-sm text-right text-gray-500">
                    Totals:
                  </td>
                  <td className="px-6 py-4 text-sm text-right font-medium text-gray-900">
                    {formatCurrency(totalDebit)}
                  </td>
                  <td className="px-6 py-4 text-sm text-right font-medium text-gray-900">
                    {formatCurrency(totalCredit)}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          {/* Footer with metadata */}
          <div className="px-6 py-4 bg-gray-50 text-sm text-gray-500">
            <div className="flex flex-col md:flex-row md:justify-between">
              <div className="flex items-center mb-2 md:mb-0">
                <User className="h-4 w-4 mr-1" />
                Created by: {journalEntry.createdBy.name}
              </div>
              <div className="flex items-center">
                <Calendar className="h-4 w-4 mr-1" />
                Created: {formatDateTime(journalEntry.created_at)}
              </div>
              {journalEntry.updated_at !== journalEntry.created_at && (
                <div className="flex items-center mt-2 md:mt-0">
                  <Clock className="h-4 w-4 mr-1" />
                  Last updated: {formatDateTime(journalEntry.updated_at)}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
