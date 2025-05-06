<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\AccountCategory;
use App\Models\CompanySetting;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the bank accounts.
     */
    /**
     * Display a listing of the bank accounts.
     */
    public function index(Request $request)
    {
        // Get the active business ID
        $businessId = session('active_business_id');

        // Make sure a business is selected
        if (!$businessId) {
            return redirect()->route('dashboard')
                ->with('error', 'Please select a business first');
        }

        // Get active business and all user's businesses for the selector
        $activeBusiness = Auth::user()->businesses()->find($businessId);
        $businesses = Auth::user()->businesses;

        // Start query with business_id filter
        $query = BankAccount::with('account')
            ->where('business_id', $businessId);

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by bank name
        if ($request->has('bank_name') && $request->bank_name) {
            $query->where('bank_name', 'like', '%' . $request->bank_name . '%');
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_name', 'like', '%' . $search . '%')
                    ->orWhere('account_number', 'like', '%' . $search . '%')
                    ->orWhere('bank_name', 'like', '%' . $search . '%')
                    ->orWhere('branch_name', 'like', '%' . $search . '%');
            });
        }

        $bankAccounts = $query->orderBy('bank_name')->paginate(10)->withQueryString();

        // Calculate balances for each bank account
        foreach ($bankAccounts as $account) {
            // Load the balance from chart of accounts
            if ($account->account) {
                $chartAccount = $account->account;

                // Calculate debits and credits for this account
                $debits = JournalItem::whereHas('journalEntry', function ($query) use ($businessId) {
                    $query->where('status', 'posted')
                        ->where('business_id', $businessId);
                })
                    ->where('account_id', $chartAccount->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) use ($businessId) {
                    $query->where('status', 'posted')
                        ->where('business_id', $businessId);
                })
                    ->where('account_id', $chartAccount->id)
                    ->where('type', 'credit')
                    ->sum('amount');

                // For bank accounts (assets), balance = debits - credits
                $account->balance = $debits - $credits;
                $account->formatted_balance = number_format($account->balance, 2);
            } else {
                $account->balance = 0;
                $account->formatted_balance = number_format(0, 2);
            }
        }

        // Calculate total balance
        $totalBalance = $bankAccounts->sum('balance');

        // Get company settings for currency symbol
        $companySetting = CompanySetting::where('business_id', $businessId)->first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
            'totalBalance' => $totalBalance,
            'formattedTotalBalance' => $currencySymbol . ' ' . number_format($totalBalance, 2),
            'filters' => $request->only(['status', 'bank_name', 'search']),
            'companySetting' => $companySetting,
            'businesses' => $businesses,
            'activeBusiness' => $activeBusiness,
        ]);
    }

    /**
     * Show the form for creating a new bank account.
     */
    public function create()
    {
        $businessId = session('active_business_id');

        // Make sure a business is selected
        if (!$businessId) {
            return redirect()->route('dashboard')
                ->with('error', 'Please select a business first');
        }

        $activeBusiness = Auth::user()->businesses()->find($businessId);
        $businesses = Auth::user()->businesses;

        // Log all chart accounts for this business to debug
        $allBusinessAccounts = ChartOfAccount::where('business_id', $businessId)->get();
        \Log::info('All chart accounts for business ' . $businessId . ':', $allBusinessAccounts->toArray());

        // Get all asset categories for this business
        $assetCategories = AccountCategory::where('type', 'Asset')
            ->where('business_id', $businessId)
            ->get();

        \Log::info('Asset categories for business ' . $businessId . ':', $assetCategories->toArray());

        $chartAccounts = [];

        // Use a simpler approach to get accounts
        $chartAccounts = ChartOfAccount::where('business_id', $businessId)
            ->whereHas('category', function ($query) {
                $query->where('type', 'Asset');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        \Log::info('Found ' . count($chartAccounts) . ' chart accounts for business ID: ' . $businessId);

        return Inertia::render('BankAccounts/Create', [
            'chartAccounts' => $chartAccounts,
            'businesses' => $businesses,
            'activeBusiness' => $activeBusiness,
        ]);
    }

    /**
     * Store a newly created bank account in storage.
     */
    public function store(Request $request)
    {
        $businessId = session('active_business_id');

        if (!$businessId) {
            return redirect()->back()->with('error', 'No active business selected. Please select a business first.');
        }

        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,NULL,id,business_id,' . $businessId,
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'routing_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
            'initial_balance' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $bankAccount = BankAccount::create([
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'swift_code' => $request->swift_code,
                'routing_number' => $request->routing_number,
                'address' => $request->address,
                'contact_person' => $request->contact_person,
                'contact_number' => $request->contact_number,
                'account_id' => $request->account_id,
                'is_active' => $request->is_active ?? true,
                'business_id' => $businessId,
                'created_by' => Auth::id(),
            ]);

            // === Initial Balance Handling ===
            if ($request->initial_balance && $request->initial_balance != 0) {
                $financialYear = \App\Models\FinancialYear::where('is_active', true)
                    ->where('business_id', $businessId)
                    ->first();

                if (!$financialYear) {
                    throw new \Exception('No active financial year found for this business.');
                }

                $companySetting = CompanySetting::where('business_id', $businessId)->first();
                $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

                $journalEntry = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'INITIAL-BANK-' . $bankAccount->id,
                    'financial_year_id' => $financialYear->id,
                    'entry_date' => Carbon::now(),
                    'narration' => 'Initial balance for bank account: ' . $bankAccount->account_name,
                    'status' => 'posted',
                    'business_id' => $businessId,
                    'created_by' => Auth::id(),
                ]);

                $initialBalance = $request->initial_balance;

                // === Opening Balance Equity Account ===
                $equityCategory = AccountCategory::firstOrCreate(
                    ['type' => 'Equity', 'business_id' => $businessId],
                    ['name' => 'Equity', 'created_by' => Auth::id()]
                );

                $openingBalanceAccount = ChartOfAccount::where('name', 'Opening Balance Equity')
                    ->where('business_id', $businessId)
                    ->first();

                if (!$openingBalanceAccount) {
                    // Generate a new unique account code dynamically
                    $lastCode = ChartOfAccount::where('business_id', $businessId)
                        ->orderByDesc('account_code')
                        ->value('account_code');

                    $newCode = $lastCode ? ((int)$lastCode + 1) : 3000;

                    $openingBalanceAccount = ChartOfAccount::create([
                        'account_code' => $newCode,
                        'name' => 'Opening Balance Equity',
                        'category_id' => $equityCategory->id,
                        'description' => 'Account for initial balances',
                        'is_active' => true,
                        'business_id' => $businessId,
                        'created_by' => Auth::id(),
                    ]);
                }

                if ($initialBalance > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->account_id,
                        'type' => 'debit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance',
                    ]);

                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $openingBalanceAccount->id,
                        'type' => 'credit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance for ' . $bankAccount->account_name,
                    ]);
                } else {
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->account_id,
                        'type' => 'credit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance',
                    ]);

                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $openingBalanceAccount->id,
                        'type' => 'debit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance for ' . $bankAccount->account_name,
                    ]);
                }
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($bankAccount)
                ->withProperties([
                    'account_name' => $bankAccount->account_name,
                    'bank_name' => $bankAccount->bank_name,
                    'business_id' => $businessId,
                ])
                ->log('created');

            DB::commit();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank account created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bank Account Creation Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => Auth::id(),
                'business_id' => $businessId
            ]);
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * Display the specified bank account.
     */
    public function show(BankAccount $bankAccount)
    {
        // Ensure the bank account belongs to the active business
        $businessId = session('active_business_id');

        if ($bankAccount->business_id != $businessId) {
            return redirect()->route('bank-accounts.index')
                ->with('error', 'You do not have access to this bank account.');
        }

        $bankAccount->load('account');

        // Calculate balance
        if ($bankAccount->account) {
            $chartAccount = $bankAccount->account;

            // Calculate debits and credits for this account
            $debits = JournalItem::whereHas('journalEntry', function ($query) use ($businessId) {
                $query->where('status', 'posted')
                    ->where('business_id', $businessId);
            })
                ->where('account_id', $chartAccount->id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) use ($businessId) {
                $query->where('status', 'posted')
                    ->where('business_id', $businessId);
            })
                ->where('account_id', $chartAccount->id)
                ->where('type', 'credit')
                ->sum('amount');

            // For bank accounts (assets), balance = debits - credits
            $bankAccount->balance = $debits - $credits;
            $bankAccount->formatted_balance = number_format($bankAccount->balance, 2);
        } else {
            $bankAccount->balance = 0;
            $bankAccount->formatted_balance = number_format(0, 2);
        }

        // Get recent transactions
        $recentTransactions = [];
        if ($bankAccount->account) {
            $journalItems = JournalItem::with('journalEntry')
                ->whereHas('journalEntry', function ($query) use ($businessId) {
                    $query->where('status', 'posted')
                        ->where('business_id', $businessId)
                        ->orderBy('entry_date', 'desc');
                })
                ->where('account_id', $bankAccount->account_id)
                ->take(10)
                ->get();

            $runningBalance = $bankAccount->balance;
            foreach ($journalItems as $item) {
                // Adjust running balance for each transaction (showing most recent first)
                if ($item->type === 'debit') {
                    $runningBalance -= $item->amount;
                } else {
                    $runningBalance += $item->amount;
                }

                $recentTransactions[] = [
                    'date' => $item->journalEntry->entry_date,
                    'reference' => $item->journalEntry->reference_number,
                    'description' => $item->journalEntry->narration,
                    'type' => $item->type,
                    'amount' => $item->amount,
                    'balance' => $runningBalance,
                    'formatted_amount' => number_format($item->amount, 2),
                    'formatted_balance' => number_format($runningBalance, 2),
                ];
            }

            // Reverse to show most recent first
            $recentTransactions = array_reverse($recentTransactions);
        }

        // Get company settings for currency symbol
        $companySetting = CompanySetting::where('business_id', $businessId)->first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/Show', [
            'bankAccount' => $bankAccount,
            'balance' => $bankAccount->balance,
            'formattedBalance' => $currencySymbol . ' ' . $bankAccount->formatted_balance,
            'recentTransactions' => $recentTransactions,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for editing the specified bank account.
     */
    public function edit(BankAccount $bankAccount)
    {
        // Ensure the bank account belongs to the active business
        $businessId = session('active_business_id');

        if ($bankAccount->business_id != $businessId) {
            return redirect()->route('bank-accounts.index')
                ->with('error', 'You do not have access to this bank account.');
        }

        $bankAccount->load('account');

        $activeBusiness = Auth::user()->businesses()->find($businessId);
        $businesses = Auth::user()->businesses;

        // Get asset accounts for bank account association
        $assetCategory = AccountCategory::where('type', 'Asset')
            ->where('business_id', $businessId)
            ->first();

        $chartAccounts = [];
        if ($assetCategory) {
            $chartAccounts = ChartOfAccount::where('category_id', $assetCategory->id)
                ->where('is_active', true)
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('BankAccounts/Edit', [
            'bankAccount' => $bankAccount,
            'chartAccounts' => $chartAccounts,
            'businesses' => $businesses,
            'activeBusiness' => $activeBusiness,
        ]);
    }

    /**
     * Update the specified bank account in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        // Ensure the bank account belongs to the active business
        $businessId = session('active_business_id');

        if ($bankAccount->business_id != $businessId) {
            return redirect()->route('bank-accounts.index')
                ->with('error', 'You do not have access to this bank account.');
        }

        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,' . $bankAccount->id . ',id,business_id,' . $businessId,
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'routing_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $bankAccount->update([
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'swift_code' => $request->swift_code,
                'routing_number' => $request->routing_number,
                'address' => $request->address,
                'contact_person' => $request->contact_person,
                'contact_number' => $request->contact_number,
                'account_id' => $request->account_id,
                'is_active' => $request->is_active ?? $bankAccount->is_active,
                // business_id already set and doesn't change
            ]);

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($bankAccount)
                ->withProperties([
                    'account_name' => $bankAccount->account_name,
                    'bank_name' => $bankAccount->bank_name,
                    'business_id' => $businessId,
                ])
                ->log('updated');

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank account updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified bank account from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        // Ensure the bank account belongs to the active business
        $businessId = session('active_business_id');

        if ($bankAccount->business_id != $businessId) {
            return redirect()->route('bank-accounts.index')
                ->with('error', 'You do not have access to this bank account.');
        }

        try {
            // Check if the bank account has transactions
            $hasTransactions = JournalItem::whereHas('journalEntry', function ($query) use ($businessId) {
                $query->where('status', 'posted')
                    ->where('business_id', $businessId);
            })
                ->where('account_id', $bankAccount->account_id)
                ->exists();

            if ($hasTransactions) {
                return back()->with('error', 'Cannot delete bank account with existing transactions.');
            }

            // Check if the bank account is used in payments
            $isUsedInPayments = Payment::where('account_id', $bankAccount->account_id)
                ->where('business_id', $businessId)
                ->exists();

            if ($isUsedInPayments) {
                return back()->with('error', 'Cannot delete bank account used in payments.');
            }

            // Log the activity before deletion
            activity()
                ->causedBy(Auth::user())
                ->performedOn($bankAccount)
                ->withProperties([
                    'account_name' => $bankAccount->account_name,
                    'bank_name' => $bankAccount->bank_name,
                    'business_id' => $businessId,
                ])
                ->log('deleted');

            $bankAccount->delete();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank account deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}
