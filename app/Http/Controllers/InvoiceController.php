<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TaxSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the invoices.
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['contact', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by contact
        if ($request->has('contact_id') && $request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        // Filter overdue invoices
        if ($request->has('overdue') && $request->overdue) {
            $query->overdue();
        }

        // Search by reference number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->paginate(10)->withQueryString();

        $contacts = Contact::where('is_active', true)->get();
        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'contacts' => $contacts,
            'filters' => $request->only(['type', 'status', 'contact_id', 'from_date', 'to_date', 'overdue', 'search']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(Request $request)
    {
        $companySetting = CompanySetting::first();
        $invoicePrefix = $companySetting ? $companySetting->invoice_prefix : 'INV-';

        // Generate reference number if not provided
        $referenceNumber = $request->reference_number;
        if (!$referenceNumber) {
            $lastInvoice = Invoice::latest()->first();
            $referenceNumber = $invoicePrefix . '00001';

            if ($lastInvoice) {
                $lastReferenceNumber = $lastInvoice->reference_number;
                $lastNumber = (int) substr($lastReferenceNumber, strlen($invoicePrefix));
                $referenceNumber = $invoicePrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            }
        }

        // Check if creating invoice from sales order
        $salesOrder = null;
        if ($request->has('sales_order_id') && $request->sales_order_id) {
            $salesOrder = SalesOrder::with(['customer', 'items.product'])->findOrFail($request->sales_order_id);
        }

        // Check if creating invoice from purchase order
        $purchaseOrder = null;
        if ($request->has('purchase_order_id') && $request->purchase_order_id) {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product'])->findOrFail($request->purchase_order_id);
        }

        $contacts = Contact::where('is_active', true)->get();
        $taxSettings = TaxSetting::where('is_active', true)->get();

        // Get relevant chart accounts for accounting entries
        $accountsReceivable = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })->where('name', 'like', '%Accounts Receivable%')->first();

        $accountsPayable = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Liability');
        })->where('name', 'like', '%Accounts Payable%')->first();

        $salesRevenue = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Revenue');
        })->where('name', 'like', '%Sales Revenue%')->first();

        $inventory = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })->where('name', 'like', '%Inventory%')->first();

        $costOfGoodsSold = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Expense');
        })->where('name', 'like', '%Cost of Goods Sold%')->first();

        return Inertia::render('Invoices/Create', [
            'referenceNumber' => $referenceNumber,
            'salesOrder' => $salesOrder,
            'purchaseOrder' => $purchaseOrder,
            'contacts' => $contacts,
            'taxSettings' => $taxSettings,
            'companySetting' => $companySetting,
            'accounts' => [
                'accountsReceivable' => $accountsReceivable,
                'accountsPayable' => $accountsPayable,
                'salesRevenue' => $salesRevenue,
                'inventory' => $inventory,
                'costOfGoodsSold' => $costOfGoodsSold,
            ],
        ]);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string|unique:invoices',
            'type' => 'required|in:sales,purchase',
            'contact_id' => 'required|exists:contacts,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'sub_total' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'status' => 'required|in:unpaid,partially_paid,paid,cancelled',
            'remarks' => 'nullable|string',
            'accounts' => 'required|array',
            'accounts.receivable_id' => 'required_if:type,sales|exists:chart_of_accounts,id',
            'accounts.payable_id' => 'required_if:type,purchase|exists:chart_of_accounts,id',
            'accounts.revenue_id' => 'required_if:type,sales|exists:chart_of_accounts,id',
            'accounts.inventory_id' => 'required_if:type,purchase|exists:chart_of_accounts,id',
            'accounts.cogs_id' => 'required_if:type,sales|exists:chart_of_accounts,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Create journal entry for accounting
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();
            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            $lastJournal = JournalEntry::latest()->first();
            $journalReferenceNumber = $journalPrefix . '00001';

            if ($lastJournal) {
                $lastReferenceNumber = $lastJournal->reference_number;
                $lastNumber = (int) substr($lastReferenceNumber, strlen($journalPrefix));
                $journalReferenceNumber = $journalPrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'reference_number' => $journalReferenceNumber,
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->invoice_date,
                'narration' => $request->type === 'sales'
                    ? 'Sales Invoice: ' . $request->reference_number
                    : 'Purchase Invoice: ' . $request->reference_number,
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Create journal items based on invoice type
            if ($request->type === 'sales') {
                // Debit Accounts Receivable
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->accounts['receivable_id'],
                    'type' => 'debit',
                    'amount' => $request->total,
                    'description' => 'Invoice: ' . $request->reference_number,
                ]);

                // Credit Sales Revenue
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->accounts['revenue_id'],
                    'type' => 'credit',
                    'amount' => $request->sub_total,
                    'description' => 'Invoice: ' . $request->reference_number,
                ]);

                // If there's tax, credit Tax Payable
                if ($request->tax_amount > 0 && isset($request->accounts['tax_id'])) {
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->accounts['tax_id'],
                        'type' => 'credit',
                        'amount' => $request->tax_amount,
                        'description' => 'Tax on Invoice: ' . $request->reference_number,
                    ]);
                }

                // If there's COGS, process inventory reduction
                if ($request->sales_order_id && isset($request->accounts['cogs_id']) && isset($request->accounts['inventory_id'])) {
                    $cogsTotal = 0;

                    // Calculate COGS based on average cost of products
                    foreach ($request->items as $item) {
                        $product = \App\Models\Product::find($item['product_id']);
                        if ($product) {
                            $cogsTotal += $product->purchase_price * $item['quantity'];
                        }
                    }

                    if ($cogsTotal > 0) {
                        // Debit COGS
                        JournalItem::create([
                            'journal_entry_id' => $journalEntry->id,
                            'account_id' => $request->accounts['cogs_id'],
                            'type' => 'debit',
                            'amount' => $cogsTotal,
                            'description' => 'COGS for Invoice: ' . $request->reference_number,
                        ]);

                        // Credit Inventory
                        JournalItem::create([
                            'journal_entry_id' => $journalEntry->id,
                            'account_id' => $request->accounts['inventory_id'],
                            'type' => 'credit',
                            'amount' => $cogsTotal,
                            'description' => 'Inventory reduction for Invoice: ' . $request->reference_number,
                        ]);
                    }
                }
            } else { // Purchase invoice
                // Debit Inventory or Expense
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->accounts['inventory_id'],
                    'type' => 'debit',
                    'amount' => $request->sub_total,
                    'description' => 'Invoice: ' . $request->reference_number,
                ]);

                // If there's tax, debit Tax Receivable
                if ($request->tax_amount > 0 && isset($request->accounts['tax_id'])) {
                    JournalItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $request->accounts['tax_id'],
                        'type' => 'debit',
                        'amount' => $request->tax_amount,
                        'description' => 'Tax on Invoice: ' . $request->reference_number,
                    ]);
                }

                // Credit Accounts Payable
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->accounts['payable_id'],
                    'type' => 'credit',
                    'amount' => $request->total,
                    'description' => 'Invoice: ' . $request->reference_number,
                ]);
            }

            // Create invoice
            $invoice = Invoice::create([
                'reference_number' => $request->reference_number,
                'type' => $request->type,
                'contact_id' => $request->contact_id,
                'sales_order_id' => $request->sales_order_id,
                'purchase_order_id' => $request->purchase_order_id,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'sub_total' => $request->sub_total,
                'discount' => $request->discount ?? 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'total' => $request->total,
                'amount_paid' => $request->amount_paid ?? 0,
                'status' => $request->status,
                'journal_entry_id' => $journalEntry->id,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            // Update sales order or purchase order if needed
            if ($request->sales_order_id) {
                SalesOrder::where('id', $request->sales_order_id)
                    ->update(['status' => 'delivered']);
            }

            if ($request->purchase_order_id) {
                PurchaseOrder::where('id', $request->purchase_order_id)
                    ->update(['status' => 'received']);
            }

            // If amount_paid > 0, create payment record
            if ($request->amount_paid > 0) {
                $paymentPrefix = $companySetting ? $companySetting->payment_prefix : 'PAY-';

                $lastPayment = Payment::latest()->first();
                $paymentReferenceNumber = $paymentPrefix . '00001';

                if ($lastPayment) {
                    $lastReferenceNumber = $lastPayment->reference_number;
                    $lastNumber = (int) substr($lastReferenceNumber, strlen($paymentPrefix));
                    $paymentReferenceNumber = $paymentPrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
                }

                // Create payment journal entry
                $paymentJournalEntry = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'PAY-' . $invoice->id,
                    'financial_year_id' => $financialYear->id,
                    'entry_date' => $request->invoice_date,
                    'narration' => $request->type === 'sales'
                        ? 'Payment received for invoice: ' . $request->reference_number
                        : 'Payment made for invoice: ' . $request->reference_number,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Default to cash account if not specified
                $cashAccountId = $request->accounts['cash_id'] ??
                    ChartOfAccount::whereHas('category', function ($query) {
                        $query->where('type', 'Asset');
                    })->where('name', 'like', '%Cash%')->first()->id;

                // Create journal items for payment
                if ($request->type === 'sales') {
                    // Debit Cash/Bank
                    JournalItem::create([
                        'journal_entry_id' => $paymentJournalEntry->id,
                        'account_id' => $cashAccountId,
                        'type' => 'debit',
                        'amount' => $request->amount_paid,
                        'description' => 'Payment for Invoice: ' . $request->reference_number,
                    ]);

                    // Credit Accounts Receivable
                    JournalItem::create([
                        'journal_entry_id' => $paymentJournalEntry->id,
                        'account_id' => $request->accounts['receivable_id'],
                        'type' => 'credit',
                        'amount' => $request->amount_paid,
                        'description' => 'Payment for Invoice: ' . $request->reference_number,
                    ]);
                } else { // Purchase invoice payment
                    // Debit Accounts Payable
                    JournalItem::create([
                        'journal_entry_id' => $paymentJournalEntry->id,
                        'account_id' => $request->accounts['payable_id'],
                        'type' => 'debit',
                        'amount' => $request->amount_paid,
                        'description' => 'Payment for Invoice: ' . $request->reference_number,
                    ]);

                    // Credit Cash/Bank
                    JournalItem::create([
                        'journal_entry_id' => $paymentJournalEntry->id,
                        'account_id' => $cashAccountId,
                        'type' => 'credit',
                        'amount' => $request->amount_paid,
                        'description' => 'Payment for Invoice: ' . $request->reference_number,
                    ]);
                }

                // Create payment record
                Payment::create([
                    'reference_number' => $paymentReferenceNumber,
                    'invoice_id' => $invoice->id,
                    'payment_date' => $request->invoice_date,
                    'amount' => $request->amount_paid,
                    'payment_method' => $request->payment_method ?? 'cash',
                    'transaction_id' => $request->transaction_id,
                    'account_id' => $cashAccountId,
                    'journal_entry_id' => $paymentJournalEntry->id,
                    'remarks' => 'Initial payment for invoice: ' . $request->reference_number,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'contact',
            'salesOrder.items.product',
            'purchaseOrder.items.product',
            'journalEntry.items.account',
            'payments',
            'createdBy'
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(Invoice $invoice)
    {
        // Check if invoice can be edited (only unpaid invoices should be editable)
        if (!$invoice->isUnpaid()) {
            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'Only unpaid invoices can be edited.');
        }

        $invoice->load([
            'contact',
            'salesOrder.items.product',
            'purchaseOrder.items.product',
            'journalEntry.items.account'
        ]);

        $contacts = Contact::where('is_active', true)->get();
        $taxSettings = TaxSetting::where('is_active', true)->get();
        $companySetting = CompanySetting::first();

        // Get chart accounts
        $chartAccounts = ChartOfAccount::with('category')
            ->where('is_active', true)
            ->get()
            ->groupBy('category.type');

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'contacts' => $contacts,
            'taxSettings' => $taxSettings,
            'chartAccounts' => $chartAccounts,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        // Check if invoice can be updated
        if (!$invoice->isUnpaid()) {
            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'Only unpaid invoices can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $invoice->update([
                'due_date' => $request->due_date,
                'remarks' => $request->remarks,
            ]);

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Cancel the specified invoice.
     */
    public function cancel(Invoice $invoice)
    {
        // Check if invoice can be cancelled
        if ($invoice->isCancelled() || $invoice->isFullyPaid()) {
            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'This invoice cannot be cancelled.');
        }

        try {
            DB::beginTransaction();

            // Create reversal journal entry
            $journalEntry = $invoice->journalEntry;
            if ($journalEntry) {
                $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

                $companySetting = CompanySetting::first();
                $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

                $reversalJournal = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'REV-' . $invoice->id,
                    'financial_year_id' => $financialYear->id,
                    'entry_date' => now(),
                    'narration' => 'Reversal of ' . $journalEntry->narration,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                ]);

                // Create reversal journal items (debit becomes credit and vice versa)
                foreach ($journalEntry->items as $item) {
                    JournalItem::create([
                        'journal_entry_id' => $reversalJournal->id,
                        'account_id' => $item->account_id,
                        'type' => $item->type === 'debit' ? 'credit' : 'debit',
                        'amount' => $item->amount,
                        'description' => 'Reversal: ' . $item->description,
                    ]);
                }
            }

            // Update invoice status
            $invoice->update([
                'status' => 'cancelled',
            ]);

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for recording a payment.
     */
    public function showPaymentForm(Invoice $invoice)
    {
        // Check if invoice can receive payment
        if ($invoice->isCancelled() || $invoice->isFullyPaid()) {
            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'This invoice cannot receive payment.');
        }

        $invoice->load('contact');

        // Get bank accounts and cash accounts
        $bankAccounts = \App\Models\BankAccount::with('account')
            ->where('is_active', true)
            ->get();

        $cashAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })
            ->where(function ($query) {
                $query->where('name', 'like', '%Cash%')
                    ->orWhere('name', 'like', '%Bank%');
            })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();
        $paymentPrefix = $companySetting ? $companySetting->payment_prefix : 'PAY-';

        $lastPayment = Payment::latest()->first();
        $referenceNumber = $paymentPrefix . '00001';

        if ($lastPayment) {
            $lastReferenceNumber = $lastPayment->reference_number;
            $lastNumber = (int) substr($lastReferenceNumber, strlen($paymentPrefix));
            $referenceNumber = $paymentPrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        }

        return Inertia::render('Invoices/RecordPayment', [
            'invoice' => $invoice,
            'bankAccounts' => $bankAccounts,
            'cashAccounts' => $cashAccounts,
            'referenceNumber' => $referenceNumber,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Record a payment for the invoice.
     */
    public function recordPayment(Request $request, Invoice $invoice)
    {
        // Check if invoice can receive payment
        if ($invoice->isCancelled() || $invoice->isFullyPaid()) {
            return redirect()->route('invoices.show', $invoice->id)
                ->with('error', 'This invoice cannot receive payment.');
        }

        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string|unique:payments',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:' . $invoice->remaining_amount,
            'payment_method' => 'required|in:cash,bank,mobile_banking',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Create journal entry for payment
            $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();
            if (!$financialYear) {
                throw new \Exception('No active financial year found.');
            }

            $companySetting = CompanySetting::first();
            $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

            $journalEntry = JournalEntry::create([
                'reference_number' => $journalPrefix . 'PAY-' . $invoice->id . '-' . time(),
                'financial_year_id' => $financialYear->id,
                'entry_date' => $request->payment_date,
                'narration' => $invoice->type === 'sales'
                    ? 'Payment received for invoice: ' . $invoice->reference_number
                    : 'Payment made for invoice: ' . $invoice->reference_number,
                'status' => 'posted',
                'created_by' => Auth::id(),
            ]);

            // Create journal items based on invoice type
            if ($invoice->type === 'sales') {
                // Find accounts receivable account
                $accountsReceivable = ChartOfAccount::whereHas('category', function ($query) {
                    $query->where('type', 'Asset');
                })->where('name', 'like', '%Accounts Receivable%')->first();

                if (!$accountsReceivable) {
                    throw new \Exception('Accounts Receivable account not found.');
                }

                // Debit Cash/Bank
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->account_id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                    'description' => 'Payment for Invoice: ' . $invoice->reference_number,
                ]);

                // Credit Accounts Receivable
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $accountsReceivable->id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                    'description' => 'Payment for Invoice: ' . $invoice->reference_number,
                ]);
            } else { // Purchase invoice payment
                // Find accounts payable account
                $accountsPayable = ChartOfAccount::whereHas('category', function ($query) {
                    $query->where('type', 'Liability');
                })->where('name', 'like', '%Accounts Payable%')->first();

                if (!$accountsPayable) {
                    throw new \Exception('Accounts Payable account not found.');
                }

                // Debit Accounts Payable
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $accountsPayable->id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                    'description' => 'Payment for Invoice: ' . $invoice->reference_number,
                ]);

                // Credit Cash/Bank
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $request->account_id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                    'description' => 'Payment for Invoice: ' . $invoice->reference_number,
                ]);
            }

            // Create payment record
            $payment = Payment::create([
                'reference_number' => $request->reference_number,
                'invoice_id' => $invoice->id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'transaction_id' => $request->transaction_id,
                'account_id' => $request->account_id,
                'journal_entry_id' => $journalEntry->id,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            // Update invoice amount_paid and status
            $newAmountPaid = $invoice->amount_paid + $request->amount;
            $newStatus = 'unpaid';

            if ($newAmountPaid >= $invoice->total) {
                $newStatus = 'paid';
            } elseif ($newAmountPaid > 0) {
                $newStatus = 'partially_paid';
            }

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }
    /**
     * Export invoices to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = Invoice::with(['contact', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('contact_id') && $request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        $invoices = $query->get();
        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/ExportPdf', [
            'invoices' => $invoices,
            'companySetting' => $companySetting,
            'filters' => $request->only(['type', 'status', 'contact_id', 'from_date', 'to_date']),
        ]);
    }

    /**
     * Generate accounts receivable aging report.
     */
    public function agingReport(Request $request)
    {
        $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : Carbon::today();

        $query = Invoice::with('contact')
            ->where('type', 'sales')
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->where('invoice_date', '<=', $asOfDate)
            ->orderBy('invoice_date', 'asc');

        if ($request->has('contact_id') && $request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }

        $invoices = $query->get();

        // Group invoices by aging buckets
        $agingBuckets = [
            'current' => 0, // Not yet due
            '1_30' => 0,    // 1-30 days
            '31_60' => 0,   // 31-60 days
            '61_90' => 0,   // 61-90 days
            'over_90' => 0  // Over 90 days
        ];

        $agingDetails = [];

        foreach ($invoices as $invoice) {
            $daysOverdue = 0;

            if ($invoice->due_date < $asOfDate) {
                $daysOverdue = $invoice->due_date->diffInDays($asOfDate);
            }

            $remainingAmount = $invoice->total - $invoice->amount_paid;

            // Determine aging bucket
            $bucket = 'current';
            if ($daysOverdue > 0) {
                if ($daysOverdue <= 30) {
                    $bucket = '1_30';
                } elseif ($daysOverdue <= 60) {
                    $bucket = '31_60';
                } elseif ($daysOverdue <= 90) {
                    $bucket = '61_90';
                } else {
                    $bucket = 'over_90';
                }
            }

            // Add to total bucket
            $agingBuckets[$bucket] += $remainingAmount;

            // Add to customer details
            $customerId = $invoice->contact_id;
            $customerName = $invoice->contact->name;

            if (!isset($agingDetails[$customerId])) {
                $agingDetails[$customerId] = [
                    'customer_name' => $customerName,
                    'current' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                    'invoices' => []
                ];
            }

            $agingDetails[$customerId][$bucket] += $remainingAmount;
            $agingDetails[$customerId]['total'] += $remainingAmount;

            $agingDetails[$customerId]['invoices'][] = [
                'id' => $invoice->id,
                'reference_number' => $invoice->reference_number,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'days_overdue' => $daysOverdue,
                'amount' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $remainingAmount,
                'bucket' => $bucket
            ];
        }

        // Convert to indexed array and sort by total amount
        $agingDetails = collect($agingDetails)->sortByDesc('total')->values()->all();

        $customers = Contact::where(function ($query) {
            $query->where('type', 'customer')
                ->orWhere('type', 'both');
        })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/AgingReport', [
            'agingBuckets' => $agingBuckets,
            'agingDetails' => $agingDetails,
            'asOfDate' => $asOfDate->format('Y-m-d'),
            'customers' => $customers,
            'filters' => $request->only(['contact_id', 'as_of_date']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate accounts payable aging report.
     */
    public function payablesReport(Request $request)
    {
        $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : Carbon::today();

        $query = Invoice::with('contact')
            ->where('type', 'purchase')
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->where('invoice_date', '<=', $asOfDate)
            ->orderBy('invoice_date', 'asc');

        if ($request->has('contact_id') && $request->contact_id) {
            $query->where('contact_id', $request->contact_id);
        }

        $invoices = $query->get();

        // Group invoices by aging buckets
        $agingBuckets = [
            'current' => 0, // Not yet due
            '1_30' => 0,    // 1-30 days
            '31_60' => 0,   // 31-60 days
            '61_90' => 0,   // 61-90 days
            'over_90' => 0  // Over 90 days
        ];

        $agingDetails = [];

        foreach ($invoices as $invoice) {
            $daysOverdue = 0;

            if ($invoice->due_date < $asOfDate) {
                $daysOverdue = $invoice->due_date->diffInDays($asOfDate);
            }

            $remainingAmount = $invoice->total - $invoice->amount_paid;

            // Determine aging bucket
            $bucket = 'current';
            if ($daysOverdue > 0) {
                if ($daysOverdue <= 30) {
                    $bucket = '1_30';
                } elseif ($daysOverdue <= 60) {
                    $bucket = '31_60';
                } elseif ($daysOverdue <= 90) {
                    $bucket = '61_90';
                } else {
                    $bucket = 'over_90';
                }
            }

            // Add to total bucket
            $agingBuckets[$bucket] += $remainingAmount;

            // Add to supplier details
            $supplierId = $invoice->contact_id;
            $supplierName = $invoice->contact->name;

            if (!isset($agingDetails[$supplierId])) {
                $agingDetails[$supplierId] = [
                    'supplier_name' => $supplierName,
                    'current' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                    'invoices' => []
                ];
            }

            $agingDetails[$supplierId][$bucket] += $remainingAmount;
            $agingDetails[$supplierId]['total'] += $remainingAmount;

            $agingDetails[$supplierId]['invoices'][] = [
                'id' => $invoice->id,
                'reference_number' => $invoice->reference_number,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'days_overdue' => $daysOverdue,
                'amount' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $remainingAmount,
                'bucket' => $bucket
            ];
        }

        // Convert to indexed array and sort by total amount
        $agingDetails = collect($agingDetails)->sortByDesc('total')->values()->all();

        $suppliers = Contact::where(function ($query) {
            $query->where('type', 'supplier')
                ->orWhere('type', 'both');
        })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/PayablesReport', [
            'agingBuckets' => $agingBuckets,
            'agingDetails' => $agingDetails,
            'asOfDate' => $asOfDate->format('Y-m-d'),
            'suppliers' => $suppliers,
            'filters' => $request->only(['contact_id', 'as_of_date']),
            'companySetting' => $companySetting,
        ]);
    }


    /**
     * Generate cash flow projection report.
     */
    public function cashFlowProjection(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::today()->addDays(90);

        // Get all unpaid/partially paid invoices
        $receivables = Invoice::with('contact')
            ->where('type', 'sales')
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->where('due_date', '>=', $startDate)
            ->where('due_date', '<=', $endDate)
            ->orderBy('due_date', 'asc')
            ->get();

        $payables = Invoice::with('contact')
            ->where('type', 'purchase')
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->where('due_date', '>=', $startDate)
            ->where('due_date', '<=', $endDate)
            ->orderBy('due_date', 'asc')
            ->get();

        // Initialize projection data
        $projection = [];
        $runningBalance = 0;

        // Get initial balance
        $cashAccounts = ChartOfAccount::whereHas('category', function ($query) {
            $query->where('type', 'Asset');
        })
            ->where(function ($query) {
                $query->where('name', 'like', '%Cash%')
                    ->orWhere('name', 'like', '%Bank%');
            })
            ->where('is_active', true)
            ->get();

        $initialBalance = 0;
        foreach ($cashAccounts as $account) {
            // Calculate account balance based on journal entries
            $debits = JournalItem::whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
                ->where('account_id', $account->id)
                ->where('type', 'debit')
                ->sum('amount');

            $credits = JournalItem::whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
                ->where('account_id', $account->id)
                ->where('type', 'credit')
                ->sum('amount');

            $initialBalance += $debits - $credits;
        }

        $runningBalance = $initialBalance;

        // Generate projection by week
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $weekStart = $currentDate->copy()->startOfWeek();
            $weekEnd = $currentDate->copy()->endOfWeek();

            if ($weekEnd > $endDate) {
                $weekEnd = $endDate;
            }

            $weekReceivables = $receivables->filter(function ($invoice) use ($weekStart, $weekEnd) {
                return $invoice->due_date >= $weekStart && $invoice->due_date <= $weekEnd;
            });

            $weekPayables = $payables->filter(function ($invoice) use ($weekStart, $weekEnd) {
                return $invoice->due_date >= $weekStart && $invoice->due_date <= $weekEnd;
            });

            $receivablesTotal = $weekReceivables->sum(function ($invoice) {
                return $invoice->total - $invoice->amount_paid;
            });

            $payablesTotal = $weekPayables->sum(function ($invoice) {
                return $invoice->total - $invoice->amount_paid;
            });

            $netCashFlow = $receivablesTotal - $payablesTotal;
            $runningBalance += $netCashFlow;

            $projection[] = [
                'period' => 'Week of ' . $weekStart->format('M d, Y'),
                'start_date' => $weekStart->format('Y-m-d'),
                'end_date' => $weekEnd->format('Y-m-d'),
                'receivables' => $receivablesTotal,
                'payables' => $payablesTotal,
                'net_cash_flow' => $netCashFlow,
                'balance' => $runningBalance,
                'receivables_detail' => $weekReceivables->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'reference_number' => $invoice->reference_number,
                        'customer' => $invoice->contact->name,
                        'due_date' => $invoice->due_date->format('Y-m-d'),
                        'amount' => $invoice->total - $invoice->amount_paid
                    ];
                })->values()->all(),
                'payables_detail' => $weekPayables->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'reference_number' => $invoice->reference_number,
                        'supplier' => $invoice->contact->name,
                        'due_date' => $invoice->due_date->format('Y-m-d'),
                        'amount' => $invoice->total - $invoice->amount_paid
                    ];
                })->values()->all()
            ];

            $currentDate->addWeek();
        }

        $companySetting = CompanySetting::first();

        return Inertia::render('Invoices/CashFlowProjection', [
            'projection' => $projection,
            'initialBalance' => $initialBalance,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'filters' => $request->only(['start_date', 'end_date']),
            'companySetting' => $companySetting,
        ]);
    }
}
