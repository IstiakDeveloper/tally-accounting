import React, { FormEvent, useEffect, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Save, AlertTriangle, Info } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import BusinessSelector from '@/components/BusinessSelector';

interface Business {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
}

interface FinancialYearCreateProps {
  businesses: Business[];
  activeBusiness: Business | null;
}

export default function FinancialYearCreate({ businesses, activeBusiness }: FinancialYearCreateProps) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    start_date: '',
    end_date: '',
    is_active: false,
  });

  const [dateWarning, setDateWarning] = useState<string | null>(null);

  // Check for common financial year patterns and suggest name
  useEffect(() => {
    if (data.start_date && data.end_date) {
      const startYear = new Date(data.start_date).getFullYear();
      const endYear = new Date(data.end_date).getFullYear();

      // If name is empty, suggest a name based on the years
      if (!data.name && startYear && endYear) {
        if (startYear === endYear) {
          setData('name', `${startYear}`);
        } else {
          setData('name', `${startYear}-${endYear}`);
        }
      }

      // Validate year difference - financial years typically span 1 year
      if (endYear - startYear > 1) {
        setDateWarning('Financial years typically span 1 year. Please check your dates.');
      } else {
        setDateWarning(null);
      }
    }
  }, [data.start_date, data.end_date]);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(route('financial-years.store'));
  };

  return (
    <AppLayout title="নতুন অর্থবছর তৈরি করুন">
      <Head title="অর্থবছর তৈরি - Tally Software" />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('financial-years.index')}
          className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          ফিরে যান
        </Link>

        <BusinessSelector businesses={businesses} activeBusiness={activeBusiness} />
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">নতুন অর্থবছর তৈরি করুন</h3>
          {activeBusiness && (
            <p className="mt-1 text-sm text-gray-500">
              বিজনেস: {activeBusiness.name} ({activeBusiness.code})
            </p>
          )}
        </div>

        <div className="p-6">
          {Object.keys(errors).length > 0 && (
            <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
              <div className="flex items-center">
                <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                <span className="text-red-800 font-medium">আপনার জমা দেওয়ার সময় ত্রুটি ছিল</span>
              </div>
              {errors.date_range && (
                <p className="mt-2 text-sm text-red-700">{errors.date_range}</p>
              )}
              {errors.name && (
                <p className="mt-2 text-sm text-red-700">{errors.name}</p>
              )}
              {errors.start_date && (
                <p className="mt-2 text-sm text-red-700">{errors.start_date}</p>
              )}
              {errors.end_date && (
                <p className="mt-2 text-sm text-red-700">{errors.end_date}</p>
              )}
            </div>
          )}

          {dateWarning && (
            <div className="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
              <div className="flex items-center">
                <Info className="h-5 w-5 text-yellow-500 mr-2" />
                <span className="text-yellow-800 font-medium">{dateWarning}</span>
              </div>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="space-y-6">
              {/* Financial Year Name */}
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                  অর্থবছরের নাম <span className="text-red-500">*</span>
                </label>
                <div className="mt-1">
                  <input
                    type="text"
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className={`w-full rounded-md border ${
                      errors.name ? 'border-red-300' : 'border-gray-300'
                    } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                    placeholder="উদাহরণ: ২০২৪-২০২৫"
                    required
                  />
                  {errors.name && (
                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                  )}
                </div>
              </div>

              {/* Start and End Dates */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Start Date */}
                <div>
                  <label htmlFor="start_date" className="block text-sm font-medium text-gray-700">
                    শুরুর তারিখ <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1 relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Calendar className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="date"
                      id="start_date"
                      value={data.start_date}
                      onChange={(e) => setData('start_date', e.target.value)}
                      className={`pl-10 w-full rounded-md border ${
                        errors.start_date ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      required
                    />
                    {errors.start_date && (
                      <p className="mt-1 text-sm text-red-600">{errors.start_date}</p>
                    )}
                  </div>
                </div>

                {/* End Date */}
                <div>
                  <label htmlFor="end_date" className="block text-sm font-medium text-gray-700">
                    শেষের তারিখ <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1 relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Calendar className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="date"
                      id="end_date"
                      value={data.end_date}
                      onChange={(e) => setData('end_date', e.target.value)}
                      className={`pl-10 w-full rounded-md border ${
                        errors.end_date ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      required
                    />
                    {errors.end_date && (
                      <p className="mt-1 text-sm text-red-600">{errors.end_date}</p>
                    )}
                  </div>
                </div>
              </div>

              {/* Active Checkbox */}
              <div className="flex items-start">
                <div className="flex items-center h-5">
                  <input
                    id="is_active"
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                </div>
                <div className="ml-3 text-sm">
                  <label htmlFor="is_active" className="font-medium text-gray-700">
                    সক্রিয় অর্থবছর হিসেবে সেট করুন
                  </label>
                  <p className="text-gray-500">
                    একটি সক্রিয় অর্থবছর প্রয়োজন। এটি সক্রিয় হিসেবে সেট করলে বর্তমান সক্রিয় অর্থবছর নিষ্ক্রিয় হয়ে যাবে।
                  </p>
                </div>
              </div>
            </div>

            {/* Buttons */}
            <div className="mt-6 flex justify-end">
              <Link
                href={route('financial-years.index')}
                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                বাতিল
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                {processing ? (
                  <span className="flex items-center">
                    <svg
                      className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
                      <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                      ></circle>
                      <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      ></path>
                    </svg>
                    প্রক্রিয়াকরণ হচ্ছে...
                  </span>
                ) : (
                  <span className="flex items-center">
                    <Save className="mr-2 h-4 w-4" />
                    সংরক্ষণ করুন
                  </span>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>

      <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md flex items-start">
        <Info className="h-5 w-5 text-blue-500 mr-3 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="text-sm font-medium text-blue-800">অর্থবছর সম্পর্কে টিপস</h4>
          <p className="mt-1 text-sm text-blue-700">
            বাংলাদেশে সাধারণত অর্থবছর জুলাই ১ থেকে জুন ৩০ পর্যন্ত চলে। আপনার ব্যবসার প্রয়োজনে ভিন্ন তারিখও সেট করতে পারেন।
            অর্থবছর সক্রিয় থাকলে সেই সময়কালের জন্য রিপোর্ট এবং হিসাব তৈরি করা যাবে।
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
