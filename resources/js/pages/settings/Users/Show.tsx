import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Power, Key, Clock, User, FileText, AlertCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

interface ActivityLog {
  id: number;
  log_name: string;
  description: string;
  subject_type: string;
  subject_id: number;
  causer_type: string;
  causer_id: number;
  properties: any;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface UsersShowProps {
  user: User;
  activityLogs: ActivityLog[];
  roles: {
    admin: string;
    accountant: string;
    manager: string;
    user: string;
  };
}

export default function UsersShow({ user, activityLogs, roles }: UsersShowProps) {
  const [resetPasswordModalOpen, setResetPasswordModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    password: '',
    password_confirmation: '',
  });

  const resetPasswordForm = useForm({
    password: '',
    password_confirmation: '',
  });

  const handleResetPassword = (e: React.FormEvent) => {
    e.preventDefault();
    resetPasswordForm.post(route('settings.users.reset-password', user.id), {
      onSuccess: () => {
        setResetPasswordModalOpen(false);
        resetPasswordForm.reset();
      },
    });
  };

  const handleToggleStatus = () => {
    if (confirm(`Are you sure you want to ${user.is_active ? 'deactivate' : 'activate'} this user?`)) {
      post(route('settings.users.toggle-status', user.id));
    }
  };

  const handleDelete = () => {
    post(route('settings.users.destroy', user.id), {
      method: 'delete',
      onSuccess: () => setDeleteModalOpen(false),
    });
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getActivityDescription = (activity: ActivityLog) => {
    switch (activity.description) {
      case 'created':
        return 'Created a new resource';
      case 'updated':
        return 'Updated a resource';
      case 'deleted':
        return 'Deleted a resource';
      case 'toggled status':
        return 'Changed status of a resource';
      case 'reset password':
        return 'Reset password';
      default:
        return activity.description;
    }
  };

  return (
    <AppLayout title={`User: ${user.name}`}>
      <Head title={`User: ${user.name} - Tally Software`} />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('settings.users.index')}
          className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Users
        </Link>

        <div className="flex space-x-2">
          <Link
            href={route('settings.users.edit', user.id)}
            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
          >
            <Edit className="h-4 w-4 mr-2" />
            Edit
          </Link>

          <button
            onClick={() => setResetPasswordModalOpen(true)}
            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
          >
            <Key className="h-4 w-4 mr-2" />
            Reset Password
          </button>

          <button
            onClick={handleToggleStatus}
            className={`inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium ${
              user.is_active
                ? 'border-orange-300 text-orange-700 bg-orange-50 hover:bg-orange-100'
                : 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100'
            } transition-colors`}
          >
            <Power className="h-4 w-4 mr-2" />
            {user.is_active ? 'Deactivate' : 'Activate'}
          </button>

          <button
            onClick={() => setDeleteModalOpen(true)}
            className="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 transition-colors"
          >
            <Trash2 className="h-4 w-4 mr-2" />
            Delete
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* User Information Card */}
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 className="text-lg font-medium text-gray-800">User Information</h2>
          </div>
          <div className="px-6 py-4">
            <div className="flex items-center justify-center mb-6">
              <div className="h-24 w-24 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                <User className="h-12 w-12" />
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Full Name</h3>
                <p className="mt-1 text-gray-900">{user.name}</p>
              </div>

              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Email Address</h3>
                <p className="mt-1 text-gray-900">{user.email}</p>
              </div>

