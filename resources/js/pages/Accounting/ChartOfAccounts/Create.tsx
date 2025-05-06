import React, { FormEvent, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, AlertTriangle, HelpCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { AccountCategory } from '@/types';
import BusinessSelector from '@/components/BusinessSelector';

interface ChartOfAccountsCreateProps {
  categories: AccountCategory[];
  businesses: any[];
  activeBusiness: any;
}

export default function ChartOfAccountsCreate({ categories, businesses, activeBusiness }: ChartOfAccountsCreateProps) {
  const { data, setData, post, processing, errors } = useForm({
    account_code: '',
    name: '',
    category_id: '',
    description: '',
    is_active: true,
  });

  const [selectedCategory, setSelectedCategory] = useState<AccountCategory | null>(null);

  const handleCategoryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const categoryId = e.target.value;
    setData('category_id', categoryId);

    if (categoryId) {
      const category = categories.find(c => c.id === parseInt(categoryId));
      setSelectedCategory(category || null);
    } else {
      setSelectedCategory(null);
    }
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(route('chart-of-accounts.store'));
  };

  // Group categories by type
  const groupedCategories = categories.reduce((acc, category) => {
    if (!acc[category.type]) {
      acc[category.type] = [];
    }
    acc[category.type].push(category);
    return acc;
  }, {} as Record<string, AccountCategory[]>);

  // Order of category types
  const categoryTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

  return (
    <AppLayout title="Create Account">
      <Head title="Create Account - Tally Software" />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('chart-of-accounts.index')}
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
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label htmlFor="account_code" className="block text-sm font-medium text-gray-700 mb-1">
                    Account Code <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="text"
                      id="account_code"
                      value={data.account_code}
                      onChange={(e) => setData('account_code', e.target.value)}
                      className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                        errors.account_code ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                      }`}
                      placeholder="Example: 1010"
                      required
                    />
                    {errors.account_code && (
                      <p className="mt-1 text-sm text-red-600">{errors.account_code}</p>
                    )}
                    <p className="mt-1 text-xs text-gray-500">
                      Unique code for this account. We recommend using a structured coding system.
                    </p>
                  </div>
                </div>

                <div>
                  <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                    Account Name <span className="text-red-500">*</span>
                  </label>
                  <div>
                    <input
                      type="text"
                      id="name"
                      value={data.name}
                      onChange={(e) => setData('name', e.target.value)}
                      className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                        errors.name ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                      }`}
                      placeholder="Example: Cash on Hand"
                      required
                    />
                    {errors.name && (
                      <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                    )}
                  </div>
                </div>
              </div>

              <div>
                <label htmlFor="category_id" className="block text-sm font-medium text-gray-700 mb-1">
                  Category <span className="text-red-500">*</span>
                </label>
                <div>
                  <select
                    id="category_id"
                    value={data.category_id}
                    onChange={handleCategoryChange}
                    className={`h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                      errors.category_id ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                    }`}
                    required
                  >
                    <option value="">Select Account Category</option>
                    {categoryTypes.map(type => (
                      groupedCategories[type] && (
                        <optgroup key={type} label={type}>
                          {groupedCategories[type].map(category => (
                            <option key={category.id} value={category.id.toString()}>
                              {category.name}
                            </option>
                          ))}
                        </optgroup>
                      )
                    ))}
                  </select>
                  {errors.category_id && (
                    <p className="mt-1 text-sm text-red-600">{errors.category_id}</p>
                  )}
                </div>
                {selectedCategory && (
                  <div className="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                    <div className="flex items-start">
                      <div className="flex-shrink-0 mt-0.5">
                        <HelpCircle className="h-4 w-4 text-blue-500" />
                      </div>
                      <div className="ml-2">
                        <p className="text-sm text-blue-700">
                          <strong>{selectedCategory.type}</strong> type account.
                          {selectedCategory.type === 'Asset' && " Typically has a debit balance. Increases with debits, decreases with credits."}
                          {selectedCategory.type === 'Liability' && " Typically has a credit balance. Increases with credits, decreases with debits."}
                          {selectedCategory.type === 'Equity' && " Typically has a credit balance. Increases with credits, decreases with debits."}
                          {selectedCategory.type === 'Revenue' && " Typically has a credit balance. Increases with credits, decreases with debits."}
                          {selectedCategory.type === 'Expense' && " Typically has a debit balance. Increases with debits, decreases with credits."}
                        </p>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              <div>
                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <div>
                  <textarea
                    id="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={3}
                    className={`px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md ${
                      errors.description ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : ''
                    }`}
                    placeholder="Optional description for this account"
                  ></textarea>
                  {errors.description && (
                    <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                  )}
                </div>
              </div>

              <div className="flex items-start">
                <div className="flex items-center h-5">
                  <input
                    id="is_active"
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-5 w-5 focus:ring-blue-500 text-blue-600 border-gray-300 rounded"
                  />
                </div>
                <div className="ml-3 text-sm">
                  <label htmlFor="is_active" className="font-medium text-gray-700">
                    Active Account
                  </label>
                  <p className="text-gray-500">
                    Inactive accounts are not available for selection in transactions.
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-8 pt-5 border-t border-gray-200 flex justify-end gap-3">
              <Link
                href={route('chart-of-accounts.index')}
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
