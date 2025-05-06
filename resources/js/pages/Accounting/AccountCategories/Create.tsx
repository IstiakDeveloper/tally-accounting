import React, { FormEvent } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import BusinessSelector from '@/components/BusinessSelector';

interface AccountCategoriesCreateProps {
    types: {
        Asset: string;
        Liability: string;
        Equity: string;
        Revenue: string;
        Expense: string;
    };
    businesses: any[];
    activeBusiness: any;
}

export default function AccountCategoriesCreate({ types, businesses, activeBusiness }: AccountCategoriesCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('account-categories.store'));
    };

    return (
        <AppLayout title="Create Account Category">
            <Head title="Create Account Category - Tally Software" />

            <div className="flex justify-between items-center mb-6">
                <Link
                    href={route('account-categories.index')}
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
                        <div className="space-y-6">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                    Category Name <span className="text-red-500">*</span>
                                </label>
                                <div>
                                    <input
                                        type="text"
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${errors.name ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                            }`}
                                        placeholder="Example: Current Assets"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                    )}
                                </div>
                            </div>

                            <input
                                type="hidden"
                                name="business_id"
                                value={activeBusiness?.id || ''}
                            />

                            <div>
                                <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-1">
                                    Category Type <span className="text-red-500">*</span>
                                </label>
                                <div>
                                    <select
                                        id="type"
                                        value={data.type}
                                        onChange={(e) => setData('type', e.target.value)}
                                        className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${errors.type ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                                            }`}
                                        required
                                    >
                                        <option value="">Select Category Type</option>
                                        {Object.entries(types).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {value} ({label})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.type && (
                                        <p className="mt-1 text-sm text-red-600">{errors.type}</p>
                                    )}
                                </div>
                                <p className="mt-2 text-sm text-gray-500">
                                    Choose the appropriate type for this account category. This determines how accounts in this category affect the financial statements.
                                </p>
                            </div>
                        </div>

                        <div className="mt-8 pt-5 border-t border-gray-200 flex justify-end gap-3">
                            <Link
                                href={route('account-categories.index')}
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
        </AppLayout>
    );
}
