import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertCircle, ArrowDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankDepositProps, BankAccount, ChartOfAccount } from '@/types';

export default function BankDeposit({ bankAccounts, incomeAccounts, companySetting }: BankDepositProps) {
  const [values, setValues] = useState({
    bank_account_id: '',
    amount: '',
    income_account_id: '',
    deposit_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: '',
  });

  const [errors, setErrors] = useState<{
    bank_account_id?: string;
    amount?: string;
    income_account_id?: string;
    deposit_date?: string;
  }>({});

  const [selectedBankAccount, setSelectedBankAccount] = useState<BankAccount | null>(null);
  const [selectedIncomeAccount, setSelectedIncomeAccount] = useState<ChartOfAccount | null>(null);

  useEffect(() => {
    const selected = bankAccounts.find(account => account.id.toString() === values.bank_account_id);
    setSelectedBankAccount(selected || null);
  }, [values.bank_account_id, bankAccounts]);

  useEffect(() => {
    const selected = incomeAccounts.find(account => account.id.toString() === values.income_account_id);
    setSelectedIncomeAccount(selected || null);
  }, [values.income_account_id, incomeAccounts]);

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

    router.post(route('bank-accounts.process-deposit'), values, {
      onError: (errors) => {
        setErrors(errors);
      },
    });
  };

  return (
    <AppLayout title="Make Deposit">
      <Head title="Make Deposit - Tally Software" />

      <div className="mb-6">
        <Link href={route('bank-accounts.index')} className="inline-flex items-center text-blue-600 hover:text-blue-900">
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to Bank Accounts
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h2 className="text-xl font-semibold text-gray-800">Make a Deposit</h2>
        </div>

        <form onSubmit={handleSubmit} className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Left Column */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Deposit Details</h3>

              <div className="mb-4">
                <label htmlFor="bank_account_id" className="block text-sm font-medium text-gray-700 mb-1">
                  Destination Account*
                </label>
                <select
                  id="bank_account_id"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.bank_account_id ? 'border-red-300' : ''
                  }`}
                  value={values.bank_account_id}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select bank account</option>
                  {bankAccounts.map((account) => (
                    <option key={account.id} value={account.id}>
                      {account.bank_name} - {account.account_name}
                    </option>
                  ))}
                </select>
                {errors.bank_account_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.bank_account_id}</p>
                )}
                {selectedBankAccount && (
                  <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md">
                    <p className="text-sm text-blue-800">Current Balance: {companySetting.currency_symbol} {selectedBankAccount.formatted_balance}</p>
                  </div>
                )}
              </div>

              <div className="mb-4">
                <label htmlFor="amount" className="block text-sm font-medium text-gray-700 mb-1">
                  Deposit Amount*
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

              <div className="flex justify-center my-4">
                <div className="p-2 bg-gray-100 rounded-full">
                  <ArrowDown className="h-5 w-5 text-gray-500" />
                </div>
              </div>

              <div className="mb-4">
                <label htmlFor="income_account_id" className="block text-sm font-medium text-gray-700 mb-1">
                  Income Account*
                </label>
                <select
                  id="income_account_id"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.income_account_id ? 'border-red-300' : ''
                  }`}
                  value={values.income_account_id}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select income account</option>
                  {incomeAccounts.map((account) => (
                    <option key={account.id} value={account.id}>
                      {account.account_code} - {account.name}
                    </option>
                  ))}
                </select>
                {errors.income_account_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.income_account_id}</p>
                )}
                <p className="mt-1 text-xs text-gray-500">
                  Select the income account that this money is coming from
                </p>
              </div>
            </div>

            {/* Right Column */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>

              <div className="mb-4">
                <label htmlFor="deposit_date" className="block text-sm font-medium text-gray-700 mb-1">
                  Deposit Date*
                </label>
                <input
                  type="date"
                  id="deposit_date"
                  className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    errors.deposit_date ? 'border-red-300' : ''
                  }`}
                  value={values.deposit_date}
                  onChange={handleChange}
                  required
                />
                {errors.deposit_date && (
                  <p className="mt-1 text-sm text-red-600">{errors.deposit_date}</p>
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
                  placeholder="Enter a description for this deposit"
                  value={values.description}
                  onChange={handleChange}
                ></textarea>
              </div>

              {/* Preview of Transaction */}
              {values.bank_account_id && values.income_account_id && values.amount && (
                <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Transaction Preview</h4>
                  <div className="text-sm text-gray-600">
                    <p className="mb-1">
                      <span className="font-medium">Debit:</span> {selectedBankAccount?.account_name} ({companySetting.currency_symbol} {values.amount})
                    </p>
                    <p>
                      <span className="font-medium">Credit:</span> {selectedIncomeAccount?.name} ({companySetting.currency_symbol} {values.amount})
                    </p>
                  </div>
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
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700"
            >
              Process Deposit
            </button>
          </div>
        </form>
      </div>

      <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex items-start">
        <AlertCircle className="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="text-sm font-medium text-yellow-800">Important Note</h4>
          <p className="mt-1 text-sm text-yellow-700">
            When you make a deposit, a journal entry will be created automatically to record this transaction.
            The bank account will be debited, and the selected income account will be credited.
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
