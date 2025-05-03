import React from 'react';
import { Link } from '@inertiajs/react';
import { X, Plus, Search, FileText, Download, Filter, Trash, Edit, Eye } from 'lucide-react';

interface DataTableProps {
  data: any[];
  columns: {
    header: string;
    accessor: string;
    cell?: (row: any) => React.ReactNode;
  }[];
  onEdit?: (id: number) => void;
  onDelete?: (id: number) => void;
  onView?: (id: number) => void;
  actions?: boolean;
}

export const DataTable: React.FC<DataTableProps> = ({
  data,
  columns,
  onEdit,
  onDelete,
  onView,
  actions = true
}) => {
  return (
    <div className="overflow-x-auto bg-white rounded-lg shadow">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((column, index) => (
              <th
                key={index}
                scope="col"
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                {column.header}
              </th>
            ))}
            {actions && (
              <th scope="col" className="relative px-6 py-3">
                <span className="sr-only">Actions</span>
              </th>
            )}
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {data.map((row, rowIndex) => (
            <tr key={rowIndex} className="hover:bg-gray-50">
              {columns.map((column, colIndex) => (
                <td key={colIndex} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {column.cell ? column.cell(row) : row[column.accessor]}
                </td>
              ))}
              {actions && (
                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                  {onView && (
                    <button
                      onClick={() => onView(row.id)}
                      className="text-indigo-600 hover:text-indigo-900 transition-colors duration-200"
                    >
                      <Eye size={18} />
                    </button>
                  )}
                  {onEdit && (
                    <button
                      onClick={() => onEdit(row.id)}
                      className="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                    >
                      <Edit size={18} />
                    </button>
                  )}
                  {onDelete && (
                    <button
                      onClick={() => onDelete(row.id)}
                      className="text-red-600 hover:text-red-900 transition-colors duration-200"
                    >
                      <Trash size={18} />
                    </button>
                  )}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

interface SearchBarProps {
  placeholder?: string;
  value: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onClear?: () => void;
}

export const SearchBar: React.FC<SearchBarProps> = ({
  placeholder = "Search...",
  value,
  onChange,
  onClear
}) => {
  return (
    <div className="relative">
      <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <Search size={18} className="text-gray-400" />
      </div>
      <input
        type="text"
        className="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition duration-150 ease-in-out"
        placeholder={placeholder}
        value={value}
        onChange={onChange}
      />
      {value && onClear && (
        <div className="absolute inset-y-0 right-0 flex items-center pr-3">
          <button
            onClick={onClear}
            className="text-gray-400 hover:text-gray-500 focus:outline-none"
          >
            <X size={18} />
          </button>
        </div>
      )}
    </div>
  );
};

interface ButtonProps {
  children: React.ReactNode;
  variant?: 'primary' | 'secondary' | 'danger' | 'success' | 'warning';
  size?: 'sm' | 'md' | 'lg';
  onClick?: () => void;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  icon?: React.ReactNode;
  className?: string;
}

export const Button: React.FC<ButtonProps> = ({
  children,
  variant = 'primary',
  size = 'md',
  onClick,
  type = 'button',
  disabled = false,
  icon,
  className = '',
}) => {
  const baseStyles = 'inline-flex items-center justify-center border font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150';

  const variantStyles = {
    primary: 'border-transparent text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500',
    secondary: 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:ring-indigo-500',
    danger: 'border-transparent text-white bg-red-600 hover:bg-red-700 focus:ring-red-500',
    success: 'border-transparent text-white bg-green-600 hover:bg-green-700 focus:ring-green-500',
    warning: 'border-transparent text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'
  };

  const sizeStyles = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base'
  };

  const disabledStyles = disabled ? 'opacity-50 cursor-not-allowed' : 'hover:shadow';

  const buttonStyles = `${baseStyles} ${variantStyles[variant]} ${sizeStyles[size]} ${disabledStyles} ${className}`;

  return (
    <button
      type={type}
      className={buttonStyles}
      onClick={onClick}
      disabled={disabled}
    >
      {icon && <span className="mr-2">{icon}</span>}
      {children}
    </button>
  );
};

interface CardProps {
  children: React.ReactNode;
  title?: string;
  className?: string;
  footer?: React.ReactNode;
}

export const Card: React.FC<CardProps> = ({
  children,
  title,
  className = '',
  footer
}) => {
  return (
    <div className={`bg-white shadow-md rounded-lg overflow-hidden ${className}`}>
      {title && (
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-medium text-gray-900">{title}</h3>
        </div>
      )}
      <div className="px-6 py-4">
        {children}
      </div>
      {footer && (
        <div className="px-6 py-3 bg-gray-50 border-t border-gray-200">
          {footer}
        </div>
      )}
    </div>
  );
};

interface StatusBadgeProps {
  status: string;
  type?: 'default' | 'success' | 'warning' | 'danger' | 'info';
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({
  status,
  type = 'default'
}) => {
  const typeStyles = {
    default: 'bg-gray-100 text-gray-800',
    success: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-blue-100 text-blue-800'
  };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${typeStyles[type]}`}>
      {status}
    </span>
  );
};

interface FormGroupProps {
  label: string;
  htmlFor: string;
  error?: string;
  children: React.ReactNode;
  required?: boolean;
  helpText?: string;
}

export const FormGroup: React.FC<FormGroupProps> = ({
  label,
  htmlFor,
  error,
  children,
  required = false,
  helpText
}) => {
  return (
    <div className="mb-4">
      <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700 mb-1">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      {children}
      {helpText && <p className="mt-1 text-xs text-gray-500">{helpText}</p>}
      {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
  );
};

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  onPageChange
}) => {
  const getPageNumbers = () => {
    const pages = [];
    const maxVisiblePages = 5;

    if (totalPages <= maxVisiblePages) {
      // Show all pages
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      // Always include first page, last page, current page, and pages around current
      const firstPage = 1;
      const lastPage = totalPages;

      if (currentPage <= 3) {
        // Near start
        for (let i = 1; i <= 5; i++) {
          pages.push(i);
        }
        pages.push('...');
        pages.push(lastPage);
      } else if (currentPage >= totalPages - 2) {
        // Near end
        pages.push(firstPage);
        pages.push('...');
        for (let i = totalPages - 4; i <= totalPages; i++) {
          pages.push(i);
        }
      } else {
        // Middle
        pages.push(firstPage);
        pages.push('...');
        for (let i = currentPage - 1; i <= currentPage + 1; i++) {
          pages.push(i);
        }
        pages.push('...');
        pages.push(lastPage);
      }
    }

    return pages;
  };

  return (
    <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
      <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
          <p className="text-sm text-gray-700">
            Showing <span className="font-medium">page {currentPage}</span> of{' '}
            <span className="font-medium">{totalPages}</span> pages
          </p>
        </div>
        <div>
          <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
            <button
              onClick={() => onPageChange(Math.max(1, currentPage - 1))}
              disabled={currentPage === 1}
              className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50"
            >
              <span className="sr-only">Previous</span>
              <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
              </svg>
            </button>

            {getPageNumbers().map((page, index) => (
              page === '...' ? (
                <span key={`ellipsis-${index}`} className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">
                  ...
                </span>
              ) : (
                <button
                  key={`page-${page}`}
                  onClick={() => typeof page === 'number' && onPageChange(page)}
                  className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${
                    currentPage === page
                      ? 'bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                      : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                  }`}
                >
                  {page}
                </button>
              )
            ))}

            <button
              onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
              disabled={currentPage === totalPages}
              className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50"
            >
              <span className="sr-only">Next</span>
              <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
              </svg>
            </button>
          </nav>
        </div>
      </div>
    </div>
  );
};
