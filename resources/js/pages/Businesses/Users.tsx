import React, { FormEvent, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, Users, X, UserPlus, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

interface User {
  id: number;
  name: string;
  email: string;
}

interface BusinessUser {
  id: number;
  name: string;
  email: string;
  role: string;
  pivot: {
    role: string;
    is_active: boolean;
  };
}

interface BusinessUsersProps {
  business: {
    id: number;
    name: string;
  };
  businessUsers: BusinessUser[];
  availableUsers: User[];
}

export default function BusinessUsers({ business, businessUsers, availableUsers }: BusinessUsersProps) {
  const [showAddUserModal, setShowAddUserModal] = useState(false);
  const [showEditRoleModal, setShowEditRoleModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState<BusinessUser | null>(null);

  // Form for adding a user
  const { data: addData, setData: setAddData, post: addUser, processing: addProcessing, errors: addErrors, reset: resetAddForm } = useForm({
    user_id: '',
    role: 'user',
  });

  // Form for editing a user role
  const { data: editData, setData: setEditData, put: updateRole, processing: updateProcessing, errors: updateErrors, reset: resetEditForm } = useForm({
    role: '',
  });

  const handleAddUser = (e: FormEvent) => {
    e.preventDefault();
    addUser(route('admin.businesses.users.attach', business.id), {
      onSuccess: () => {
        setShowAddUserModal(false);
        resetAddForm();
      },
    });
  };

  const handleEditRole = (e: FormEvent) => {
    e.preventDefault();
    if (selectedUser) {
      updateRole(route('admin.businesses.users.update', [business.id, selectedUser.id]), {
        onSuccess: () => {
          setShowEditRoleModal(false);
          setSelectedUser(null);
          resetEditForm();
        },
      });
    }
  };

  const openEditModal = (user: BusinessUser) => {
    setSelectedUser(user);
    setEditData('role', user.pivot.role);
    setShowEditRoleModal(true);
  };

  const handleDetachUser = (userId: number) => {
    if (confirm('আপনি কি নিশ্চিত যে আপনি এই ব্যবহারকারীকে ব্যবসা থেকে সরাতে চান?')) {
      // Use Inertia's delete method
      const url = route('admin.businesses.users.detach', [business.id, userId]);
      window.location.href = url;
    }
  };

  return (
    <AppLayout title={`${business.name} - ব্যবহারকারী পরিচালনা`}>
      <Head title={`ব্যবহারকারী পরিচালনা - ${business.name} - Tally Software`} />

      <div className="flex justify-between items-center mb-6">
        <Link
          href={route('businesses.index')}
          className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          ব্যবসা তালিকায় ফিরে যান
        </Link>
        <button
          onClick={() => setShowAddUserModal(true)}
          className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          <UserPlus className="h-4 w-4 mr-2" />
          নতুন ব্যবহারকারী যোগ করুন
        </button>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="flex items-center">
            <Users className="h-5 w-5 text-gray-500 mr-2" />
            <h3 className="text-lg font-medium text-gray-900">{business.name} - ব্যবহারকারী</h3>
          </div>
          <p className="mt-1 text-sm text-gray-500">
            এই ব্যবসায় অ্যাকসেস সহ ব্যবহারকারীদের পরিচালনা করুন।
          </p>
        </div>

        <div className="p-6">
          {businessUsers.length === 0 ? (
            <div className="text-center py-8">
              <Users className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">কোন ব্যবহারকারী যোগ করা হয়নি</h3>
              <p className="text-gray-500 mb-4">এই ব্যবসায় অ্যাকসেস দিতে ব্যবহারকারী যোগ করুন।</p>
              <button
                onClick={() => setShowAddUserModal(true)}
                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <UserPlus className="h-4 w-4 mr-2" />
                ব্যবহারকারী যোগ করুন
              </button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      নাম
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      ইমেইল
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      রোল
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      স্ট্যাটাস
                    </th>
                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      কার্যক্রম
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {businessUsers.map((user) => (
                    <tr key={user.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <div className="flex-shrink-0 h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                            <span className="text-sm font-medium text-gray-700">
                              {user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                            </span>
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-gray-900">{user.name}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{user.email}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                          {user.pivot.role === 'admin' && 'অ্যাডমিন'}
                          {user.pivot.role === 'accountant' && 'হিসাবরক্ষক'}
                          {user.pivot.role === 'manager' && 'ম্যানেজার'}
                          {user.pivot.role === 'user' && 'ব্যবহারকারী'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                          user.pivot.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                          {user.pivot.is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          onClick={() => openEditModal(user)}
                          className="text-blue-600 hover:text-blue-900 mr-4"
                        >
                          রোল পরিবর্তন
                        </button>
                        <button
                          onClick={() => handleDetachUser(user.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          অপসারণ
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Add User Modal */}
      {showAddUserModal && (
        <div className="fixed inset-0 overflow-y-auto z-50">
          <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div className="fixed inset-0 transition-opacity" aria-hidden="true">
              <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
              <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div className="sm:flex sm:items-start">
                  <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <div className="flex justify-between items-center">
                      <h3 className="text-lg leading-6 font-medium text-gray-900">
                        ব্যবসায় ব্যবহারকারী যোগ করুন
                      </h3>
                      <button
                        onClick={() => {
                          setShowAddUserModal(false);
                          resetAddForm();
                        }}
                        className="text-gray-400 hover:text-gray-500"
                      >
                        <X className="h-5 w-5" />
                      </button>
                    </div>

                    {Object.keys(addErrors).length > 0 && (
                      <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                        <div className="flex items-center">
                          <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                          <span className="text-red-800 font-medium">আপনার জমা দেওয়ার সময় ত্রুটি ছিল</span>
                        </div>
                        {Object.entries(addErrors).map(([key, error]) => (
                          <p key={key} className="mt-1 text-sm text-red-600">{error}</p>
                        ))}
                      </div>
                    )}

                    <form onSubmit={handleAddUser} className="mt-4">
                      <div className="space-y-4">
                        {/* User Selection */}
                        <div>
                          <label htmlFor="user_id" className="block text-sm font-medium text-gray-700">
                            ব্যবহারকারী নির্বাচন করুন <span className="text-red-500">*</span>
                          </label>
                          <select
                            id="user_id"
                            value={addData.user_id}
                            onChange={(e) => setAddData('user_id', e.target.value)}
                            className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md ${
                              addErrors.user_id ? 'border-red-300' : 'border-gray-300'
                            }`}
                            required
                          >
                            <option value="">ব্যবহারকারী নির্বাচন করুন</option>
                            {availableUsers.map((user) => (
                              <option key={user.id} value={user.id}>
                                {user.name} ({user.email})
                              </option>
                            ))}
                          </select>
                          {addErrors.user_id && (
                            <p className="mt-1 text-sm text-red-600">{addErrors.user_id}</p>
                          )}
                        </div>

                        {/* Role Selection */}
                        <div>
                          <label htmlFor="role" className="block text-sm font-medium text-gray-700">
                            রোল <span className="text-red-500">*</span>
                          </label>
                          <select
                            id="role"
                            value={addData.role}
                            onChange={(e) => setAddData('role', e.target.value)}
                            className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md ${
                              addErrors.role ? 'border-red-300' : 'border-gray-300'
                            }`}
                            required
                          >
                            <option value="user">ব্যবহারকারী</option>
                            <option value="accountant">হিসাবরক্ষক</option>
                            <option value="manager">ম্যানেজার</option>
                            <option value="admin">অ্যাডমিন</option>
                          </select>
                          {addErrors.role && (
                            <p className="mt-1 text-sm text-red-600">{addErrors.role}</p>
                          )}
                        </div>
                      </div>

                      <div className="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button
                          type="submit"
                          disabled={addProcessing}
                          className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm"
                        >
                          {addProcessing ? 'যোগ করা হচ্ছে...' : 'যোগ করুন'}
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setShowAddUserModal(false);
                            resetAddForm();
                          }}
                          className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm"
                        >
                          বাতিল
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Edit Role Modal */}
      {showEditRoleModal && selectedUser && (
        <div className="fixed inset-0 overflow-y-auto z-50">
          <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div className="fixed inset-0 transition-opacity" aria-hidden="true">
              <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
              <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div className="sm:flex sm:items-start">
                  <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <div className="flex justify-between items-center">
                      <h3 className="text-lg leading-6 font-medium text-gray-900">
                        {selectedUser.name} - রোল পরিবর্তন
                      </h3>
                      <button
                        onClick={() => {
                          setShowEditRoleModal(false);
                          setSelectedUser(null);
                          resetEditForm();
                        }}
                        className="text-gray-400 hover:text-gray-500"
                      >
                        <X className="h-5 w-5" />
                      </button>
                    </div>

                    {Object.keys(updateErrors).length > 0 && (
                      <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                        <div className="flex items-center">
                          <AlertTriangle className="h-5 w-5 text-red-500 mr-2" />
                          <span className="text-red-800 font-medium">আপনার জমা দেওয়ার সময় ত্রুটি ছিল</span>
                        </div>
                        {Object.entries(updateErrors).map(([key, error]) => (
                          <p key={key} className="mt-1 text-sm text-red-600">{error}</p>
                        ))}
                      </div>
                    )}

                    <form onSubmit={handleEditRole} className="mt-4">
                      <div>
                        <label htmlFor="edit_role" className="block text-sm font-medium text-gray-700">
                          রোল <span className="text-red-500">*</span>
                        </label>
                        <select
                          id="edit_role"
                          value={editData.role}
                          onChange={(e) => setEditData('role', e.target.value)}
                          className={`mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md ${
                            updateErrors.role ? 'border-red-300' : 'border-gray-300'
                          }`}
                          required
                        >
                          <option value="user">ব্যবহারকারী</option>
                          <option value="accountant">হিসাবরক্ষক</option>
                          <option value="manager">ম্যানেজার</option>
                          <option value="admin">অ্যাডমিন</option>
                        </select>
                        {updateErrors.role && (
                          <p className="mt-1 text-sm text-red-600">{updateErrors.role}</p>
                        )}
                      </div>

                      <div className="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button
                          type="submit"
                          disabled={updateProcessing}
                          className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm"
                        >
                          {updateProcessing ? 'আপডেট হচ্ছে...' : 'আপডেট করুন'}
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setShowEditRoleModal(false);
                            setSelectedUser(null);
                            resetEditForm();
                          }}
                          className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm"
                        >
                          বাতিল
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
