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
    public function index(Request $request)
    {
        $query = BankAccount::with('account');

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
                $debits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
                    })
                    ->where('account_id', $chartAccount->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
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
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
            'totalBalance' => $totalBalance,
            'formattedTotalBalance' => $currencySymbol . ' ' . number_format($totalBalance, 2),
            'filters' => $request->only(['status', 'bank_name', 'search']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for creating a new bank account.
     */
    public function create()
    {
        // Get asset accounts for bank account association
        $assetCategory = AccountCategory::where('type', 'Asset')->first();

        $chartAccounts = [];
        if ($assetCategory) {
            $chartAccounts = ChartOfAccount::where('category_id', $assetCategory->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('BankAccounts/Create', [
            'chartAccounts' => $chartAccounts,
        ]);
    }

    /**
     * Store a newly created bank account in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts',
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

            // Create bank account
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
            ]);

            // If initial balance is provided, create a journal entry
            if ($request->initial_balance && $request->initial_balance != 0) {
                // Get active financial year
                $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

                if (!$financialYear) {
                    throw new \Exception('No active financial year found.');
                }

                // Get company settings for journal prefix
                $companySetting = CompanySetting::first();
                $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

                // Create journal entry
                $journalEntry = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'INITIAL-BANK-' . $bankAccount->id,
                    'financial_year_id' => $financialYear->id,
                    'entry_date' => Carbon::now(),
                    'narration' => 'Initial balance for bank account: ' . $bankAccount->account_name,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                $initialBalance = $request->initial_balance;

                if ($initialBalance > 0) {
                    // For positive balance, debit bank account
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->account_id,
                        'type' => 'debit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance',
                    ]);

                    // Credit opening balance equity account
                    $equityCategory = AccountCategory::where('type', 'Equity')->first();
                    $openingBalanceAccount = ChartOfAccount::where('name', 'like', '%Opening Balance Equity%')
                        ->where('category_id', $equityCategory->id)
                        ->first();

                    if (!$openingBalanceAccount) {
                        // Create opening balance equity account if it doesn't exist
                        $openingBalanceAccount = ChartOfAccount::create([
                            'account_code' => '3000',
                            'name' => 'Opening Balance Equity',
                            'category_id' => $equityCategory->id,
                            'description' => 'Account for initial balances',
                            'is_active' => true,
                            'created_by' => Auth::id(),
                        ]);
                    }

                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $openingBalanceAccount->id,
                        'type' => 'credit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance for ' . $bankAccount->account_name,
                    ]);
                } else {
                    // For negative balance, credit bank account
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->account_id,
                        'type' => 'credit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance',
                    ]);

                    // Debit opening balance equity account
                    $equityCategory = AccountCategory::where('type', 'Equity')->first();
                    $openingBalanceAccount = ChartOfAccount::where('name', 'like', '%Opening Balance Equity%')
                        ->where('category_id', $equityCategory->id)
                        ->first();

                    if (!$openingBalanceAccount) {
                        // Create opening balance equity account if it doesn't exist
                        $openingBalanceAccount = ChartOfAccount::create([
                            'account_code' => '3000',
                            'name' => 'Opening Balance Equity',
                            'category_id' => $equityCategory->id,
                            'description' => 'Account for initial balances',
                            'is_active' => true,
                            'created_by' => Auth::id(),
                        ]);
                    }

                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $openingBalanceAccount->id,
                        'type' => 'debit',
                        'amount' => abs($initialBalance),
                        'description' => 'Initial balance for ' . $bankAccount->account_name,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank account created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified bank account.
     */
    public function show(BankAccount $bankAccount)
    {
        $bankAccount->load('account');

        // Calculate balance
        if ($bankAccount->account) {
            $chartAccount = $bankAccount->account;

            // Calculate debits and credits for this account
            $debits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $chartAccount->id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
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
                ->whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted')
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
        $companySetting = CompanySetting::first();
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
        $bankAccount->load('account');

        // Get asset accounts for bank account association
        $assetCategory = AccountCategory::where('type', 'Asset')->first();

        $chartAccounts = [];
        if ($assetCategory) {
            $chartAccounts = ChartOfAccount::where('category_id', $assetCategory->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('BankAccounts/Edit', [
            'bankAccount' => $bankAccount,
            'chartAccounts' => $chartAccounts,
        ]);
    }

    /**
     * Update the specified bank account in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,' . $bankAccount->id,
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
            ]);

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
        try {
            // Check if the bank account has transactions
            $hasTransactions = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $bankAccount->account_id)
                ->exists();

            if ($hasTransactions) {
                return back()->with('error', 'Cannot delete bank account with existing transactions.');
            }

            // Check if the bank account is used in payments
            $isUsedInPayments = Payment::where('account_id', $bankAccount->account_id)->exists();

            if ($isUsedInPayments) {
                return back()->with('error', 'Cannot delete bank account used in payments.');
            }

            $bankAccount->delete();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank account deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the active status of the bank account.
     */
    public function toggleStatus(BankAccount $bankAccount)
    {
        try {
            $bankAccount->update([
                'is_active' => !$bankAccount->is_active,
            ]);

            return back()->with('success', 'Bank account status updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Get bank account statement.
     */
    public function statement(Request $request, BankAccount $bankAccount)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $fromDate = Carbon::parse($request->from_date);
        $toDate = Carbon::parse($request->to_date);

        $bankAccount->load('account');

        if (!$bankAccount->account) {
            return back()->with('error', 'Bank account is not associated with a chart account.');
        }

        // Get opening balance
        $openingDebits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<', $fromDate);
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'debit')
            ->sum('amount');

        $openingCredits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<', $fromDate);
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'credit')
            ->sum('amount');

        // For bank accounts (assets), opening balance = debits - credits
        $openingBalance = $openingDebits - $openingCredits;

        // Get transactions within date range
        $transactions = JournalItem::with('journalEntry')
            ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate) {
                $query->where('status', 'posted')
                      ->whereBetween('entry_date', [$fromDate, $toDate]);
            })
            ->where('account_id', $bankAccount->account_id)
            ->orderBy('journalEntry.entry_date')
            ->orderBy('journalEntry.id')
            ->get();

        $statementData = [];
        $runningBalance = $openingBalance;

        // Add opening balance entry
        $statementData[] = [
            'date' => $fromDate->format('Y-m-d'),
            'reference' => 'Opening Balance',
            'description' => 'Opening Balance',
            'debit' => 0,
            'credit' => 0,
            'balance' => $runningBalance,
            'formatted_debit' => number_format(0, 2),
            'formatted_credit' => number_format(0, 2),
            'formatted_balance' => number_format($runningBalance, 2),
        ];

        // Process transactions
        foreach ($transactions as $transaction) {
            $debit = $transaction->type === 'debit' ? $transaction->amount : 0;
            $credit = $transaction->type === 'credit' ? $transaction->amount : 0;

            // Update running balance
            if ($transaction->type === 'debit') {
                $runningBalance += $transaction->amount;
            } else {
                $runningBalance -= $transaction->amount;
            }

            $statementData[] = [
                'date' => $transaction->journalEntry->entry_date,
                'reference' => $transaction->journalEntry->reference_number,
                'description' => $transaction->journalEntry->narration,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
                'formatted_debit' => number_format($debit, 2),
                'formatted_credit' => number_format($credit, 2),
                'formatted_balance' => number_format($runningBalance, 2),
            ];
        }

        // Calculate totals
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $netMovement = $totalDebits - $totalCredits;
        $closingBalance = $openingBalance + $netMovement;

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/Statement', [
            'bankAccount' => $bankAccount,
            'statementData' => $statementData,
            'fromDate' => $fromDate->format('Y-m-d'),
            'toDate' => $toDate->format('Y-m-d'),
            'openingBalance' => $openingBalance,
            'totalDebits' => $totalDebits,
            'totalCredits' => $totalCredits,
            'netMovement' => $netMovement,
            'closingBalance' => $closingBalance,
            'formattedOpeningBalance' => number_format($openingBalance, 2),
            'formattedTotalDebits' => number_format($totalDebits, 2),
            'formattedTotalCredits' => number_format($totalCredits, 2),
            'formattedNetMovement' => number_format($netMovement, 2),
            'formattedClosingBalance' => number_format($closingBalance, 2),
            'currencySymbol' => $currencySymbol,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Record a bank transfer.
     */
    public function showTransferForm()
    {
        // Get active bank accounts
        $bankAccounts = BankAccount::with('account')
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get();

        // Calculate balances for each bank account
        foreach ($bankAccounts as $account) {
            if ($account->account) {
                $chartAccount = $account->account;

                // Calculate debits and credits for this account
                $debits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
                    })
                    ->where('account_id', $chartAccount->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
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

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();

        return Inertia::render('BankAccounts/Transfer', [
            'bankAccounts' => $bankAccounts,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Process a bank transfer.
     */
    public function processTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:bank_accounts,id',
            'to_account_id' => 'required|exists:bank_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $fromAccount = BankAccount::with('account')->findOrFail($request->from_account_id);
            $toAccount = BankAccount::with('account')->findOrFail($request->to_account_id);

            if (!$fromAccount->account || !$toAccount->account) {
                throw new \Exception('Both bank accounts must be associated with chart accounts.');
            }

            // Check if source account has sufficient balance
            $debits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $fromAccount->account_id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $fromAccount->account_id)
                ->where('type', 'credit')
                ->sum('amount');

            $currentBalance = $debits - $credits;

            if ($currentBalance < $request->amount) {
                return back()->with('error', 'Insufficient balance in source account.')->withInput();
            }

            // Get active financial year
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            // Get company settings for journal prefix
            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            // Create reference if not provided
            $reference = $request->reference ?? ('TRANSFER-' . date('YmdHis'));

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'TRANSFER-' . date('YmdHis'),
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->transfer_date,
                'narration' => $request->description ?? ('Bank transfer from ' . $fromAccount->account_name . ' to ' . $toAccount->account_name),
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Credit source account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $fromAccount->account_id,
                'type' => 'credit',
                'amount' => $request->amount,
                'description' => 'Transfer to ' . $toAccount->account_name,
            ]);

            // Debit destination account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $toAccount->account_id,
                'type' => 'debit',
                'amount' => $request->amount,
                'description' => 'Transfer from ' . $fromAccount->account_name,
            ]);

            DB::commit();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Bank transfer processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Export bank statement to PDF.
     */
    public function exportStatement(Request $request, BankAccount $bankAccount)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $fromDate = Carbon::parse($request->from_date);
        $toDate = Carbon::parse($request->to_date);

        $bankAccount->load('account');

        if (!$bankAccount->account) {
            return back()->with('error', 'Bank account is not associated with a chart account.');
        }

        // Get opening balance
        $openingDebits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<', $fromDate);
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'debit')
            ->sum('amount');

        $openingCredits = JournalItem::whereHas('journalEntry', function ($query) use ($fromDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<', $fromDate);
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'credit')
            ->sum('amount');

        // For bank accounts (assets), opening balance = debits - credits
        $openingBalance = $openingDebits - $openingCredits;

        // Get transactions within date range
        $transactions = JournalItem::with('journalEntry')
            ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate) {
                $query->where('status', 'posted')
                      ->whereBetween('entry_date', [$fromDate, $toDate]);
            })
            ->where('account_id', $bankAccount->account_id)
            ->orderBy('journalEntry.entry_date')
            ->orderBy('journalEntry.id')
            ->get();

        $statementData = [];
        $runningBalance = $openingBalance;

        // Add opening balance entry
        $statementData[] = [
            'date' => $fromDate->format('Y-m-d'),
            'reference' => 'Opening Balance',
            'description' => 'Opening Balance',
            'debit' => 0,
            'credit' => 0,
            'balance' => $runningBalance,
            'formatted_debit' => number_format(0, 2),
            'formatted_credit' => number_format(0, 2),
            'formatted_balance' => number_format($runningBalance, 2),
        ];

        // Process transactions
        foreach ($transactions as $transaction) {
            $debit = $transaction->type === 'debit' ? $transaction->amount : 0;
            $credit = $transaction->type === 'credit' ? $transaction->amount : 0;

            // Update running balance
            if ($transaction->type === 'debit') {
                $runningBalance += $transaction->amount;
            } else {
                $runningBalance -= $transaction->amount;
            }

            $statementData[] = [
                'date' => $transaction->journalEntry->entry_date,
                'reference' => $transaction->journalEntry->reference_number,
                'description' => $transaction->journalEntry->narration,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
                'formatted_debit' => number_format($debit, 2),
                'formatted_credit' => number_format($credit, 2),
                'formatted_balance' => number_format($runningBalance, 2),
            ];
        }

        // Calculate totals
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $netMovement = $totalDebits - $totalCredits;
        $closingBalance = $openingBalance + $netMovement;

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/ExportStatement', [
            'bankAccount' => $bankAccount,
            'statementData' => $statementData,
            'fromDate' => $fromDate->format('Y-m-d'),
            'toDate' => $toDate->format('Y-m-d'),
            'openingBalance' => $openingBalance,
            'totalDebits' => $totalDebits,
            'totalCredits' => $totalCredits,
            'netMovement' => $netMovement,
            'closingBalance' => $closingBalance,
            'formattedOpeningBalance' => number_format($openingBalance, 2),
            'formattedTotalDebits' => number_format($totalDebits, 2),
            'formattedTotalCredits' => number_format($totalCredits, 2),
            'formattedNetMovement' => number_format($netMovement, 2),
            'formattedClosingBalance' => number_format($closingBalance, 2),
            'currencySymbol' => $currencySymbol,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Make a deposit to a bank account.
     */
    public function showDepositForm()
    {
        // Get active bank accounts
        $bankAccounts = BankAccount::with('account')
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get();

        // Get income accounts for deposit (Revenue accounts)
        $revenueCategory = AccountCategory::where('type', 'Revenue')->first();

        $incomeAccounts = [];
        if ($revenueCategory) {
            $incomeAccounts = ChartOfAccount::where('category_id', $revenueCategory->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();

        return Inertia::render('BankAccounts/Deposit', [
            'bankAccounts' => $bankAccounts,
            'incomeAccounts' => $incomeAccounts,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Process a deposit to a bank account.
     */
    public function processDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'income_account_id' => 'required|exists:chart_of_accounts,id',
            'deposit_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $bankAccount = BankAccount::with('account')->findOrFail($request->bank_account_id);
            $incomeAccount = ChartOfAccount::findOrFail($request->income_account_id);

            if (!$bankAccount->account) {
                throw new \Exception('Bank account must be associated with a chart account.');
            }

            // Get active financial year
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            // Get company settings for journal prefix
            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            // Create reference if not provided
            $reference = $request->reference ?? ('DEPOSIT-' . date('YmdHis'));

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'DEPOSIT-' . date('YmdHis'),
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->deposit_date,
                'narration' => $request->description ?? ('Deposit to ' . $bankAccount->account_name),
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Debit bank account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $bankAccount->account_id,
                'type' => 'debit',
                'amount' => $request->amount,
                'description' => 'Deposit',
            ]);

            // Credit income account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $incomeAccount->id,
                'type' => 'credit',
                'amount' => $request->amount,
                'description' => 'Deposit to ' . $bankAccount->account_name,
            ]);

            DB::commit();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Deposit processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Make a withdrawal from a bank account.
     */
    public function showWithdrawalForm()
    {
        // Get active bank accounts
        $bankAccounts = BankAccount::with('account')
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get();

        // Calculate balances for each bank account
        foreach ($bankAccounts as $account) {
            if ($account->account) {
                $chartAccount = $account->account;

                // Calculate debits and credits for this account
                $debits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
                    })
                    ->where('account_id', $chartAccount->id)
                    ->where('type', 'debit')
                    ->sum('amount');

                $credits = JournalItem::whereHas('journalEntry', function ($query) {
                        $query->where('status', 'posted');
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

        // Get expense accounts for withdrawal
        $expenseCategory = AccountCategory::where('type', 'Expense')->first();

        $expenseAccounts = [];
        if ($expenseCategory) {
            $expenseAccounts = ChartOfAccount::where('category_id', $expenseCategory->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();

        return Inertia::render('BankAccounts/Withdrawal', [
            'bankAccounts' => $bankAccounts,
            'expenseAccounts' => $expenseAccounts,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Process a withdrawal from a bank account.
     */
    public function processWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_account_id' => 'required|exists:chart_of_accounts,id',
            'withdrawal_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $bankAccount = BankAccount::with('account')->findOrFail($request->bank_account_id);
            $expenseAccount = ChartOfAccount::findOrFail($request->expense_account_id);

            if (!$bankAccount->account) {
                throw new \Exception('Bank account must be associated with a chart account.');
            }

            // Check if bank account has sufficient balance
            $debits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $bankAccount->account_id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) {
                    $query->where('status', 'posted');
                })
                ->where('account_id', $bankAccount->account_id)
                ->where('type', 'credit')
                ->sum('amount');

            $currentBalance = $debits - $credits;

            if ($currentBalance < $request->amount) {
                return back()->with('error', 'Insufficient balance in bank account.')->withInput();
            }

            // Get active financial year
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            // Get company settings for journal prefix
            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            // Create reference if not provided
            $reference = $request->reference ?? ('WITHDRAWAL-' . date('YmdHis'));

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'WITHDRAWAL-' . date('YmdHis'),
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->withdrawal_date,
                'narration' => $request->description ?? ('Withdrawal from ' . $bankAccount->account_name),
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Debit expense account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'type' => 'debit',
                'amount' => $request->amount,
                'description' => 'Withdrawal from ' . $bankAccount->account_name,
            ]);

            // Credit bank account
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $bankAccount->account_id,
                'type' => 'credit',
                'amount' => $request->amount,
                'description' => 'Withdrawal',
            ]);

            DB::commit();

            return redirect()->route('bank-accounts.index')
                ->with('success', 'Withdrawal processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Generate a bank reconciliation report.
     */
    public function showReconciliationForm(BankAccount $bankAccount)
    {
        $bankAccount->load('account');

        if (!$bankAccount->account) {
            return back()->with('error', 'Bank account is not associated with a chart account.');
        }

        // Calculate current balance in the system
        $debits = JournalItem::whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'debit')
            ->sum('amount');

        $credits = JournalItem::whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->where('account_id', $bankAccount->account_id)
            ->where('type', 'credit')
            ->sum('amount');

        $systemBalance = $debits - $credits;

        // Get company settings for currency symbol
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return Inertia::render('BankAccounts/Reconciliation', [
            'bankAccount' => $bankAccount,
            'systemBalance' => $systemBalance,
            'formattedSystemBalance' => $currencySymbol . ' ' . number_format($systemBalance, 2),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Process bank reconciliation.
     */
    public function processReconciliation(Request $request, BankAccount $bankAccount)
    {
        $validator = Validator::make($request->all(), [
            'statement_balance' => 'required|numeric',
            'reconciliation_date' => 'required|date',
            'adjustment_amount' => 'required|numeric',
            'adjustment_description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $bankAccount->load('account');

            if (!$bankAccount->account) {
                throw new \Exception('Bank account is not associated with a chart account.');
            }

            // Adjustment amount should be the difference between statement balance and system balance
            $adjustmentAmount = $request->adjustment_amount;

            if ($adjustmentAmount == 0) {
                return back()->with('info', 'No adjustment needed. System balance matches statement balance.');
            }

            // Get active financial year
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            // Get company settings for journal prefix
            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            // Create journal entry for reconciliation adjustment
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'RECONCILIATION-' . date('YmdHis'),
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->reconciliation_date,
                'narration' => 'Bank reconciliation adjustment for ' . $bankAccount->account_name,
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Get or create reconciliation discrepancy account
            $expenseCategory = AccountCategory::where('type', 'Expense')->first();
            $reconciliationAccount = ChartOfAccount::where('name', 'like', '%Reconciliation Discrepancy%')
                ->where('category_id', $expenseCategory->id)
                ->first();

            if (!$reconciliationAccount) {
                // Create reconciliation discrepancy account if it doesn't exist
                $reconciliationAccount = ChartOfAccount::create([
                    'account_code' => '6999',
                    'name' => 'Reconciliation Discrepancy',
                    'category_id' => $expenseCategory->id,
                    'description' => 'Account for bank reconciliation discrepancies',
                    'is_active' => true,
                    'created_by' => Auth::id(),
                ]);
            }

            if ($adjustmentAmount > 0) {
                // System balance is lower than statement balance, need to increase system balance
                // Debit bank account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->account_id,
                    'type' => 'debit',
                    'amount' => abs($adjustmentAmount),
                    'description' => $request->adjustment_description,
                ]);

                // Credit reconciliation account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $reconciliationAccount->id,
                    'type' => 'credit',
                    'amount' => abs($adjustmentAmount),
                    'description' => 'Reconciliation adjustment for ' . $bankAccount->account_name,
                ]);
            } else {
                // System balance is higher than statement balance, need to decrease system balance
                // Credit bank account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $bankAccount->account_id,
                    'type' => 'credit',
                    'amount' => abs($adjustmentAmount),
                    'description' => $request->adjustment_description,
                ]);

                // Debit reconciliation account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $reconciliationAccount->id,
                    'type' => 'debit',
                    'amount' => abs($adjustmentAmount),
                    'description' => 'Reconciliation adjustment for ' . $bankAccount->account_name,
                ]);
            }

            DB::commit();

            return redirect()->route('bank-accounts.show', $bankAccount->id)
                ->with('success', 'Bank reconciliation processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }
}
