<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $businesses = Auth::user()->businesses;
        $activeBusiness = Business::find(session('active_business_id'));

        return Inertia::render('Businesses/Index', [
            'businesses' => $businesses,
            'activeBusiness' => $activeBusiness,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Businesses/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:businesses,code',
            'legal_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        $business = Business::create($validated);

        // Attach user with admin role
        $business->users()->attach(Auth::id(), ['role' => 'admin', 'is_active' => true]);

        // Set as active business
        session(['active_business_id' => $business->id]);

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($business)
            ->withProperties([
                'name' => $business->name,
                'code' => $business->code,
            ])
            ->log('created');

        return redirect()->route('businesses.index')
            ->with('success', 'ব্যবসা সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(Business $business)
    {
        // Check if user has access to this business
        if (!Auth::user()->businesses->contains($business->id)) {
            return redirect()->route('businesses.index')
                ->with('error', 'আপনার এই ব্যবসায় অ্যাক্সেস নেই।');
        }

        $business->load('chartOfAccounts', 'financialYears', 'createdBy');

        return Inertia::render('Businesses/Show', [
            'business' => $business,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Business $business)
    {
        // Check if user has access to this business
        if (!Auth::user()->businesses->contains($business->id)) {
            return redirect()->route('businesses.index')
                ->with('error', 'আপনার এই ব্যবসা সম্পাদনা করার অনুমতি নেই।');
        }

        return Inertia::render('Businesses/Edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {
        // Check if user has access to this business
        if (!Auth::user()->businesses->contains($business->id)) {
            return redirect()->route('businesses.index')
                ->with('error', 'আপনার এই ব্যবসা সম্পাদনা করার অনুমতি নেই।');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('businesses')->ignore($business->id),
            ],
            'legal_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $business->name,
            'code' => $business->code,
            'is_active' => $business->is_active,
        ];

        $business->update($validated);

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($business)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $business->name,
                    'code' => $business->code,
                    'is_active' => $business->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('businesses.index')
            ->with('success', 'ব্যবসা সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        // Deny deletion if this is the only business or the active business
        if (Auth::user()->businesses->count() <= 1) {
            return back()->with('error', 'আপনার কমপক্ষে একটি ব্যবসা থাকতে হবে।');
        }

        if (session('active_business_id') == $business->id) {
            return back()->with('error', 'সক্রিয় ব্যবসা মুছে ফেলা যাবে না। অন্য ব্যবসায় স্যুইচ করুন।');
        }

        // Check if user has admin access to this business
        $pivot = Auth::user()->businesses()->where('business_id', $business->id)->first()->pivot;
        if ($pivot->role !== 'admin') {
            return back()->with('error', 'শুধুমাত্র অ্যাডমিন ব্যবসা মুছে ফেলতে পারেন।');
        }

        // Store business details for audit log
        $businessDetails = [
            'id' => $business->id,
            'name' => $business->name,
            'code' => $business->code,
        ];

        // Detach all users
        $business->users()->detach();

        // Delete business
        $business->delete();

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($businessDetails)
            ->log('deleted business');

        return redirect()->route('businesses.index')
            ->with('success', 'ব্যবসা সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Switch to the specified business.
     */
    public function switch(Business $business)
    {
        // Check if user has access to this business
        if (!Auth::user()->businesses->contains($business->id)) {
            return redirect()->route('businesses.index')
                ->with('error', 'আপনার এই ব্যবসায় অ্যাক্সেস নেই।');
        }

        // Check if business is active
        if (!$business->is_active) {
            return redirect()->route('businesses.index')
                ->with('error', 'নিষ্ক্রিয় ব্যবসায় স্যুইচ করা যাবে না।');
        }

        // Set active business in session
        session(['active_business_id' => $business->id]);

        return redirect()->route('dashboard')
            ->with('success', 'ব্যবসা পরিবর্তন করা হয়েছে: ' . $business->name);
    }

    /**
     * Show users of the specified business.
     */
    public function users(Business $business)
    {
        // Check if user has admin access to this business
        $pivot = Auth::user()->businesses()->where('business_id', $business->id)->first()->pivot;
        if ($pivot->role !== 'admin') {
            return redirect()->route('businesses.index')
                ->with('error', 'শুধুমাত্র অ্যাডমিন ব্যবহারকারী পরিচালনা করতে পারেন।');
        }

        // Get business users
        $businessUsers = $business->users;

        // Get users not already associated with this business
        $allUsers = User::where('is_active', true)->get();
        $availableUsers = $allUsers->diff($businessUsers);

        return Inertia::render('Businesses/Users', [
            'business' => $business,
            'businessUsers' => $businessUsers,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Attach a user to the business.
     */
    public function attachUser(Request $request, Business $business)
    {
        // Check if user has admin access to this business
        $pivot = Auth::user()->businesses()->where('business_id', $business->id)->first()->pivot;
        if ($pivot->role !== 'admin') {
            return back()->with('error', 'শুধুমাত্র অ্যাডমিন ব্যবহারকারী যোগ করতে পারেন।');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,accountant,manager,user',
        ]);

        // Check if user is already associated with this business
        if ($business->users()->where('user_id', $validated['user_id'])->exists()) {
            return back()->with('error', 'ব্যবহারকারী ইতিমধ্যে এই ব্যবসার সাথে যুক্ত আছে।');
        }

        // Attach user to business
        $business->users()->attach($validated['user_id'], [
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        // Get user name for activity log
        $user = User::find($validated['user_id']);

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($business)
            ->withProperties([
                'user_id' => $validated['user_id'],
                'user_name' => $user->name,
                'role' => $validated['role'],
            ])
            ->log('attached user to business');

        return back()->with('success', 'ব্যবহারকারী সফলভাবে ব্যবসায় যোগ করা হয়েছে।');
    }

    /**
     * Update user role in the business.
     */
    public function updateUserRole(Request $request, Business $business, User $user)
    {
        // Check if user has admin access to this business
        $pivot = Auth::user()->businesses()->where('business_id', $business->id)->first()->pivot;
        if ($pivot->role !== 'admin') {
            return back()->with('error', 'শুধুমাত্র অ্যাডমিন ব্যবহারকারীর রোল পরিবর্তন করতে পারেন।');
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,accountant,manager,user',
        ]);

        // Get current role for activity log
        $currentRole = $business->users()->where('user_id', $user->id)->first()->pivot->role;

        // Update user role
        $business->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($business)
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'old_role' => $currentRole,
                'new_role' => $validated['role'],
            ])
            ->log('updated user role in business');

        return back()->with('success', 'ব্যবহারকারীর রোল সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Detach a user from the business.
     */
    public function detachUser(Business $business, User $user)
    {
        // Check if user has admin access to this business
        $pivot = Auth::user()->businesses()->where('business_id', $business->id)->first()->pivot;
        if ($pivot->role !== 'admin') {
            return back()->with('error', 'শুধুমাত্র অ্যাডমিন ব্যবহারকারী অপসারণ করতে পারেন।');
        }

        // Prevent removing yourself if you are the only admin
        if (Auth::id() == $user->id) {
            $adminCount = $business->users()->wherePivot('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'আপনি নিজেকে অপসারণ করতে পারবেন না কারণ আপনি একমাত্র অ্যাডমিন।');
            }
        }

        // Get user role for activity log
        $userRole = $business->users()->where('user_id', $user->id)->first()->pivot->role;

        // Detach user from business
        $business->users()->detach($user->id);

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($business)
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $userRole,
            ])
            ->log('detached user from business');

        return back()->with('success', 'ব্যবহারকারী সফলভাবে ব্যবসা থেকে অপসারণ করা হয়েছে।');
    }
}
