<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $role = $request->input('role');
        $status = $request->input('status');

        // Query users with filters
        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $users = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('settings/Users/Index', props: [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'roles' => [
                'admin' => 'অ্যাডমিন',
                'accountant' => 'হিসাবরক্ষক',
                'manager' => 'ম্যানেজার',
                'user' => 'ব্যবহারকারী',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('settings/Users/Create', [
            'roles' => [
                'admin' => 'অ্যাডমিন',
                'accountant' => 'হিসাবরক্ষক',
                'manager' => 'ম্যানেজার',
                'user' => 'ব্যবহারকারী',
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,accountant,manager,user',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ])
            ->log('created');

        return redirect()->route('settings.users.index')
            ->with('success', 'ব্যবহারকারী সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // Get activity logs for this user
        $activityLogs = AuditLog::where('user_id', $user->id)
        ->latest()
        ->limit(20)
        ->get();

        return Inertia::render('settings/Users/Show', [
            'user' => $user,
            'activityLogs' => $activityLogs,
            'roles' => [
                'admin' => 'অ্যাডমিন',
                'accountant' => 'হিসাবরক্ষক',
                'manager' => 'ম্যানেজার',
                'user' => 'ব্যবহারকারী',
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return Inertia::render('settings/Users/Edit', [
            'user' => $user,
            'roles' => [
                'admin' => 'অ্যাডমিন',
                'accountant' => 'হিসাবরক্ষক',
                'manager' => 'ম্যানেজার',
                'user' => 'ব্যবহারকারী',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,accountant,manager,user',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ];

        // Only update password if provided
        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('settings.users.index')
            ->with('success', 'ব্যবহারকারী সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Check if the user is the authenticated user
        if ($user->id === Auth::id()) {
            return back()->with('error', 'আপনি নিজেকে মুছে ফেলতে পারবেন না।');
        }

        // Check if the user has related records
        if ($user->employee()->exists() || $user->journalEntries()->exists()) {
            return back()->with('error', 'এই ব্যবহারকারীর সাথে সম্পর্কিত রেকর্ড আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store user details for audit log
        $userDetails = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $user->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($userDetails)
            ->log('deleted user');

        return redirect()->route('settings.users.index')
            ->with('success', 'ব্যবহারকারী সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the user.
     */
    public function toggleStatus(User $user)
    {
        // Check if the user is the authenticated user
        if ($user->id === Auth::id()) {
            return back()->with('error', 'আপনি নিজের স্ট্যাটাস পরিবর্তন করতে পারবেন না।');
        }

        $oldStatus = $user->is_active;
        $user->is_active = !$oldStatus;
        $user->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $user->is_active,
            ])
            ->log('toggled status');

        $statusText = $user->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "ব্যবহারকারী সফলভাবে {$statusText} করা হয়েছে।");
    }

    /**
     * Reset the password of the user.
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->log('reset password');

        return back()->with('success', 'পাসওয়ার্ড সফলভাবে রিসেট করা হয়েছে।');
    }
}
