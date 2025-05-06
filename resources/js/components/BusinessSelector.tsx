import React, { useState, useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, Building, Plus, Check, ArrowRight } from 'lucide-react';

interface Business {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
}

interface BusinessSelectorProps {
  businesses: Business[];
  activeBusiness: Business | null;
}

export default function BusinessSelector({ businesses, activeBusiness }: BusinessSelectorProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  if (!businesses || businesses.length === 0) {
    return (
      <Link
        href={route('businesses.create')}
        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
      >
        <Plus className="mr-2 h-4 w-4" />
        ব্যবসা তৈরি করুন
      </Link>
    );
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        onClick={() => setIsOpen(!isOpen)}
      >
        {activeBusiness ? (
          <>
            <Building className="mr-2 h-4 w-4 text-gray-500" />
            <span>{activeBusiness.name}</span>
          </>
        ) : (
          <>
            <Building className="mr-2 h-4 w-4 text-gray-500" />
            <span>ব্যবসা নির্বাচন করুন</span>
          </>
        )}
        <ChevronDown className="ml-2 h-4 w-4 text-gray-500" />
      </button>

      {isOpen && (
        <div className="origin-top-right absolute right-0 mt-2 w-72 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10">
          <div className="py-1 divide-y divide-gray-100" role="menu" aria-orientation="vertical" aria-labelledby="business-selector">
            {businesses.map((business) => (
              <Link
                key={business.id}
                href={route('businesses.switch', business.id)}
                method="get"
                as="button"
                className={`w-full text-left px-4 py-3 text-sm ${
                  activeBusiness && activeBusiness.id === business.id
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
                role="menuitem"
                onClick={() => setIsOpen(false)}
              >
                <div className="flex items-center">
                  <div className="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                    <span className="text-sm font-medium text-blue-800">
                      {business.name.substring(0, 2).toUpperCase()}
                    </span>
                  </div>
                  <div className="ml-3 flex-grow">
                    <p className="font-medium">{business.name}</p>
                    <p className="text-xs text-gray-500">{business.code}</p>
                  </div>
                  {activeBusiness && activeBusiness.id === business.id && (
                    <Check className="h-5 w-5 text-blue-600" />
                  )}
                </div>
              </Link>
            ))}

            <div className="px-4 py-3">
              <Link
                href={route('businesses.create')}
                className="w-full flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                onClick={() => setIsOpen(false)}
              >
                <Plus className="mr-2 h-4 w-4" />
                নতুন ব্যবসা তৈরি করুন
              </Link>
            </div>

            <div className="px-4 py-3">
              <Link
                href={route('businesses.index')}
                className="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                onClick={() => setIsOpen(false)}
              >
                <ArrowRight className="mr-2 h-4 w-4" />
                সমস্ত ব্যবসা দেখুন
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
