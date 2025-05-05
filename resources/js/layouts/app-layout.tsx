import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
  Menu,
  X,
  ChevronDown,
  Home,
  PieChart,
  FileText,
  ShoppingCart,
  Package,
  Users,
  DollarSign,
  Settings,
  Calendar,
  Bell,
  User,
  LogOut,
  BookOpen,
  Layers,
  FileBarChart,
  Building
} from 'lucide-react';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface PageProps {
  auth: {
    user: User;
  };
  [key: string]: any;
}

interface MenuItem {
  label: string;
  icon: React.ReactNode;
  href?: string;
  submenu?: MenuItem[];
  roles?: string[];
}

interface MainLayoutProps {
  children: React.ReactNode;
  title?: string;
}

export default function MainLayout({ children, title = 'Dashboard' }: MainLayoutProps) {
  const { auth } = usePage<PageProps>().props;
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [openSubmenus, setOpenSubmenus] = useState<{ [key: string]: boolean }>({});
  const [currentDateTime, setCurrentDateTime] = useState(new Date());
  const [notifications, setNotifications] = useState([]);
  const [showUserMenu, setShowUserMenu] = useState(false);

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentDateTime(new Date());
    }, 60000); // Update every minute

    return () => {
      clearInterval(timer);
    };
  }, []);

  const toggleSubmenu = (label: string) => {
    setOpenSubmenus(prev => ({
      ...prev,
      [label]: !prev[label]
    }));
  };

  const menuItems: MenuItem[] = [
    {
      label: 'Dashboard',
      icon: <Home size={20} />,
      href: '/dashboard',
    },
    {
      label: 'Accounting',
      icon: <PieChart size={20} />,
      submenu: [
        { label: 'Chart of Accounts', href: '/accounting/chart-of-accounts', icon: <BookOpen size={18} /> },
        { label: 'Journal Entries', href: '/accounting/journal-entries', icon: <FileText size={18} /> },
        { label: 'Financial Years', href: '/accounting/financial-years', icon: <Calendar size={18} /> },
        { label: 'Bank Accounts', href: '/accounting/bank-accounts', icon: <Building size={18} /> },
      ],
      roles: ['admin', 'accountant'],
    },
    {
      label: 'Sales',
      icon: <DollarSign size={20} />,
      submenu: [
        { label: 'Customers', href: '/customers', icon: <Users size={18} /> },
        { label: 'Sales Orders', href: '/sales-orders', icon: <FileText size={18} /> },
        { label: 'Sales Invoices', href: '/sales-invoices', icon: <FileText size={18} /> },
        { label: 'Payments Received', href: '/payments-received', icon: <DollarSign size={18} /> },
      ],
    },
    {
      label: 'Purchases',
      icon: <ShoppingCart size={20} />,
      submenu: [
        { label: 'Suppliers', href: '/suppliers', icon: <Users size={18} /> },
        { label: 'Purchase Orders', href: '/purchase-orders', icon: <FileText size={18} /> },
        { label: 'Purchase Invoices', href: '/purchase-invoices', icon: <FileText size={18} /> },
        { label: 'Payments Made', href: '/payments-made', icon: <DollarSign size={18} /> },
      ],
    },
    {
      label: 'Inventory',
      icon: <Package size={20} />,
      submenu: [
        { label: 'Products', href: '/products', icon: <Package size={18} /> },
        { label: 'Categories', href: '/product-categories', icon: <Layers size={18} /> },
        { label: 'Warehouses', href: '/warehouses', icon: <Building size={18} /> },
        { label: 'Stock Movements', href: '/stock-movements', icon: <FileText size={18} /> },
        { label: 'Stock Balances', href: '/stock-balances', icon: <FileBarChart size={18} /> },
      ],
    },
    {
      label: 'Human Resources',
      icon: <Users size={20} />,
      submenu: [
        { label: 'Employees', href: '/employees', icon: <Users size={18} /> },
        { label: 'Departments', href: '/departments', icon: <Building size={18} /> },
        { label: 'Designations', href: '/designations', icon: <Users size={18} /> },
        { label: 'Payroll', href: '/payroll', icon: <DollarSign size={18} /> },
        { label: 'Leave Management', href: '/leaves', icon: <Calendar size={18} /> },
      ],
      roles: ['admin', 'manager'],
    },
    {
      label: 'Reports',
      icon: <FileBarChart size={20} />,
      submenu: [
        { label: 'Financial Reports', href: '/reports/financial', icon: <FileText size={18} /> },
        { label: 'Sales Reports', href: '/reports/sales', icon: <FileText size={18} /> },
        { label: 'Purchase Reports', href: '/reports/purchase', icon: <FileText size={18} /> },
        { label: 'Inventory Reports', href: '/reports/inventory', icon: <FileText size={18} /> },
        { label: 'HR Reports', href: '/reports/hr', icon: <FileText size={18} /> },
        { label: 'Tax Reports', href: '/reports/tax', icon: <FileText size={18} /> },
      ],
      roles: ['admin', 'accountant', 'manager'],
    },
    {
      label: 'Settings',
      icon: <Settings size={20} />,
      submenu: [
        { label: 'Company', href: '/settings/company', icon: <Building size={18} /> },
        { label: 'Users', href: '/settings/users', icon: <Users size={18} /> },
        { label: 'Taxes', href: '/settings/taxes', icon: <DollarSign size={18} /> },
        { label: 'Audit Logs', href: '/settings/audit-logs', icon: <FileText size={18} /> },
      ],
      roles: ['admin'],
    },
  ];

  const filteredMenuItems = menuItems.filter(item => {
    if (!item.roles) return true;
    return item.roles.includes(auth.user.role);
  });

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
  };

  const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
  };

  return (
    <div className="flex h-screen bg-gray-100">
      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 w-64 transform bg-white border-r border-gray-200 shadow-lg transition-transform duration-300 ease-in-out lg:static lg:inset-0 lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Logo */}
        <div className="flex items-center justify-between h-16 px-6 border-b border-gray-200">
          <Link href="/dashboard" className="flex items-center space-x-2">
            <span className="text-xl font-bold text-indigo-600">TallyERP</span>
          </Link>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden"
          >
            <X size={20} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="px-4 py-4 overflow-y-auto h-[calc(100vh-64px)]">
          <ul className="space-y-1">
            {filteredMenuItems.map((item, index) => (
              <li key={index} className="mb-2">
                {item.submenu ? (
                  <div>
                    <button
                      onClick={() => toggleSubmenu(item.label)}
                      className={`flex items-center justify-between w-full px-4 py-2 text-sm font-medium rounded-md ${
                        openSubmenus[item.label] ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-100'
                      }`}
                    >
                      <div className="flex items-center">
                        {item.icon}
                        <span className="ml-3">{item.label}</span>
                      </div>
                      <ChevronDown
                        size={16}
                        className={`transition-transform duration-200 ${
                          openSubmenus[item.label] ? 'rotate-180' : ''
                        }`}
                      />
                    </button>
                    {openSubmenus[item.label] && (
                      <ul className="pl-4 mt-1 space-y-1">
                        {item.submenu.map((subitem, subindex) => (
                          <li key={subindex}>
                            <Link
                              href={subitem.href || '#'}
                              className="flex items-center px-4 py-2 text-sm text-gray-600 rounded-md hover:bg-gray-100"
                            >
                              {subitem.icon}
                              <span className="ml-3">{subitem.label}</span>
                            </Link>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                ) : (
                  <Link
                    href={item.href || '#'}
                    className={`flex items-center px-4 py-2 text-sm font-medium rounded-md ${
                      window.location.pathname === item.href
                        ? 'bg-indigo-50 text-indigo-600'
                        : 'text-gray-700 hover:bg-gray-100'
                    }`}
                  >
                    {item.icon}
                    <span className="ml-3">{item.label}</span>
                  </Link>
                )}
              </li>
            ))}
          </ul>
        </nav>
      </aside>

      {/* Main Content */}
      <div className="flex flex-col flex-1 min-h-screen overflow-x-hidden">
        {/* Top Navigation */}
        <header className="sticky top-0 z-30 flex items-center justify-between h-16 px-6 bg-white border-b border-gray-200 shadow-sm">
          <div className="flex items-center">
            <button
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden"
            >
              <Menu size={20} />
            </button>
            <h1 className="hidden ml-6 text-lg font-semibold lg:block">{title}</h1>
          </div>

          <div className="flex items-center space-x-4">
            <div className="hidden text-sm text-gray-500 md:block">
              <div>{formatDate(currentDateTime)}</div>
              <div className="text-right">{formatTime(currentDateTime)}</div>
            </div>

            {/* Notifications */}
            <div className="relative">
              <button className="p-1 text-gray-600 rounded-full hover:bg-gray-100">
                <Bell size={20} />
                {notifications.length > 0 && (
                  <span className="absolute top-0 right-0 w-4 h-4 text-xs text-white bg-red-500 rounded-full flex items-center justify-center">
                    {notifications.length}
                  </span>
                )}
              </button>
            </div>

            {/* User Menu */}
            <div className="relative">
              <button
                onClick={() => setShowUserMenu(!showUserMenu)}
                className="flex items-center space-x-2 text-sm text-gray-700"
              >
                <div className="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white">
                  {auth.user.name.charAt(0).toUpperCase()}
                </div>
                <span className="hidden md:block">{auth.user.name}</span>
              </button>

              {showUserMenu && (
                <div className="absolute right-0 z-50 w-48 mt-2 bg-white rounded-md shadow-lg">
                  <div className="py-1">
                    <Link
                      href="/profile"
                      className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                      onClick={() => setShowUserMenu(false)}
                    >
                      <User size={16} className="mr-2" />
                      Profile
                    </Link>
                    <Link
                      href="/logout"
                      method="post"
                      as="button"
                      className="flex items-center w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                      onClick={() => setShowUserMenu(false)}
                    >
                      <LogOut size={16} className="mr-2" />
                      Log Out
                    </Link>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 p-6 overflow-y-auto bg-gray-100">
          <div className="max-w-7xl mx-auto">
            {/* Page header */}
            <div className="md:hidden mb-6">
              <h1 className="text-2xl font-semibold text-gray-800">{title}</h1>
            </div>

            {/* Page content */}
            {children}
          </div>
        </main>

        {/* Footer */}
        <footer className="py-4 bg-white border-t border-gray-200">
          <div className="container px-6 mx-auto">
            <div className="flex flex-col items-center justify-between md:flex-row">
              <p className="text-sm text-gray-500">
                &copy; {new Date().getFullYear()} TallyERP - All rights reserved.
              </p>
              <div className="mt-2 text-sm text-gray-500 md:mt-0">
                Version 1.0.0
              </div>
            </div>
          </div>
        </footer>
      </div>
    </div>
  );
}
