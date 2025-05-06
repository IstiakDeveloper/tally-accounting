<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AccountCategoryController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = AccountCategory::where('business_id', session('active_business_id'))
            ->with('accounts')
            ->get();

        return Inertia::render('Accounting/AccountCategories/Index', [
            'categories' => $categories,
            'typeLabels' => [
                'Asset' => 'Asset',
                'Liability' => 'Liability',
                'Equity' => 'Equity',
                'Revenue' => 'Revenue',
                'Expense' => 'Expense',
            ],
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $activeBusiness = Auth::user()->businesses()->find(session('active_business_id'));
        $businesses = Auth::user()->businesses;

        return Inertia::render('Accounting/AccountCategories/Create', [
            'types' => [
                'Asset' => 'Asset',
                'Liability' => 'Liability',
                'Equity' => 'Equity',
                'Revenue' => 'Revenue',
                'Expense' => 'Expense',
            ],
            'businesses' => $businesses,
            'activeBusiness' => $activeBusiness,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'business_id' => 'required|exists:businesses,id',
        ]);

        $businessId = session('active_business_id');

        // Check if business_id exists
        if (!$businessId) {
            return redirect()->back()->with('error', 'No active business selected. Please select a business first.');
        }

        // Add business_id to the validated data
        $validated['business_id'] = $businessId;
        $validated['created_by'] = Auth::id();

        $category = AccountCategory::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($category)
            ->withProperties([
                'name' => $category->name,
                'type' => $category->type,
            ])
            ->log('created');

        return redirect()->route('account-categories.index')
            ->with('success', 'Account category created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountCategory $accountCategory)
    {
        return Inertia::render('Accounting/AccountCategories/Edit', [
            'category' => $accountCategory,
            'types' => [
                'Asset' => 'Asset',
                'Liability' => 'Liability',
                'Equity' => 'Equity',
                'Revenue' => 'Revenue',
                'Expense' => 'Expense',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccountCategory $accountCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $accountCategory->name,
            'type' => $accountCategory->type,
        ];

        $accountCategory->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($accountCategory)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $accountCategory->name,
                    'type' => $accountCategory->type,
                ],
            ])
            ->log('updated');

        return redirect()->route('account-categories.index')
            ->with('success', 'অ্যাকাউন্ট ক্যাটাগরি সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountCategory $accountCategory)
    {
        // Check if the category has any accounts
        if ($accountCategory->accounts()->exists()) {
            return back()->with('error', 'এই ক্যাটাগরির অধীনে অ্যাকাউন্ট আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store category details for audit log
        $categoryDetails = [
            'id' => $accountCategory->id,
            'name' => $accountCategory->name,
            'type' => $accountCategory->type,
        ];

        $accountCategory->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($categoryDetails)
            ->log('deleted account category');

        return redirect()->route('account-categories.index')
            ->with('success', 'অ্যাকাউন্ট ক্যাটাগরি সফলভাবে মুছে ফেলা হয়েছে।');
    }
}
