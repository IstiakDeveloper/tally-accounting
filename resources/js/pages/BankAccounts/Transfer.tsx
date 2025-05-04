import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertCircle, ArrowRightLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankTransferProps, BankAccount } from '@/types';

export default function BankTransfer({ bankAccounts, companySetting }: BankTransferProps) {
  const [values, setValues] = useState({
    from_account_id: '',
    to_account_id: '',
    amount: '',
    transfer_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: '',
  });

  const [errors, setErrors] = useState<{
    from_account_id?: string;
    to_account_id?: string;
    amount?: string;
    transfer_date?: string;
  }>({});

  const [fromAccount, setFromAccount] = useState<BankAccount | null>(null);
  const [toAccount, setToAccount] = useState<BankAccount | null>(null);

  useEffect(() => {
    const selected = bankAccounts.find(account => account.id.toString() === values.from_account_id);
    setFromAccount(selected || null);
  }, [values.from_account_id, bankAccounts]);

  useEffect(() => {
    const selected = bankAccounts.find(account => account.id.toString() === values.to_account_id);
    setToAccount(selected || null);
  }, [values.to_account_id, bankAccounts]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const key = e.target.id;
    const value = e.target.value;

    setValues(values => ({
      ...values,
      [key]: value
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    router.post(route('bank-accounts.process-transfer'), values, {
      onError: (errors) => {
        setErrors(errors);
      },
    });
  };

  const swapAccounts = () => {
    setValues(values => ({
      ...values,
      from_account_id: values.to_account_id,
      to_account_id: values.from_account_id
    }));
  };

  // Format currency
  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: companySetting.currency || 'BDT',
      minimumFractionDigits: 2
    }).format(amount);
  };

  return (
    <AppLayout title="Transfer Funds">
      <Head title="Transfer Funds - Tally Software" />

      <div className="mb-6">
        <Link href={route('bank-accounts.index')} className="inline-flex items-center text-blue-600 hover:text-blue-900">
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to Bank Accounts
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h2 className="text-xl font-semibold text-gray-800">Transfer Funds Between Accounts</h2>
        </div>

        <form onSubmit={handleSubmit} className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Source and Destination Accounts */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Transfer Details</h3>

              <div className="mb-4">
                <label htmlFor="from_account_id" className="block text-sm font-medium text-gray-700 mb-1">
                  From Account*
                </label>
                <select
                  id="from_account_id"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.from_account_id ? 'border-red-300' : ''
                  }`}
                  value={values.from_account_id}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select source account</option>
                  {bankAccounts.map((account) => (
                    <option key={account.id} value={account.id}>
                      {account.bank_name} - {account.account_name} ({account.formatted_balance})
                    </option>
                  ))}
                </select>
                {errors.from_account_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.from_account_id}</p>
                )}
                {fromAccount && (
                  <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md">
                    <p className="text-sm text-blue-800">Available Balance: {companySetting.currency_symbol} {fromAccount.formatted_balance}</p>
                  </div>
                )}
              </div>

              <div className="flex justify-center my-4">
                <button
                  type="button"
                  onClick={swapAccounts}
                  className="inline-flex items-center p-2 border border-gray-300 rounded-full shadow-sm text-white bg-gray-500 hover:bg-gray-600"
                >
                  <ArrowRightLeft className="h-5 w-5" />
                  <span className="sr-only">Swap accounts</span>
                </button>
              </div>

              <div className="mb-4">
                <label htmlFor="to_account_id" className="block text-sm font-medium text-gray-700 mb-1">
                  To Account*
                </label>
                <select
                  id="to_account_id"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.to_account_id ? 'border-red-300' : ''
                  }`}
                  value={values.to_account_id}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select destination account</option>
                  {bankAccounts.map((account) => (
                    <option
                      key={account.id}
                      value={account.id}
                      disabled={account.id.toString() === values.from_account_id}
                    >
                      {account.bank_name} - {account.account_name} ({account.formatted_balance})
                    </option>
                  ))}
                </select>
                {errors.to_account_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.to_account_id}</p>
                )}
                {toAccount && (
                  <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md">
                    <p className="text-sm text-blue-800">Current Balance: {companySetting.currency_symbol} {toAccount.formatted_balance}</p>
                  </div>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="amount" className="block text-sm font-medium text-gray-700 mb-1">
                  Transfer Amount*
                </label>
                <div className="relative rounded-md shadow-sm">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span className="text-gray-500 sm:text-sm">{companySetting.currency_symbol}</span>
                  </div>
                  <input
                    type="number"
                    step="0.01"
                    id="amount"
                    className={`block w-full pl-7 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                      errors.amount ? 'border-red-300' : ''
                    }`}
                    placeholder="0.00"
                    value={values.amount}
                    onChange={handleChange}
                    required
                  />
                </div>
                {errors.amount && (
                  <p className="mt-1 text-sm text-red-600">{errors.amount}</p>
                )}
              </div>
            </div>

            {/* Transfer Details */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>

              <div className="mb-4">
                <label htmlFor="transfer_date" className="block text-sm font-medium text-gray-700 mb-1">
                  Transfer Date*
                </label>
                <input
                  type="date"
                  id="transfer_date"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.transfer_date ? 'border-red-300' : ''
                  }`}
                  value={values.transfer_date}
                  onChange={handleChange}
                  required
                />
                {errors.transfer_date && (
                  <p className="mt-1 text-sm text-red-600">{errors.transfer_date}</p>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="reference" className="block text-sm font-medium text-gray-700 mb-1">
                  Reference Number
                </label>
                <input
                  type="text"
                  id="reference"
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Optional reference number"
                  value={values.reference}
                  onChange={handleChange}
                />
                <p className="mt-1 text-xs text-gray-500">
                  Leave blank to auto-generate a reference number
                </p>
              </div>

              <div className="mb-4">
                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <textarea
                  id="description"
                  rows={3}
                  className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Enter a description for this transfer"
                  value={values.description}
                  onChange={handleChange}
                ></textarea>
              </div>

              {/* Preview of Transfer */}
              {values.from_account_id && values.to_account_id && values.amount && (
                <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Transfer Preview</h4>
                  <p className="text-sm text-gray-600">
                    You are transferring {values.amount && companySetting.currency_symbol} {values.amount} from account {fromAccount?.account_name} to {toAccount?.account_name}.
                  </p>
                  {fromAccount && parseFloat(values.amount) > (fromAccount.balance || 0) && (
                    <div className="mt-2 text-sm text-red-600 flex items-center">
                      <AlertCircle className="h-4 w-4 mr-1" />
                      Insufficient funds in source account
                    </div>
                  )}
                </div>
              )}
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
              disabled={fromAccount && parseFloat(values.amount) > (fromAccount.balance || 0)}
            >
              Process Transfer
            </button>
          </div>
        </form>
      </div>

      <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex items-start">
        <AlertCircle className="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="text-sm font-medium text-yellow-800">Important Note</h4>
          <p className="mt-1 text-sm text-yellow-700">
            When you transfer funds between accounts, a journal entry will be created automatically
            to record this transaction. The transfer will immediately affect the balances of both accounts.
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
