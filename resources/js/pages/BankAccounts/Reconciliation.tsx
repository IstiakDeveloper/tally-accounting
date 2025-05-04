import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertCircle, CornerDownRight, HelpCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankReconciliationProps } from '@/types';

export default function BankReconciliation({
  bankAccount,
  systemBalance,
  formattedSystemBalance,
  companySetting
}: BankReconciliationProps) {
  const [values, setValues] = useState({
    statement_balance: '',
    reconciliation_date: new Date().toISOString().split('T')[0],
    adjustment_amount: '0.00',
    adjustment_description: '',
  });

  const [errors, setErrors] = useState<{
    statement_balance?: string;
    reconciliation_date?: string;
    adjustment_amount?: string;
    adjustment_description?: string;
  }>({});

  // Calculate adjustment amount when statement balance changes
  useEffect(() => {
    if (values.statement_balance) {
      const statementBal = parseFloat(values.statement_balance);
      const difference = statementBal - systemBalance;
      setValues(prev => ({
        ...prev,
        adjustment_amount: difference.toFixed(2)
      }));
    }
  }, [values.statement_balance, systemBalance]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const key = e.target.id;
    const value = e.target.value;

    setValues(values => ({
      ...values,
      [key]: value
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    router.post(route('bank-accounts.process-reconciliation', bankAccount.id), values, {
      onError: (errors) => {
        setErrors(errors);
      },
    });
  };

  // Format currency
  const formatCurrency = (amount: number | string) => {
    const numericAmount = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: companySetting.currency || 'BDT',
      minimumFractionDigits: 2
    }).format(numericAmount);
  };

  return (
    <AppLayout title={`Reconcile: ${bankAccount.account_name}`}>
      <Head title={`Reconcile ${bankAccount.account_name} - Tally Software`} />

      <div className="mb-6">
        <Link
          href={route('bank-accounts.show', bankAccount.id)}
          className="inline-flex items-center text-blue-600 hover:text-blue-900"
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to Account Details
        </Link>
      </div>

      <div className="bg-white shadow-md rounded-lg overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h2 className="text-xl font-semibold text-gray-800">Bank Account Reconciliation</h2>
        </div>

        <div className="p-6">
          <div className="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
            <div className="flex">
              <div className="flex-shrink-0">
                <HelpCircle className="h-5 w-5 text-blue-400" />
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-blue-800">What is reconciliation?</h3>
                <div className="mt-2 text-sm text-blue-700">
                  <p>
                    Bank reconciliation is the process of comparing your accounting records with your bank statement to ensure they match.
                    If there's a difference, an adjustment entry can be created to align your records with the bank's records.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Left Column - Account Details */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Account Information</h3>

              <div className="bg-gray-50 border border-gray-200 rounded-md p-4">
                <dl className="grid grid-cols-1 gap-x-4 gap-y-3">
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Bank Account</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_name}</dd>
                  </div>
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Account Number</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.account_number}</dd>
                  </div>
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Bank Name</dt>
                    <dd className="mt-1 text-sm text-gray-900">{bankAccount.bank_name}</dd>
                  </div>
                  <div className="border-t border-gray-200 pt-3">
                    <dt className="text-sm font-medium text-gray-500">Current System Balance</dt>
                    <dd className="mt-1 text-lg font-bold text-blue-600">{formattedSystemBalance}</dd>
                  </div>
                </dl>
              </div>
            </div>

            {/* Right Column - Reconciliation Form */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Reconciliation Details</h3>

              <form onSubmit={handleSubmit}>
                <div className="mb-4">
                  <label htmlFor="statement_balance" className="block text-sm font-medium text-gray-700 mb-1">
                    Bank Statement Balance*
                  </label>
                  <div className="relative rounded-md shadow-sm">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span className="text-gray-500 sm:text-sm">{companySetting.currency_symbol}</span>
                    </div>
                    <input
                      type="number"
                      step="0.01"
                      id="statement_balance"
                      className={`block w-full pl-7 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                        errors.statement_balance ? 'border-red-300' : ''
                      }`}
                      placeholder="0.00"
                      value={values.statement_balance}
                      onChange={handleChange}
                      required
                    />
                  </div>
                  {errors.statement_balance && (
                    <p className="mt-1 text-sm text-red-600">{errors.statement_balance}</p>
                  )}
                  <p className="mt-1 text-xs text-gray-500">
                    Enter the closing balance from your latest bank statement
                  </p>
                </div>

                <div className="mb-4">
                  <label htmlFor="reconciliation_date" className="block text-sm font-medium text-gray-700 mb-1">
                    Reconciliation Date*
                  </label>
                  <input
                    type="date"
                    id="reconciliation_date"
                    className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                      errors.reconciliation_date ? 'border-red-300' : ''
                    }`}
                    value={values.reconciliation_date}
                    onChange={handleChange}
                    required
                  />
                  {errors.reconciliation_date && (
                    <p className="mt-1 text-sm text-red-600">{errors.reconciliation_date}</p>
                  )}
                </div>

                {/* Adjustment Amount (Calculated) */}
                <div className="mb-4">
                  <div className="flex justify-between items-center mb-1">
                    <label htmlFor="adjustment_amount" className="block text-sm font-medium text-gray-700">
                      Adjustment Amount
                    </label>
                    <span className="text-xs text-gray-500">Calculated automatically</span>
                  </div>
                  <div className="relative rounded-md shadow-sm">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span className="text-gray-500 sm:text-sm">{companySetting.currency_symbol}</span>
                    </div>
                    <input
                      type="number"
                      step="0.01"
                      id="adjustment_amount"
                      className={`block w-full pl-7 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-gray-50 ${
                        errors.adjustment_amount ? 'border-red-300' : ''
                      }`}
                      value={values.adjustment_amount}
                      onChange={handleChange}
                      required
                      readOnly
                    />
                  </div>
                  {errors.adjustment_amount && (
                    <p className="mt-1 text-sm text-red-600">{errors.adjustment_amount}</p>
                  )}
                  <div className="mt-1 flex items-center">
                    <CornerDownRight className="h-4 w-4 text-gray-400 mr-1" />
                    <span className="text-xs text-gray-500">
                      {parseFloat(values.adjustment_amount) > 0
                        ? 'Your records show less than the bank statement'
                        : parseFloat(values.adjustment_amount) < 0
                          ? 'Your records show more than the bank statement'
                          : 'No adjustment needed'
                      }
                    </span>
                  </div>
                </div>

                <div className="mb-4">
                  <label htmlFor="adjustment_description" className="block text-sm font-medium text-gray-700 mb-1">
                    Adjustment Description*
                  </label>
                  <textarea
                    id="adjustment_description"
                    rows={3}
                    className={`block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                      errors.adjustment_description ? 'border-red-300' : ''
                    }`}
                    placeholder="Explain the reason for this adjustment"
                    value={values.adjustment_description}
                    onChange={handleChange}
                    required
                  ></textarea>
                  {errors.adjustment_description && (
                    <p className="mt-1 text-sm text-red-600">{errors.adjustment_description}</p>
                  )}
                </div>

                {/* Preview of Adjustment */}
                {values.statement_balance && parseFloat(values.adjustment_amount) !== 0 && (
                  <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md mb-4">
                    <h4 className="text-sm font-medium text-yellow-800 mb-2">Adjustment Preview</h4>
                    <div className="text-sm text-yellow-700">
                      <p>
                        This action will create a journal entry to {parseFloat(values.adjustment_amount) > 0 ? 'increase' : 'decrease'} your
                        account balance by {formatCurrency(Math.abs(parseFloat(values.adjustment_amount)))}.
                      </p>
                      <p className="mt-2">
                        <strong>New Balance After Adjustment:</strong> {formatCurrency(parseFloat(values.statement_balance))}
                      </p>
                    </div>
                  </div>
                )}

                <div className="mt-6 flex justify-end space-x-3">
                  <Link
                    href={route('bank-accounts.show', bankAccount.id)}
                    className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  >
                    Cancel
                  </Link>
                  <button
                    type="submit"
                    className={`inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white ${
                      parseFloat(values.adjustment_amount) === 0
                        ? 'bg-gray-400 cursor-not-allowed'
                        : 'bg-blue-600 hover:bg-blue-700'
                    }`}
                    disabled={parseFloat(values.adjustment_amount) === 0}
                  >
                    Process Reconciliation
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex items-start">
        <AlertCircle className="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="text-sm font-medium text-yellow-800">Important Note</h4>
          <p className="mt-1 text-sm text-yellow-700">
            Bank reconciliation will create a journal entry that adjusts your accounting records to match the bank statement.
            This process helps to catch errors, missing transactions, or bank fees that may not have been recorded in your system.
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
