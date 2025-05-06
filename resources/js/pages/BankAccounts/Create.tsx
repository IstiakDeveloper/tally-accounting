import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { BankAccountFormProps } from '@/types';
import BusinessSelector from '@/components/BusinessSelector';

interface ExtendedBankAccountFormProps extends BankAccountFormProps {
    businesses: any[];
    activeBusiness: any;
}

export default function BankAccountCreate({ chartAccounts, businesses, activeBusiness }: ExtendedBankAccountFormProps) {
    const { data, setData, post, processing, errors } = useForm({
        account_name: '',
        account_number: '',
        bank_name: '',
        branch_name: '',
        swift_code: '',
        routing_number: '',
        address: '',
        contact_person: '',
        contact_number: '',
        account_id: '',
        is_active: true,
        initial_balance: '0.00',
        business_id: activeBusiness?.id || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('bank-accounts.store'));
    };

    return (
        <AppLayout title="Create Bank Account">
            <Head title="Create Bank Account - Tally Software" />

            <div className="flex justify-between items-center mb-6">
                <Link
                    href={route('bank-accounts.index')}
                    className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Go Back
                </Link>

                <BusinessSelector businesses={businesses} activeBusiness={activeBusiness} />
            </div>

            <div className="bg-white rounded-lg shadow-md overflow-hidden">
                <div className="px-8 py-6">
                    {Object.keys(errors).length > 0 && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div className="flex items-center">
                                <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                                <span className="text-red-800 font-medium">There were errors with your submission</span>
                            </div>
                            {Object.entries(errors).map(([field, error]) => (
                                <p key={field} className="mt-2 text-sm text-red-700">{error}</p>
                            ))}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Account Information</h3>

                                <div className="mb-4">
                                    <label htmlFor="account_name" className="block text-sm font-medium text-gray-700 mb-1">
                                        Account Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="account_name"
                                        value={data.account_name}
                                        onChange={(e) => setData('account_name', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                                            errors.account_name ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                        }`}
                                        placeholder="Example: Main Checking Account"
                                        required
                                    />
                                    {errors.account_name && (
                                        <p className="mt-1 text-sm text-red-600">{errors.account_name}</p>
                                    )}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="account_number" className="block text-sm font-medium text-gray-700 mb-1">
                                        Account Number <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="account_number"
                                        value={data.account_number}
                                        onChange={(e) => setData('account_number', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                                            errors.account_number ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                        }`}
                                        placeholder="Example: 123456789"
                                        required
                                    />
                                    {errors.account_number && (
                                        <p className="mt-1 text-sm text-red-600">{errors.account_number}</p>
                                    )}
                                </div>

                                <input
                                    type="hidden"
                                    name="business_id"
                                    value={activeBusiness?.id || ''}
                                />

                                <div className="mb-4">
                                    <label htmlFor="bank_name" className="block text-sm font-medium text-gray-700 mb-1">
                                        Bank Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="bank_name"
                                        value={data.bank_name}
                                        onChange={(e) => setData('bank_name', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                                            errors.bank_name ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                        }`}
                                        placeholder="Example: HSBC Bank"
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
                                        value={data.branch_name}
                                        onChange={(e) => setData('branch_name', e.target.value)}
                                        className="h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                        placeholder="Example: Downtown Branch"
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
                                            value={data.swift_code}
                                            onChange={(e) => setData('swift_code', e.target.value)}
                                            className="h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                            placeholder="Example: HSBCUS33"
                                        />
                                    </div>

                                    <div className="mb-4">
                                        <label htmlFor="routing_number" className="block text-sm font-medium text-gray-700 mb-1">
                                            Routing Number
                                        </label>
                                        <input
                                            type="text"
                                            id="routing_number"
                                            value={data.routing_number}
                                            onChange={(e) => setData('routing_number', e.target.value)}
                                            className="h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                            placeholder="Example: 021000021"
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
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        className="h-auto px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                        placeholder="Bank branch address"
                                    ></textarea>
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="contact_person" className="block text-sm font-medium text-gray-700 mb-1">
                                        Contact Person
                                    </label>
                                    <input
                                        type="text"
                                        id="contact_person"
                                        value={data.contact_person}
                                        onChange={(e) => setData('contact_person', e.target.value)}
                                        className="h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                        placeholder="Example: John Smith"
                                    />
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="contact_number" className="block text-sm font-medium text-gray-700 mb-1">
                                        Contact Number
                                    </label>
                                    <input
                                        type="text"
                                        id="contact_number"
                                        value={data.contact_number}
                                        onChange={(e) => setData('contact_number', e.target.value)}
                                        className="h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                                        placeholder="Example: +1 (123) 456-7890"
                                    />
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="account_id" className="block text-sm font-medium text-gray-700 mb-1">
                                        Associated Chart of Account <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        id="account_id"
                                        value={data.account_id}
                                        onChange={(e) => setData('account_id', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                                            errors.account_id ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                        }`}
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
                                    <p className="mt-2 text-sm text-gray-500">
                                        Select an asset account to associate with this bank account
                                    </p>
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="initial_balance" className="block text-sm font-medium text-gray-700 mb-1">
                                        Initial Balance
                                    </label>
                                    <div className="relative rounded-md shadow-sm">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span className="text-gray-500 sm:text-sm">৳</span>
                                        </div>
                                        <input
                                            type="number"
                                            step="0.01"
                                            id="initial_balance"
                                            value={data.initial_balance}
                                            onChange={(e) => setData('initial_balance', e.target.value)}
                                            className={`h-10 pl-7 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                                                errors.initial_balance ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                            }`}
                                        />
                                    </div>
                                    {errors.initial_balance && (
                                        <p className="mt-1 text-sm text-red-600">{errors.initial_balance}</p>
                                    )}
                                    <p className="mt-2 text-sm text-gray-500">
                                        Enter the initial balance of this account. A journal entry will be created automatically.
                                    </p>
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        checked={data.is_active}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8 pt-5 border-t border-gray-200 flex justify-end gap-3">
                            <Link
                                href={route('bank-accounts.index')}
                                className="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                            >
                                {processing ? (
                                    <span className="flex items-center">
                                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                ) : (
                                    <span className="flex items-center">
                                        <Save className="mr-2 h-4 w-4" />
                                        Save
                                    </span>
                                )}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex items-start">
                <AlertTriangle className="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
                <div>
                    <h4 className="text-sm font-medium text-yellow-800">Important Note</h4>
                    <p className="mt-1 text-sm text-yellow-700">
                        Make sure to select the appropriate Chart of Account for this bank account. This will be used for all financial transactions related to this account.
                        If you provide an initial balance, a journal entry will be created automatically to reflect this balance.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
