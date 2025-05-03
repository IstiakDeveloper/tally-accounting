<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\FinancialYear;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $status = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Query journal entries with filters
        $query = JournalEntry::with(['financialYear', 'createdBy', 'items.account']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('narration', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->where('entry_date', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->where('entry_date', '<=', Carbon::parse($endDate));
        }

        // Get paginated results
        $journalEntries = $query->orderBy('entry_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Accounting/JournalEntries/Index', [
            'journalEntries' => $journalEntries,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get active financial year
        $financialYear = FinancialYear::getActive();

        if (!$financialYear) {
            return redirect()->route('financial-years.index')
                ->with('error', 'অনুগ্রহ করে প্রথমে একটি সক্রিয় অর্থ বছর সেট করুন।');
        }

        // Get all accounts for dropdown
        $accounts = ChartOfAccount::where('is_active', true)
            ->with('category')
            ->orderBy('account_code')
            ->get();

        // Get company settings for reference number prefix
        $companySettings = \App\Models\CompanySetting::getDefault();
        $prefix = $companySettings->journal_prefix;

        // Generate a new reference number
        $lastEntry = JournalEntry::orderBy('id', 'desc')->first();
        $nextNumber = $lastEntry ? intval(substr($lastEntry->reference_number, strlen($prefix))) + 1 : 1;
        $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return Inertia::render('Accounting/JournalEntries/Create', [
            'financialYear' => $financialYear,
            'accounts' => $accounts,
            'referenceNumber' => $referenceNumber,
            'today' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|max:20|unique:journal_entries,reference_number',
            'financial_year_id' => 'required|exists:financial_years,id',
            'entry_date' => 'required|date',
            'narration' => 'required|string',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.type' => 'required|in:debit,credit',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
        ]);

        // Check if debit equals credit
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ($validated['items'] as $item) {
            if ($item['type'] === 'debit') {
                $debitTotal += $item['amount'];
            } else {
                $creditTotal += $item['amount'];
            }
        }

        if ($debitTotal != $creditTotal) {
            return back()->withErrors(['items' => 'ডেবিট এবং ক্রেডিটের পরিমাণ সমান হতে হবে।']);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $validated['reference_number'],
                'financial_year_id' => $validated['financial_year_id'],
                'entry_date' => $validated['entry_date'],
                'narration' => $validated['narration'],
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            // Create journal items
            foreach ($validated['items'] as $item) {
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $item['account_id'],
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($journalEntry)
                ->withProperties([
                    'reference_number' => $journalEntry->reference_number,
                    'entry_date' => $journalEntry->entry_date,
                    'amount' => $debitTotal,
                ])
                ->log('created');

            DB::commit();

            return redirect()->route('journal-entries.index')
                ->with('success', 'জার্নাল এন্ট্রি সফলভাবে তৈরি করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load(['financialYear', 'createdBy', 'items.account']);

        return Inertia::render('Accounting/JournalEntries/Show', [
            'journalEntry' => $journalEntry,
            'totalDebit' => $journalEntry->total_debit,
            'totalCredit' => $journalEntry->total_credit,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JournalEntry $journalEntry)
    {
        // Check if journal entry is editable
        if ($journalEntry->status !== 'draft') {
            return redirect()->route('journal-entries.show', $journalEntry)
                ->with('error', 'শুধুমাত্র ড্রাফট জার্নাল এন্ট্রি সম্পাদনা করা যাবে।');
        }

        $journalEntry->load(['financialYear', 'items.account']);

        // Get all accounts for dropdown
        $accounts = ChartOfAccount::where('is_active', true)
            ->with('category')
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Accounting/JournalEntries/Edit', [
            'journalEntry' => $journalEntry,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JournalEntry $journalEntry)
    {
        // Check if journal entry is editable
        if ($journalEntry->status !== 'draft') {
            return back()->with('error', 'শুধুমাত্র ড্রাফট জার্নাল এন্ট্রি সম্পাদনা করা যাবে।');
        }

        $validated = $request->validate([
            'reference_number' => 'required|string|max:20|unique:journal_entries,reference_number,' . $journalEntry->id,
            'entry_date' => 'required|date',
            'narration' => 'required|string',
            'items' => 'required|array|min:2',
            'items.*.id' => 'nullable|exists:journal_items,id',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.type' => 'required|in:debit,credit',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
        ]);

        // Check if debit equals credit
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ($validated['items'] as $item) {
            if ($item['type'] === 'debit') {
                $debitTotal += $item['amount'];
            } else {
                $creditTotal += $item['amount'];
            }
        }

        if ($debitTotal != $creditTotal) {
            return back()->withErrors(['items' => 'ডেবিট এবং ক্রেডিটের পরিমাণ সমান হতে হবে।']);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Store old values for audit log
            $oldValues = [
                'reference_number' => $journalEntry->reference_number,
                'entry_date' => $journalEntry->entry_date,
                'narration' => $journalEntry->narration,
            ];

            // Update journal entry
            $journalEntry->update([
                'reference_number' => $validated['reference_number'],
                'entry_date' => $validated['entry_date'],
                'narration' => $validated['narration'],
            ]);

            // Get existing item IDs
            $existingItemIds = $journalEntry->items->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create journal items
            foreach ($validated['items'] as $itemData) {
                if (isset($itemData['id']) && $itemData['id']) {
                    // Update existing item
                    $item = JournalItem::find($itemData['id']);
                    $item->update([
                        'account_id' => $itemData['account_id'],
                        'type' => $itemData['type'],
                        'amount' => $itemData['amount'],
                        'description' => $itemData['description'] ?? null,
                    ]);
                    $updatedItemIds[] = $item->id;
                } else {
                    // Create new item
                    $item = JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $itemData['account_id'],
                        'type' => $itemData['type'],
                        'amount' => $itemData['amount'],
                        'description' => $itemData['description'] ?? null,
                    ]);
                    $updatedItemIds[] = $item->id;
                }
            }

            // Delete items that were removed
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            JournalItem::whereIn('id', $itemsToDelete)->delete();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($journalEntry)
                ->withProperties([
                    'old' => $oldValues,
                    'new' => [
                        'reference_number' => $journalEntry->reference_number,
                        'entry_date' => $journalEntry->entry_date,
                        'narration' => $journalEntry->narration,
                    ],
                    'amount' => $debitTotal,
                ])
                ->log('updated');

            DB::commit();

            return redirect()->route('journal-entries.index')
                ->with('success', 'জার্নাল এন্ট্রি সফলভাবে আপডেট করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JournalEntry $journalEntry)
    {
        // Check if journal entry is deletable
        if ($journalEntry->status !== 'draft') {
            return back()->with('error', 'শুধুমাত্র ড্রাফট জার্নাল এন্ট্রি মুছে ফেলা যাবে।');
        }

        // Store journal entry details for audit log
        $journalDetails = [
            'id' => $journalEntry->id,
            'reference_number' => $journalEntry->reference_number,
            'entry_date' => $journalEntry->entry_date,
            'amount' => $journalEntry->total_debit,
        ];

        // Begin transaction
        DB::beginTransaction();

        try {
            // Delete journal items first
            $journalEntry->items()->delete();

            // Delete journal entry
            $journalEntry->delete();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->withProperties($journalDetails)
                ->log('deleted journal entry');

            DB::commit();

            return redirect()->route('journal-entries.index')
                ->with('success', 'জার্নাল এন্ট্রি সফলভাবে মুছে ফেলা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Post the journal entry.
     */
    public function post(JournalEntry $journalEntry)
    {
        // Check if journal entry is in draft status
        if ($journalEntry->status !== 'draft') {
            return back()->with('error', 'শুধুমাত্র ড্রাফট জার্নাল এন্ট্রি পোস্ট করা যাবে।');
        }

        // Check if journal entry is balanced
        if (!$journalEntry->isBalanced()) {
            return back()->with('error', 'অসমতুল্য জার্নাল এন্ট্রি পোস্ট করা যাবে না।');
        }

        // Post the journal entry
        $result = $journalEntry->post();

        if ($result) {
            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($journalEntry)
                ->withProperties([
                    'reference_number' => $journalEntry->reference_number,
                    'entry_date' => $journalEntry->entry_date,
                    'amount' => $journalEntry->total_debit,
                ])
                ->log('posted');

            return back()->with('success', 'জার্নাল এন্ট্রি সফলভাবে পোস্ট করা হয়েছে।');
        } else {
            return back()->with('error', 'জার্নাল এন্ট্রি পোস্ট করা যায়নি।');
        }
    }

    /**
     * Cancel the journal entry.
     */
    public function cancel(JournalEntry $journalEntry)
    {
        // Check if journal entry is already cancelled
        if ($journalEntry->status === 'cancelled') {
            return back()->with('error', 'এই জার্নাল এন্ট্রি ইতিমধ্যে বাতিল করা হয়েছে।');
        }

        // Cancel the journal entry
        $result = $journalEntry->cancel();

        if ($result) {
            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($journalEntry)
                ->withProperties([
                    'reference_number' => $journalEntry->reference_number,
                    'entry_date' => $journalEntry->entry_date,
                    'previous_status' => $journalEntry->getOriginal('status'),
                ])
                ->log('cancelled');

            return back()->with('success', 'জার্নাল এন্ট্রি সফলভাবে বাতিল করা হয়েছে।');
        } else {
            return back()->with('error', 'জার্নাল এন্ট্রি বাতিল করা যায়নি।');
        }
    }
}
