import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

interface SetupRequiredProps {
  message: string;
  setupUrl: string;
}

export default function SetupRequired({ message, setupUrl }: SetupRequiredProps) {
  return (
    <AppLayout title="Setup Required">
      <Head title="Setup Required - Tally Software" />

      <div className="bg-white rounded-lg shadow-lg overflow-hidden">
        <div className="p-6 sm:p-8">
          <div className="flex items-start">
            <div className="flex-shrink-0">
              <AlertTriangle className="h-6 w-6 text-yellow-500" aria-hidden="true" />
            </div>
            <div className="ml-3">
              <h3 className="text-lg font-medium text-gray-900">সেটআপ প্রয়োজন</h3>
              <div className="mt-2 text-sm text-gray-500">
                <p>{message}</p>
              </div>
              <div className="mt-4">
                <Link
                  href={setupUrl}
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  সেটআপ করুন
                  <ChevronRight className="ml-2 h-4 w-4" />
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
