import React from 'react';
import { Link, Head } from '@inertiajs/react';
import { LogIn, BarChart2, DollarSign, Package, Users, Settings } from 'lucide-react';

export default function Welcome() {
  const features = [
    {
      name: 'Accounting',
      description: 'Complete double-entry accounting system with journal entries, chart of accounts, and financial reports.',
      icon: BarChart2,
    },
    {
      name: 'Inventory Management',
      description: 'Track stock levels, movements, and valuations across multiple warehouses.',
      icon: Package,
    },
    {
      name: 'Sales & Purchases',
      description: 'Manage the complete order-to-cash and procure-to-pay cycles with ease.',
      icon: DollarSign,
    },
    {
      name: 'Payroll',
      description: 'Employee management, salary processing, and attendance tracking all in one place.',
      icon: Users,
    },
    {
      name: 'Comprehensive Reporting',
      description: 'Generate detailed reports for informed decision-making and compliance.',
      icon: BarChart2,
    },
    {
      name: 'Customizable Settings',
      description: 'Adapt the system to your business needs with flexible configuration options.',
      icon: Settings,
    },
  ];

  return (
    <div className="min-h-screen bg-gray-100">
      <Head title="Welcome - Tally Software" />

      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 flex justify-between items-center">
          <div className="flex items-center">
            <h1 className="text-2xl font-bold text-gray-900">Tally Software</h1>
          </div>
          <div>
            <Link
              href="/login"
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <LogIn className="mr-2 h-4 w-4" />
              Sign In
            </Link>
          </div>
        </div>
      </header>

      <main>
        {/* Hero section */}
        <div className="relative">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div className="text-center">
              <h1 className="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                <span className="block">Modern accounting solution</span>
                <span className="block text-blue-600">for your business</span>
              </h1>
              <p className="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                A comprehensive business management system with accounting, inventory, sales, purchases, and payroll modules.
              </p>
              <div className="mt-8 flex justify-center">
                <div className="rounded-md shadow">
                  <Link
                    href="/login"
                    className="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg md:px-10"
                  >
                    Get Started
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Features section */}
        <div className="bg-white py-12">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="text-center">
              <h2 className="text-base font-semibold text-blue-600 tracking-wide uppercase">Features</h2>
              <p className="mt-2 text-3xl font-extrabold text-gray-900 sm:text-4xl">
                Everything you need to run your business
              </p>
              <p className="mt-4 max-w-2xl text-xl text-gray-500 mx-auto">
                From accounting to inventory, sales to payroll - all in one integrated solution.
              </p>
            </div>

            <div className="mt-12">
              <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                {features.map((feature, index) => (
                  <div key={index} className="pt-6">
                    <div className="flow-root bg-gray-50 rounded-lg px-6 pb-8">
                      <div className="-mt-6">
                        <div>
                          <span className="inline-flex items-center justify-center p-3 bg-blue-600 rounded-md shadow-lg">
                            <feature.icon className="h-6 w-6 text-white" aria-hidden="true" />
                          </span>
                          <h3 className="mt-8 text-lg font-medium text-gray-900 tracking-tight">{feature.name}</h3>
                          <p className="mt-2 text-base text-gray-500">{feature.description}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Testimonial section */}
        <div className="bg-gray-50 py-16 px-4 sm:px-6 lg:px-8">
          <div className="max-w-7xl mx-auto">
            <div className="text-center">
              <h2 className="text-3xl font-extrabold text-gray-900">Trusted by businesses across Bangladesh</h2>
              <p className="mt-4 text-lg text-gray-500">
                Our tally software helps businesses of all sizes streamline their operations.
              </p>
            </div>
            <div className="mt-12 bg-white rounded-lg shadow-lg overflow-hidden">
              <div className="px-6 py-8 sm:p-10">
                <div className="text-center">
                  <p className="text-xl font-medium text-gray-900">
                    "This software has transformed how we manage our business. The integrated accounting and inventory features save us hours every week."
                  </p>
                  <div className="mt-6">
                    <p className="text-base font-medium text-gray-900">Rahul Ahmed</p>
                    <p className="text-sm text-gray-500">CEO, Global Trading Ltd.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* CTA section */}
        <div className="bg-blue-700">
          <div className="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
            <h2 className="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
              <span className="block">Ready to get started?</span>
              <span className="block text-blue-300">Sign up today and streamline your business operations.</span>
            </h2>
            <div className="mt-8 flex lg:mt-0 lg:flex-shrink-0">
              <div className="inline-flex rounded-md shadow">
                <Link
                  href="/login"
                  className="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-blue-600 bg-white hover:bg-gray-50"
                >
                  Get Started
                </Link>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer className="bg-gray-800">
        <div className="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
          <p className="text-center text-base text-gray-400">
            &copy; {new Date().getFullYear()} Tally Software. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
}
