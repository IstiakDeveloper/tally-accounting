import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  BarChart,
  LineChart,
  Line,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import {
  TrendingUp,
  TrendingDown,
  DollarSign,
  ShoppingCart,
  Users,
  Package,
  AlertCircle
} from 'lucide-react';

interface DashboardProps {
  stats: {
    totalSales: number;
    totalPurchases: number;
    accountsReceivable: number;
    accountsPayable: number;
    totalProducts: number;
    lowStockProducts: number;
    totalCustomers: number;
    totalSuppliers: number;
  };
  recentSales: {
    id: number;
    reference_number: string;
    customer_name: string;
    invoice_date: string;
    total: number;
    status: string;
  }[];
  salesData: {
    name: string;
    amount: number;
  }[];
  expenseData: {
    name: string;
    amount: number;
  }[];
  alerts: {
    type: string;
    message: string;
  }[];
}

export default function Dashboard({ stats, recentSales, salesData, expenseData, alerts }: DashboardProps) {
  const formatCurrency = (value: number) => {
    return `৳${value?.toLocaleString()}`;
  };

  return (
    <AppLayout>
      <Head title="Dashboard" />

      <h1 className="text-2xl font-semibold mb-6">ড্যাশবোর্ড</h1>

      {/* Stats Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">মোট বিক্রয়</p>
                <h3 className="text-2xl font-bold">{formatCurrency(stats?.totalSales)}</h3>
              </div>
              <div className="p-3 bg-green-100 rounded-full">
                <TrendingUp className="text-green-600" size={24} />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">মোট ক্রয়</p>
                <h3 className="text-2xl font-bold">{formatCurrency(stats?.totalPurchases)}</h3>
              </div>
              <div className="p-3 bg-red-100 rounded-full">
                <TrendingDown className="text-red-600" size={24} />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">প্রাপ্য হিসাব</p>
                <h3 className="text-2xl font-bold">{formatCurrency(stats?.accountsReceivable)}</h3>
              </div>
              <div className="p-3 bg-blue-100 rounded-full">
                <DollarSign className="text-blue-600" size={24} />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">দেয় হিসাব</p>
                <h3 className="text-2xl font-bold">{formatCurrency(stats?.accountsPayable)}</h3>
              </div>
              <div className="p-3 bg-orange-100 rounded-full">
                <DollarSign className="text-orange-600" size={24} />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Sales Chart */}
        <Card>
          <CardHeader>
            <CardTitle>মাসিক বিক্রয়</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={salesData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip formatter={(value) => formatCurrency(value as number)} />
                  <Legend />
                  <Bar dataKey="amount" fill="#4f46e5" name="বিক্রয়" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Expense Chart */}
        <Card>
          <CardHeader>
            <CardTitle>মাসিক খরচ</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={expenseData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip formatter={(value) => formatCurrency(value as number)} />
                  <Legend />
                  <Line
                    type="monotone"
                    dataKey="amount"
                    stroke="#f43f5e"
                    name="খরচ"
                    strokeWidth={2}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Sales */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>সাম্প্রতিক বিক্রয়</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">

            </div>
          </CardContent>
        </Card>

        {/* Alerts and Inventory Status */}
        <Card>
          <CardHeader>
            <CardTitle>সতর্কতা</CardTitle>
          </CardHeader>

        </Card>
      </div>
    </AppLayout>
  );
}
