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
        $categories = AccountCategory::with('accounts')->get();

        return Inertia::render('Accounting/AccountCategories/Index', [
            'categories' => $categories,
            'typeLabels' => [
                'Asset' => 'সম্পদ',
                'Liability' => 'দায়',
                'Equity' => 'মালিকানা',
                'Revenue' => 'আয়',
                'Expense' => 'ব্যয়',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Accounting/AccountCategories/Create', [
            'types' => [
                'Asset' => 'সম্পদ',
                'Liability' => 'দায়',
                'Equity' => 'মালিকানা',
                'Revenue' => 'আয়',
                'Expense' => 'ব্যয়',
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
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
        ]);

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
            ->with('success', 'অ্যাকাউন্ট ক্যাটাগরি সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountCategory $accountCategory)
    {
        return Inertia::render('Accounting/AccountCategories/Edit', [
            'category' => $accountCategory,
            'types' => [
                'Asset' => 'সম্পদ',
                'Liability' => 'দায়',
                'Equity' => 'মালিকানা',
                'Revenue' => 'আয়',
                'Expense' => 'ব্যয়',
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
