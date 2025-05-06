import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building, Users, CalendarDays, CoinsIcon, BarChart, FileText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

// Define the types for our props
interface ChartOfAccount {
  id: number;
  account_code: string;
  name: string;
}

interface FinancialYear {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

interface Business {
  id: number;
  name: string;
  code: string;
  legal_name: string | null;
  tax_identification_number: string | null;
  registration_number: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string;
  phone: string | null;
  email: string | null;
  website: string | null;
  is_active: boolean;
  created_by: {
    id: number;
    name: string;
  };
  created_at: string;
  chartOfAccounts: ChartOfAccount[];
  financialYears: FinancialYear[];
}

interface BusinessShowProps {
  business: Business;
}

export default function BusinessShow({ business }: BusinessShowProps) {
  // Format date to a friendly format
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('bn-BD', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(date);
  };

  // Count active accounts
  const activeAccounts = business.chartOfAccounts ? business.chartOfAccounts.length : 0;

  // Find active financial year
  const activeFinancialYear = business.financialYears ?
    business.financialYears.find(year => year.is_active) : null;

  return (
    <AppLayout title={`${business.name} বিবরণ`}>
      <Head title={`${business.name} - Tally Software`} />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('businesses.index')}
          className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          ব্যবসা তালিকায় ফিরে যান
        </Link>
        <div className="flex space-x-3">
          <Link
            href={route('admin.businesses.users', business.id)}
            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <Users className="h-4 w-4 mr-2" />
            ব্যবহারকারী পরিচালনা
          </Link>
          <Link
            href={route('businesses.edit', business.id)}
            className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            সম্পাদনা করুন
          </Link>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
        <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
          <div className="flex justify-between items-center">
            <div className="flex items-center">
              <div className="flex-shrink-0 h-12 w-12 rounded-md bg-blue-100 flex items-center justify-center">
                <Building className="h-6 w-6 text-blue-600" />
              </div>
              <div className="ml-4">
                <h3 className="text-lg leading-6 font-medium text-gray-900">
                  {business.name}
                </h3>
                <p className="text-sm text-gray-500">
                  কোড: {business.code}
                  {business.is_active ? (
                    <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      সক্রিয়
                    </span>
                  ) : (
                    <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                      নিষ্ক্রিয়
                    </span>
                  )}
                </p>
              </div>
            </div>
          </div>
        </div>
        <div className="border-t border-gray-200">
          <dl>
            <div className="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                আইনি নাম
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {business.legal_name || 'অনির্ধারিত'}
              </dd>
            </div>
            <div className="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                ট্যাক্স আইডি নম্বর
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {business.tax_identification_number || 'অনির্ধারিত'}
              </dd>
            </div>
            <div className="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                রেজিস্ট্রেশন নম্বর
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {business.registration_number || 'অনির্ধারিত'}
              </dd>
            </div>
            <div className="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                ঠিকানা
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {business.address ? (
                  <>
                    {business.address}
                    {business.city && `, ${business.city}`}
                    {business.state && `, ${business.state}`}
                    {business.postal_code && ` ${business.postal_code}`}
                    {business.country && `, ${business.country}`}
                  </>
                ) : (
                  'অনির্ধারিত'
                )}
              </dd>
            </div>
            <div className="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                যোগাযোগ তথ্য
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                <div>
                  <span className="font-medium">ফোন:</span> {business.phone || 'অনির্ধারিত'}
                </div>
                <div>
                  <span className="font-medium">ইমেইল:</span> {business.email || 'অনির্ধারিত'}
                </div>
                <div>
                  <span className="font-medium">ওয়েবসাইট:</span> {business.website || 'অনির্ধারিত'}
                </div>
              </dd>
            </div>
            <div className="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">
                তৈরি করেছেন
              </dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {business.created_by?.name || 'অনির্ধারিত'} <span className="text-gray-500">({formatDate(business.created_at)})</span>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        {/* Account Statistics Card */}
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="px-4 py-5 sm:p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0 bg-blue-100 rounded-md p-3">
                <CoinsIcon className="h-6 w-6 text-blue-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    মোট হিসাব
                  </dt>
                  <dd>
                    <div className="text-lg font-medium text-gray-900">
                      {activeAccounts}
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
            <div className="mt-4">
              <Link
                href={route('chart-of-accounts.index')}
                className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                হিসাব দেখুন
              </Link>
            </div>
          </div>
        </div>

        {/* Financial Year Card */}
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="px-4 py-5 sm:p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0 bg-green-100 rounded-md p-3">
                <CalendarDays className="h-6 w-6 text-green-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    সক্রিয় অর্থবছর
                  </dt>
                  <dd>
                    <div className="text-lg font-medium text-gray-900">
                      {activeFinancialYear ? activeFinancialYear.name : 'কোন সক্রিয় অর্থবছর নেই'}
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
            <div className="mt-4">
              <Link
                href={route('financial-years.index')}
                className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
              >
                অর্থবছর দেখুন
              </Link>
            </div>
          </div>
        </div>

        {/* Reports Card */}
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="px-4 py-5 sm:p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0 bg-purple-100 rounded-md p-3">
                <BarChart className="h-6 w-6 text-purple-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    ফিনান্সিয়াল রিপোর্ট
                  </dt>
                  <dd>
                    <div className="text-lg font-medium text-gray-900">
                      প্রতিবেদন তৈরি করুন
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
            <div className="mt-4">
              <Link
                href={route('reports.index')}
                className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                রিপোর্ট দেখুন
              </Link>
            </div>
          </div>
        </div>
      </div>

      {/* Financial Years Section */}
      {business.financialYears && business.financialYears.length > 0 && (
        <div className="mt-8">
          <div className="bg-white shadow overflow-hidden sm:rounded-lg">
            <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <CalendarDays className="h-5 w-5 text-gray-500 mr-2" />
                  <h3 className="text-lg leading-6 font-medium text-gray-900">
                    অর্থবছর
                  </h3>
                </div>
                <Link
                  href={route('financial-years.create')}
                  className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  নতুন অর্থবছর
                </Link>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      নাম
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      শুরুর তারিখ
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      শেষের তারিখ
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      স্ট্যাটাস
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {business.financialYears.map((year) => (
                    <tr key={year.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-gray-900">{year.name}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{formatDate(year.start_date)}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{formatDate(year.end_date)}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {year.is_active ? (
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            সক্রিয়
                          </span>
                        ) : (
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                            সক্রিয় নয়
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
