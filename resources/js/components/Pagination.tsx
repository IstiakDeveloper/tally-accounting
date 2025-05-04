import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Pagination as PaginationType } from '@/types';

interface PaginationProps {
  meta: PaginationType;
}

export default function Pagination({ meta }: PaginationProps) {
  // Check if meta data is valid
  if (!meta || !meta.last_page) {
    return null;
  }

  // Don't render pagination if there's only one page
  if (meta.last_page <= 1) {
    return null;
  }

  // Generate an array of page numbers to display
  const getPageNumbers = () => {
    const range = 2; // Show 2 pages before and after the current page
    const pages = [];

    let startPage = Math.max(1, meta.current_page - range);
    let endPage = Math.min(meta.last_page, meta.current_page + range);

    // Always show at least 5 pages if available
    if (endPage - startPage + 1 < 5) {
      if (startPage === 1) {
        endPage = Math.min(5, meta.last_page);
      } else if (endPage === meta.last_page) {
        startPage = Math.max(1, meta.last_page - 4);
      }
    }

    // Add page numbers
    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }

    return pages;
  };

  const pageNumbers = getPageNumbers();

  return (
    <div className="flex items-center justify-between">
      <div className="flex-1 flex justify-between sm:hidden">
        {meta.current_page > 1 ? (
          <Link
            href={`?page=${meta.current_page - 1}`}
            preserveState
            preserveScroll
            className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            Previous
          </Link>
        ) : (
          <button
            disabled
            className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed"
          >
            Previous
          </button>
        )}

        {meta.current_page < meta.last_page ? (
          <Link
            href={`?page=${meta.current_page + 1}`}
            preserveState
            preserveScroll
            className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            Next
          </Link>
        ) : (
          <button
            disabled
            className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed"
          >
            Next
          </button>
        )}
      </div>

      <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
          <p className="text-sm text-gray-700">
            Showing <span className="font-medium">{meta.from || 0}</span> to{' '}
            <span className="font-medium">{meta.to || 0}</span> of{' '}
            <span className="font-medium">{meta.total || 0}</span> results
          </p>
        </div>

        <div>
          <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            {/* Previous Page Link */}
            {meta.current_page > 1 ? (
              <Link
                href={`?page=${meta.current_page - 1}`}
                preserveState
                preserveScroll
                className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              >
                <span className="sr-only">Previous</span>
                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              </Link>
            ) : (
              <button
                disabled
                className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-300 cursor-not-allowed"
              >
                <span className="sr-only">Previous</span>
                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              </button>
            )}

            {/* First Page */}
            {pageNumbers[0] > 1 && (
              <>
                <Link
                  href="?page=1"
                  preserveState
                  preserveScroll
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  1
                </Link>
                {pageNumbers[0] > 2 && (
                  <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    ...
                  </span>
                )}
              </>
            )}

            {/* Page Numbers */}
            {pageNumbers.map((page) => (
              <Link
                key={page}
                href={`?page=${page}`}
                preserveState
                preserveScroll
                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                  page === meta.current_page
                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                }`}
              >
                {page}
              </Link>
            ))}

            {/* Last Page */}
            {pageNumbers[pageNumbers.length - 1] < meta.last_page && (
              <>
                {pageNumbers[pageNumbers.length - 1] < meta.last_page - 1 && (
                  <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    ...
                  </span>
                )}
                <Link
                  href={`?page=${meta.last_page}`}
                  preserveState
                  preserveScroll
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  {meta.last_page}
                </Link>
              </>
            )}

            {/* Next Page Link */}
            {meta.current_page < meta.last_page ? (
              <Link
                href={`?page=${meta.current_page + 1}`}
                preserveState
                preserveScroll
                className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              >
                <span className="sr-only">Next</span>
                <ChevronRight className="h-5 w-5" aria-hidden="true" />
              </Link>
            ) : (
              <button
                disabled
                className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-300 cursor-not-allowed"
              >
                <span className="sr-only">Next</span>
                <ChevronRight className="h-5 w-5" aria-hidden="true" />
              </button>
            )}
          </nav>
        </div>
      </div>
    </div>
  );
}
