import React, { FormEvent, useState, useEffect } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Save,
    AlertTriangle,
    Calendar,
    Plus,
    Trash2,
    PlusCircle,
    MinusCircle,
    Info
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { ChartOfAccount, FinancialYear } from '@/types';

interface JournalItem {
    id?: number;
    account_id: string;
    type: 'debit' | 'credit';
    amount: string;
    description: string;
}

interface JournalEntriesCreateProps {
    financialYear: FinancialYear;
    accounts: ChartOfAccount[];
    referenceNumber: string;
    today: string;
}

export default function JournalEntriesCreate({
    financialYear,
    accounts,
    referenceNumber,
    today
}: JournalEntriesCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        reference_number: referenceNumber,
        financial_year_id: financialYear.id.toString(),
        entry_date: today,
        narration: '',
        items: [
            { account_id: '', type: 'debit', amount: '', description: '' },
            { account_id: '', type: 'credit', amount: '', description: '' }
        ] as JournalItem[]
    });

    const [debitTotal, setDebitTotal] = useState(0);
    const [creditTotal, setCreditTotal] = useState(0);
    const [isBalanced, setIsBalanced] = useState(false);

    // Group accounts by category type
    const groupedAccounts = accounts.reduce((acc, account) => {
        const type = account.category.type;
        if (!acc[type]) {
            acc[type] = [];
        }
        acc[type].push(account);
        return acc;
    }, {} as Record<string, ChartOfAccount[]>);

    // Order of account types
    const accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

    // Calculate totals whenever items change
    useEffect(() => {
        let debitSum = 0;
        let creditSum = 0;

        data.items.forEach(item => {
            const amount = parseFloat(item.amount) || 0;
            if (item.type === 'debit') {
                debitSum += amount;
            } else {
                creditSum += amount;
            }
        });

        setDebitTotal(debitSum);
        setCreditTotal(creditSum);
        setIsBalanced(Math.abs(debitSum - creditSum) < 0.01);
    }, [data.items]);

    const handleItemChange = (index: number, field: keyof JournalItem, value: string) => {
        const updatedItems = [...data.items];
        updatedItems[index] = { ...updatedItems[index], [field]: value };
        setData('items', updatedItems);
    };

    const addItem = (type: 'debit' | 'credit') => {
        const updatedItems = [...data.items, { account_id: '', type, amount: '', description: '' }];
        setData('items', updatedItems);
    };

    const removeItem = (index: number) => {
        if (data.items.length <= 2) {
            return; // Maintain minimum of 2 items (1 debit, 1 credit)
        }

        const updatedItems = data.items.filter((_, i) => i !== index);
        setData('items', updatedItems);
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'BDT',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!isBalanced) {
            return; // Prevent submission if not balanced
        }
        post(route('journal-entries.store'));
    };

    return (
        <AppLayout title="Create Journal Entry">
            <Head title="Create Journal Entry - Tally Software" />

            <div className="flex justify-between items-center mb-6">
                <Link
                    href={route('journal-entries.index')}
                    className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Back to Journal Entries
                </Link>
            </div>

            <div className="bg-white rounded-lg shadow-md overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900">New Journal Entry</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Financial Year: {financialYear.name} ({new Date(financialYear.start_date).toLocaleDateString()} - {new Date(financialYear.end_date).toLocaleDateString()})
                    </p>
                </div>

                <div className="px-6 py-4">
                    {Object.keys(errors).length > 0 && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div className="flex items-center">
                                <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                                <span className="text-red-800 font-medium">There were errors with your submission</span>
                            </div>
                            {errors.items && (
                                <p className="mt-2 text-sm text-red-700">{errors.items}</p>
                            )}
                            {Object.entries(errors)
                                .filter(([key]) => key !== 'items' && !key.startsWith('items.'))
                                .map(([field, error]) => (
                                    <p key={field} className="mt-1 text-sm text-red-700">{error}</p>
                                ))}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label htmlFor="reference_number" className="block text-sm font-medium text-gray-700 mb-1">
                                        Reference Number <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="reference_number"
                                        value={data.reference_number}
                                        onChange={(e) => setData('reference_number', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${errors.reference_number ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                            }`}
                                        required
                                    />
                                    {errors.reference_number && (
                                        <p className="mt-1 text-sm text-red-600">{errors.reference_number}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="entry_date" className="block text-sm font-medium text-gray-700 mb-1">
                                        Entry Date <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <Calendar className="h-5 w-5 text-gray-400" />
                                        </div>
                                        <input
                                            type="date"
                                            id="entry_date"
                                            value={data.entry_date}
                                            onChange={(e) => setData('entry_date', e.target.value)}
                                            className={`h-10 pl-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${errors.entry_date ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                                }`}
                                            required
                                        />
                                        {errors.entry_date && (
                                            <p className="mt-1 text-sm text-red-600">{errors.entry_date}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label htmlFor="narration" className="block text-sm font-medium text-gray-700 mb-1">
                                    Description/Narration <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    id="narration"
                                    value={data.narration}
                                    onChange={(e) => setData('narration', e.target.value)}
                                    rows={2}
                                    className={`px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${errors.narration ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                        }`}
                                    placeholder="Enter description or purpose of this journal entry"
                                    required
                                ></textarea>
                                {errors.narration && (
                                    <p className="mt-1 text-sm text-red-600">{errors.narration}</p>
                                )}
                            </div>

                            {/* Journal Items */}
                            <div className="mt-6">
                                <div className="flex justify-between items-center mb-2">
                                    <h4 className="text-base font-medium text-gray-900">Journal Items</h4>
                                    <div className="flex space-x-2">
                                        <button
                                            type="button"
                                            onClick={() => addItem('debit')}
                                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        >
                                            <PlusCircle className="h-4 w-4 mr-1" />
                                            Add Debit
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => addItem('credit')}
                                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            <PlusCircle className="h-4 w-4 mr-1" />
                                            Add Credit
                                        </button>
                                    </div>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Description
                                                </th>
                                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {data.items.map((item, index) => (
                                                <tr key={index} className={item.type === 'debit' ? 'bg-green-50' : 'bg-blue-50'}>
                                                    <td className="px-6 py-4">
                                                        <select
                                                            value={item.account_id}
                                                            onChange={(e) => handleItemChange(index, 'account_id', e.target.value)}
                                                            className={`w-full focus:ring-blue-500 focus:border-blue-500 text-sm border-gray-300 rounded-md ${errors[`items.${index}.account_id`] ? 'border-red-300' : ''
                                                                }`}
                                                            required
                                                        >
                                                            <option value="">Select Account</option>
                                                            {accountTypes.map(type => (
                                                                groupedAccounts[type] && (
                                                                    <optgroup key={type} label={type}>
                                                                        {groupedAccounts[type].map(account => (
                                                                            <option key={account.id} value={account.id.toString()}>
                                                                                {account.account_code} - {account.name}
                                                                            </option>
                                                                        ))}
                                                                    </optgroup>
                                                                )
                                                            ))}
                                                        </select>
                                                        {errors[`items.${index}.account_id`] && (
                                                            <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.account_id`]}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <select
                                                            value={item.type}
                                                            onChange={(e) => handleItemChange(index, 'type', e.target.value as 'debit' | 'credit')}
                                                            className={`w-full focus:ring-blue-500 focus:border-blue-500 text-sm border-gray-300 rounded-md ${errors[`items.${index}.type`] ? 'border-red-300' : ''
                                                                }`}
                                                            required
                                                        >
                                                            <option value="debit">Debit</option>
                                                            <option value="credit">Credit</option>
                                                        </select>
                                                        {errors[`items.${index}.type`] && (
                                                            <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.type`]}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            value={item.amount}
                                                            onChange={(e) => handleItemChange(index, 'amount', e.target.value)}
                                                            className={`w-full focus:ring-blue-500 focus:border-blue-500 text-sm border-gray-300 rounded-md ${errors[`items.${index}.amount`] ? 'border-red-300' : ''
                                                                }`}
                                                            placeholder="0.00"
                                                            required
                                                        />
                                                        {errors[`items.${index}.amount`] && (
                                                            <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.amount`]}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <input
                                                            type="text"
                                                            value={item.description}
                                                            onChange={(e) => handleItemChange(index, 'description', e.target.value)}
                                                            className={`w-full focus:ring-blue-500 focus:border-blue-500 text-sm border-gray-300 rounded-md ${errors[`items.${index}.description`] ? 'border-red-300' : ''
                                                                }`}
                                                            placeholder="Optional description"
                                                        />
                                                        {errors[`items.${index}.description`] && (
                                                            <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.description`]}</p>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => removeItem(index)}
                                                            className="text-red-600 hover:text-red-900"
                                                            disabled={data.items.length <= 2}
                                                            title={data.items.length <= 2 ? "Minimum 2 items required" : "Remove item"}
                                                        >
                                                            <Trash2 className={`h-5 w-5 ${data.items.length <= 2 ? 'opacity-50 cursor-not-allowed' : ''}`} />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="bg-gray-50">
                                            <tr>
                                                <td colSpan={2} className="px-6 py-4 text-right font-medium">Totals:</td>
                                                <td className="px-6 py-4">
                                                    <div className="flex justify-between">
                                                        <span className="font-medium text-green-700">Debit: {formatCurrency(debitTotal)}</span>
                                                        <span className="font-medium text-blue-700">Credit: {formatCurrency(creditTotal)}</span>
                                                    </div>
                                                </td>
                                                <td colSpan={2} className="px-6 py-4">
                                                    <div className="flex items-center">
                                                        {isBalanced ? (
                                                            <span className="text-green-600 text-sm font-medium flex items-center">
                                                                <Info className="h-4 w-4 mr-1" />
                                                                Balanced
                                                            </span>
                                                        ) : (
                                                            <span className="text-red-600 text-sm font-medium flex items-center">
                                                                <AlertTriangle className="h-4 w-4 mr-1" />
                                                                Unbalanced: {formatCurrency(Math.abs(debitTotal - creditTotal))}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8 pt-5 border-t border-gray-200 flex justify-end gap-3">
                            <Link
                                href={route('journal-entries.index')}
                                className="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing || !isBalanced}
                                className={`px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors ${isBalanced ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'
                                    }`}
                                title={!isBalanced ? "Journal entry must be balanced before saving" : ""}
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
                                        Save Journal Entry
                                    </span>
                                )}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
