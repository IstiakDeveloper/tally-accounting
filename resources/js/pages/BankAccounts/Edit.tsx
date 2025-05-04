import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankAccountFormProps } from '@/types';

export default function BankAccountEdit({ bankAccount, chartAccounts }: BankAccountFormProps) {
  const [values, setValues] = useState({
    account_name: bankAccount?.account_name || '',
    account_number: bankAccount?.account_number || '',
    bank_name: bankAccount?.bank_name || '',
    branch_name: bankAccount?.branch_name || '',
    swift_code: bankAccount?.swift_code || '',
    routing_number: bankAccount?.routing_number || '',
    address: bankAccount?.address || '',
    contact_person: bankAccount?.contact_person || '',
    contact_number: bankAccount?.contact_number || '',
    account_id: bankAccount?.account_id?.toString() || '',
    is_active: bankAccount?.is_active ?? true,
  });

  const [errors, setErrors] = useState<{
    account_name?: string;
    account_number?: string;
    bank_name?: string;
    account_id?: string;
  }>({});

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const key = e.target.id;
    const value = e.target.type === 'checkbox'
      ? (e.target as HTMLInputElement).checked
      : e.target.value;

    setValues(values => ({
      ...values,
      [key]: value
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (bankAccount) {
      router.put(route('bank-accounts.update', bankAccount.id), values, {
        onError: (errors) => {
          setErrors(errors);
        },
      });
    }
  };

  return (
    <AppLayout title="Edit Bank Account">
      <Head title="Edit Bank Account - Tally Software" />

      <div className="mb-6">
        <Link href={route('bank-accounts.index')} className="inline-flex items-center text-blue-600 hover:text-blue-900">
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to Bank Accounts
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h2 className="text-xl font-semibold text-gray-800">Edit Bank Account</h2>
        </div>

        <form onSubmit={handleSubmit} className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Account Information</h3>

              <div className="mb-4">
                <label htmlFor="account_name" className="block text-sm font-medium text-gray-700 mb-1">
                  Account Name*
                </label>
                <input
                  type="text"
                  id="account_name"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.account_name ? 'border-red-300' : ''
                  }`}
                  value={values.account_name}
                  onChange={handleChange}
                  required
                />
                {errors.account_name && (
                  <p className="mt-1 text-sm text-red-600">{errors.account_name}</p>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="account_number" className="block text-sm font-medium text-gray-700 mb-1">
                  Account Number*
                </label>
                <input
                  type="text"
                  id="account_number"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.account_number ? 'border-red-300' : ''
                  }`}
                  value={values.account_number}
                  onChange={handleChange}
                  required
                />
                {errors.account_number && (
                  <p className="mt-1 text-sm text-red-600">{errors.account_number}</p>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="bank_name" className="block text-sm font-medium text-gray-700 mb-1">
                  Bank Name*
                </label>
                <input
                  type="text"
                  id="bank_name"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.bank_name ? 'border-red-300' : ''
                  }`}
                  value={values.bank_name}
                  onChange={handleChange}
                  required
                />
                {errors.bank_name && (
                  <p className="mt-1 text-sm text-red-600">{errors.bank_name}</p>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="branch_name" className="block text-sm font-medium text-gray-700 mb-1">
                  Branch Name
                </label>
                <input
                  type="text"
                  id="branch_name"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={values.branch_name}
                  onChange={handleChange}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="mb-4">
                  <label htmlFor="swift_code" className="block text-sm font-medium text-gray-700 mb-1">
                    SWIFT Code
                  </label>
                  <input
                    type="text"
                    id="swift_code"
                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    value={values.swift_code}
                    onChange={handleChange}
                  />
                </div>

                <div className="mb-4">
                  <label htmlFor="routing_number" className="block text-sm font-medium text-gray-700 mb-1">
                    Routing Number
                  </label>
                  <input
                    type="text"
                    id="routing_number"
                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    value={values.routing_number}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>

            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>

              <div className="mb-4">
                <label htmlFor="address" className="block text-sm font-medium text-gray-700 mb-1">
                  Address
                </label>
                <textarea
                  id="address"
                  rows={3}
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={values.address}
                  onChange={handleChange}
                ></textarea>
              </div>

              <div className="mb-4">
                <label htmlFor="contact_person" className="block text-sm font-medium text-gray-700 mb-1">
                  Contact Person
                </label>
                <input
                  type="text"
                  id="contact_person"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={values.contact_person}
                  onChange={handleChange}
                />
              </div>

              <div className="mb-4">
                <label htmlFor="contact_number" className="block text-sm font-medium text-gray-700 mb-1">
                  Contact Number
                </label>
                <input
                  type="text"
                  id="contact_number"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  value={values.contact_number}
                  onChange={handleChange}
                />
              </div>

              <div className="mb-4">
                <label htmlFor="account_id" className="block text-sm font-medium text-gray-700 mb-1">
                  Associated Chart of Account*
                </label>
                <select
                  id="account_id"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.account_id ? 'border-red-300' : ''
                  }`}
                  value={values.account_id}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select an account</option>
                  {chartAccounts.map((account) => (
                    <option key={account.id} value={account.id}>
                      {account.account_code} - {account.name}
                    </option>
                  ))}
                </select>
                {errors.account_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.account_id}</p>
                )}
                <p className="mt-1 text-xs text-gray-500">
                  Select an asset account to associate with this bank account
                </p>
              </div>

              <div className="mb-4 flex items-center">
                <input
                  type="checkbox"
                  id="is_active"
                  className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  checked={values.is_active}
                  onChange={handleChange}
                />
                <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                  Active
                </label>
              </div>
            </div>
          </div>

          <div className="mt-6 pt-6 border-t border-gray-200 flex items-center justify-end space-x-3">
            <Link
              href={route('bank-accounts.index')}
              className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
            >
              Update Bank Account
            </button>
          </div>
        </form>
      </div>

      <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex items-start">
        <AlertCircle className="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="text-sm font-medium text-yellow-800">Important Note</h4>
          <p className="mt-1 text-sm text-yellow-700">
            Changing the associated Chart of Account will affect all future transactions, but will not modify past transactions.
            If this account has existing transactions, consider creating a new bank account instead of changing the Chart of Account.
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
