import React, { FormEvent } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, Building, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

export default function BusinessCreate() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    code: '',
    legal_name: '',
    tax_identification_number: '',
    registration_number: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'Bangladesh',
    phone: '',
    email: '',
    website: '',
    is_active: true,
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(route('businesses.store'));
  };

  return (
    <AppLayout title="Create New Business">
      <Head title="Create Business - Tally Software" />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('businesses.index')}
          className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          Go Back
        </Link>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="flex items-center">
            <Building className="h-5 w-5 text-gray-500 mr-2" />
            <h3 className="text-lg font-medium text-gray-900">Business Information</h3>
          </div>
          <p className="mt-1 text-sm text-gray-500">
            Create a new business to manage its accounts, inventory, and operations.
          </p>
        </div>

        <div className="p-6">
          {Object.keys(errors).length > 0 && (
            <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
              <div className="flex items-center">
                <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                <span className="text-red-800 font-medium">There were errors with your submission</span>
              </div>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="space-y-6">
              {/* Basic Information */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Business Name */}
                <div>
                  <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                    Business Name <span className="text-red-500">*</span>
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
                      placeholder="Enter business name"
                      required
                    />
                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                  </div>
                </div>

                {/* Business Code */}
                <div>
                  <label htmlFor="code" className="block text-sm font-medium text-gray-700">
                    Business Code <span className="text-red-500">*</span>
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      id="code"
                      value={data.code}
                      onChange={(e) => setData('code', e.target.value)}
                      className={`w-full rounded-md border ${
                        errors.code ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      placeholder="Example: BUS001"
                      required
                    />
                    {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                  </div>
                </div>

                {/* Legal Name */}
                <div>
                  <label htmlFor="legal_name" className="block text-sm font-medium text-gray-700">
                    Legal Name
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      id="legal_name"
                      value={data.legal_name}
                      onChange={(e) => setData('legal_name', e.target.value)}
                      className={`w-full rounded-md border ${
                        errors.legal_name ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      placeholder="Legal registered name"
                    />
                    {errors.legal_name && <p className="mt-1 text-sm text-red-600">{errors.legal_name}</p>}
                  </div>
                </div>

                {/* Tax ID */}
                <div>
                  <label htmlFor="tax_identification_number" className="block text-sm font-medium text-gray-700">
                    Tax ID Number
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      id="tax_identification_number"
                      value={data.tax_identification_number}
                      onChange={(e) => setData('tax_identification_number', e.target.value)}
                      className={`w-full rounded-md border ${
                        errors.tax_identification_number ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      placeholder="Tax identification number"
                    />
                    {errors.tax_identification_number && (
                      <p className="mt-1 text-sm text-red-600">{errors.tax_identification_number}</p>
                    )}
                  </div>
                </div>

                {/* Registration Number */}
                <div>
                  <label htmlFor="registration_number" className="block text-sm font-medium text-gray-700">
                    Registration Number
                  </label>
                  <div className="mt-1">
                    <input
                      type="text"
                      id="registration_number"
                      value={data.registration_number}
                      onChange={(e) => setData('registration_number', e.target.value)}
                      className={`w-full rounded-md border ${
                        errors.registration_number ? 'border-red-300' : 'border-gray-300'
                      } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                      placeholder="Business registration number"
                    />
                    {errors.registration_number && (
                      <p className="mt-1 text-sm text-red-600">{errors.registration_number}</p>
                    )}
                  </div>
                </div>
              </div>

              {/* Address Information */}
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-3">Address Information</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Address */}
                  <div className="md:col-span-2">
                    <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                      Address
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.address ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Street address"
                      />
                      {errors.address && <p className="mt-1 text-sm text-red-600">{errors.address}</p>}
                    </div>
                  </div>

                  {/* City */}
                  <div>
                    <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                      City
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="city"
                        value={data.city}
                        onChange={(e) => setData('city', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.city ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="City"
                      />
                      {errors.city && <p className="mt-1 text-sm text-red-600">{errors.city}</p>}
                    </div>
                  </div>

                  {/* State */}
                  <div>
                    <label htmlFor="state" className="block text-sm font-medium text-gray-700">
                      State/Province
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="state"
                        value={data.state}
                        onChange={(e) => setData('state', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.state ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="State/Province"
                      />
                      {errors.state && <p className="mt-1 text-sm text-red-600">{errors.state}</p>}
                    </div>
                  </div>

                  {/* Postal Code */}
                  <div>
                    <label htmlFor="postal_code" className="block text-sm font-medium text-gray-700">
                      Postal Code
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="postal_code"
                        value={data.postal_code}
                        onChange={(e) => setData('postal_code', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.postal_code ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Postal code"
                      />
                      {errors.postal_code && <p className="mt-1 text-sm text-red-600">{errors.postal_code}</p>}
                    </div>
                  </div>

                  {/* Country */}
                  <div>
                    <label htmlFor="country" className="block text-sm font-medium text-gray-700">
                      Country
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="country"
                        value={data.country}
                        onChange={(e) => setData('country', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.country ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Country"
                      />
                      {errors.country && <p className="mt-1 text-sm text-red-600">{errors.country}</p>}
                    </div>
                  </div>
                </div>
              </div>

              {/* Contact Information */}
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-3">Contact Information</h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  {/* Phone */}
                  <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                      Phone Number
                    </label>
                    <div className="mt-1">
                      <input
                        type="text"
                        id="phone"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.phone ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Phone number"
                      />
                      {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
                    </div>
                  </div>

                  {/* Email */}
                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                      Email Address
                    </label>
                    <div className="mt-1">
                      <input
                        type="email"
                        id="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.email ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Email address"
                      />
                      {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                    </div>
                  </div>

                  {/* Website */}
                  <div>
                    <label htmlFor="website" className="block text-sm font-medium text-gray-700">
                      Website
                    </label>
                    <div className="mt-1">
                      <input
                        type="url"
                        id="website"
                        value={data.website}
                        onChange={(e) => setData('website', e.target.value)}
                        className={`w-full rounded-md border ${
                          errors.website ? 'border-red-300' : 'border-gray-300'
                        } px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50`}
                        placeholder="Website URL"
                      />
                      {errors.website && <p className="mt-1 text-sm text-red-600">{errors.website}</p>}
                    </div>
                  </div>
                </div>
              </div>

              {/* Status */}
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
                    Active Business
                  </label>
                  <p className="text-gray-500">
                    Inactive businesses will not be accessible in the system.
                  </p>
                </div>
              </div>
            </div>

            {/* Buttons */}
            <div className="mt-6 flex justify-end">
              <Link
                href={route('businesses.index')}
                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Cancel
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
