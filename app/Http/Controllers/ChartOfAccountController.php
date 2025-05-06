<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $categoryType = $request->input('category_type');
        $status = $request->input('status');

        // Query accounts with filters
        $query = ChartOfAccount::with('category', 'createdBy')
            ->where('business_id', session('active_business_id'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('account_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryType) {
            $query->whereHas('category', function ($q) use ($categoryType) {
                $q->where('type', $categoryType);
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $accounts = $query->orderBy('account_code')->paginate(10)
            ->withQueryString();

        // Get all account categories for the filter dropdown
        $categories = AccountCategory::all();

        return Inertia::render('Accounting/ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category_type' => $categoryType,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = AccountCategory::all();

        return Inertia::render('Accounting/ChartOfAccounts/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:20|unique:chart_of_accounts,account_code',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:account_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['business_id'] = session('active_business_id');
        $validated['created_by'] = Auth::id();

        $account = ChartOfAccount::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($account)
            ->withProperties([
                'account_code' => $account->account_code,
                'name' => $account->name,
            ])
            ->log('created');

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'হিসাব সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->load('category', 'createdBy');

        // Get account balance
        $balance = $chartOfAccount->getBalance();

        // Get recent journal entries for this account
        $journalItems = $chartOfAccount->journalItems()
            ->with('journalEntry')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Accounting/ChartOfAccounts/Show', [
            'account' => $chartOfAccount,
            'balance' => $balance,
            'journalItems' => $journalItems,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChartOfAccount $chartOfAccount)
    {
        $categories = AccountCategory::all();

        return Inertia::render('Accounting/ChartOfAccounts/Edit', [
            'account' => $chartOfAccount,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $validated = $request->validate([
            'account_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts')->ignore($chartOfAccount->id),
            ],
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:account_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'account_code' => $chartOfAccount->account_code,
            'name' => $chartOfAccount->name,
            'category_id' => $chartOfAccount->category_id,
            'description' => $chartOfAccount->description,
            'is_active' => $chartOfAccount->is_active,
        ];

        $chartOfAccount->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($chartOfAccount)
            ->withProperties([
                'old' => $oldValues,
                'new' => $validated,
            ])
            ->log('updated');

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'হিসাব সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChartOfAccount $chartOfAccount)
    {
        // Check if the account has any related journal items
        if ($chartOfAccount->journalItems()->exists()) {
            return back()->with('error', 'এই হিসাবের সাথে লেনদেন সংযুক্ত আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store account details for audit log
        $accountDetails = [
            'id' => $chartOfAccount->id,
            'account_code' => $chartOfAccount->account_code,
            'name' => $chartOfAccount->name,
        ];

        $chartOfAccount->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($accountDetails)
            ->log('deleted account');

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'হিসাব সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the account.
     */
    public function toggleStatus(ChartOfAccount $chartOfAccount)
    {
        $oldStatus = $chartOfAccount->is_active;
        $chartOfAccount->is_active = !$oldStatus;
        $chartOfAccount->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($chartOfAccount)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $chartOfAccount->is_active,
            ])
            ->log('toggled status');

        $statusText = $chartOfAccount->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "হিসাব সফলভাবে {$statusText} করা হয়েছে।");
    }
}