              {user.phone && (
                <div>
                  <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Phone Number</h3>
                  <p className="mt-1 text-gray-900">{user.phone}</p>
                </div>
              )}

              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Role</h3>
                <p className="mt-1 text-gray-900">
                  {user.role} ({roles[user.role as keyof typeof roles]})
                </p>
              </div>

              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Status</h3>
                <div className="mt-1">
                  <span
                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      user.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}
                  >
                    {user.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
              </div>

              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Created At</h3>
                <p className="mt-1 text-gray-900">{formatDate(user.created_at)}</p>
              </div>

              <div>
                <h3 className="text-xs uppercase tracking-wide text-gray-500 font-medium">Last Updated</h3>
                <p className="mt-1 text-gray-900">{formatDate(user.updated_at)}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Activity Logs Card */}
        <div className="bg-white rounded-lg shadow-md overflow-hidden lg:col-span-2">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 className="text-lg font-medium text-gray-800">Recent Activity</h2>
          </div>
          <div className="px-6 py-4">
            {activityLogs.length > 0 ? (
              <div className="space-y-4">
                {activityLogs.map((log) => (
                  <div key={log.id} className="flex items-start pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                    <div className="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                      {log.description === 'created' && <FileText className="h-5 w-5" />}
                      {log.description === 'updated' && <Edit className="h-5 w-5" />}
                      {log.description === 'deleted' && <Trash2 className="h-5 w-5" />}
                      {log.description === 'toggled status' && <Power className="h-5 w-5" />}
                      {log.description === 'reset password' && <Key className="h-5 w-5" />}
                      {!['created', 'updated', 'deleted', 'toggled status', 'reset password'].includes(log.description) && (
                        <AlertCircle className="h-5 w-5" />
                      )}
                    </div>
                    <div className="ml-3 flex-1">
                      <div className="text-sm text-gray-900 font-medium">{getActivityDescription(log)}</div>
                      <div className="mt-1 text-xs text-gray-500 flex items-center">
                        <Clock className="h-3 w-3 mr-1" />
                        {formatDate(log.created_at)}
                      </div>
                      {log.properties && Object.keys(log.properties).length > 0 && (
                        <div className="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-700">
                          <pre>{JSON.stringify(log.properties, null, 2)}</pre>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-6 text-gray-500">No activity records found for this user.</div>
            )}
          </div>
        </div>
      </div>

      {/* Reset Password Modal */}
      {resetPasswordModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Reset Password</h3>
            </div>
            <form onSubmit={handleResetPassword}>
              <div className="px-6 py-4">
                <div className="space-y-4">
                  <div>
                    <label htmlFor="reset_password" className="block text-sm font-medium text-gray-700">
                      New Password
                    </label>
                    <input
                      type="password"
                      id="reset_password"
                      value={resetPasswordForm.data.password}
                      onChange={(e) => resetPasswordForm.setData('password', e.target.value)}
                      className="mt-1 h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                      placeholder="Minimum 8 characters"
                      required
                    />
                    {resetPasswordForm.errors.password && (
                      <p className="mt-1 text-sm text-red-600">{resetPasswordForm.errors.password}</p>
                    )}
                  </div>
                  <div>
                    <label htmlFor="reset_password_confirmation" className="block text-sm font-medium text-gray-700">
                      Confirm Password
                    </label>
                    <input
                      type="password"
                      id="reset_password_confirmation"
                      value={resetPasswordForm.data.password_confirmation}
                      onChange={(e) => resetPasswordForm.setData('password_confirmation', e.target.value)}
                      className="mt-1 h-10 px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-gray-300 rounded-md"
                      placeholder="Confirm new password"
                      required
                    />
                  </div>
                </div>
              </div>
              <div className="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={() => setResetPasswordModalOpen(false)}
                  className="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={resetPasswordForm.processing}
                  className="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                  {resetPasswordForm.processing ? 'Processing...' : 'Reset Password'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Confirm Delete</h3>
            </div>
            <div className="px-6 py-4">
              <p className="text-gray-700">
                Are you sure you want to delete this user? This action cannot be undone.
              </p>
              <div className="mt-4 p-4 bg-yellow-50 border border-yellow-100 rounded-md">
                <div className="flex">
                  <div className="flex-shrink-0">
                    <AlertCircle className="h-5 w-5 text-yellow-600" />
                  </div>
                  <div className="ml-3">
                    <h3 className="text-sm font-medium text-yellow-800">Warning</h3>
                    <p className="mt-2 text-sm text-yellow-700">
                      This will permanently delete the user {user.name} ({user.email}) from the system.
                      If this user has associated records, the deletion may not be possible.
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div className="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
              <button
                type="button"
                onClick={() => setDeleteModalOpen(false)}
                className="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDelete}
                className="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
              >
                Delete User
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
