<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PaymentController extends Controller
{
    /**
     * Display a listing of the payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['invoice.contact', 'account', 'createdBy'])
            ->orderBy('payment_date', 'desc');

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by account
        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        // Filter by invoice type (sales or purchase)
        if ($request->has('invoice_type') && $request->invoice_type !== 'all') {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('type', $request->invoice_type);
            });
        }

        // Search by reference number or transaction id
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('invoice', function ($query) use ($search) {
                      $query->where('reference_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('invoice.contact', function ($query) use ($search) {
                      $query->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->paginate(10)->withQueryString();

        $accounts = ChartOfAccount::whereHas('category', function ($query) {
                $query->where('type', 'Asset');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Cash%')
                      ->orWhere('name', 'like', '%Bank%');
            })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'accounts' => $accounts,
            'filters' => $request->only([
                'payment_method',
                'account_id',
                'from_date',
                'to_date',
                'invoice_type',
                'search'
            ]),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for creating a new payment.
     */
    public function create(Request $request)
    {
        $invoice = null;
        if ($request->has('invoice_id') && $request->invoice_id) {
            $invoice = Invoice::with('contact')->findOrFail($request->invoice_id);

            // Check if invoice can receive payment
            if ($invoice->isCancelled() || $invoice->isFullyPaid()) {
                return redirect()->route('invoices.show', $invoice->id)
                    ->with('error', 'This invoice cannot receive payment.');
            }
        }

        // Get bank accounts and cash accounts
        $bankAccounts = BankAccount::with('account')
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

        // Get unpaid invoices for selection if no invoice_id is provided
        $unpaidInvoices = [];
        if (!$invoice) {
            $unpaidInvoices = Invoice::with('contact')
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->orderBy('due_date', 'asc')
                ->get();
        }

        return Inertia::render('Payments/Create', [
            'invoice' => $invoice,
            'unpaidInvoices' => $unpaidInvoices,
            'bankAccounts' => $bankAccounts,
            'cashAccounts' => $cashAccounts,
            'referenceNumber' => $referenceNumber,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string|unique:payments',
            'invoice_id' => 'required|exists:invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank,mobile_banking',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Check if invoice can receive payment
        if ($invoice->isCancelled() || $invoice->isFullyPaid()) {
            return back()->with('error', 'This invoice cannot receive payment.');
        }

        // Check if payment amount is valid
        if ($request->amount > $invoice->remaining_amount) {
            return back()->withErrors([
                'amount' => 'Payment amount cannot exceed the remaining amount of ' . $invoice->formatted_remaining_amount,
            ])->withInput();
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

            return redirect()->route('payments.show', $payment->id)
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment)
    {
        $payment->load([
            'invoice.contact',
            'account',
            'journalEntry.items.account',
            'createdBy'
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/Show', [
            'payment' => $payment,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment)
    {
        // Check if payment can be deleted
        // Typically, payments should only be reversed, not deleted
        // But we'll implement delete for administrative purposes

        try {
            DB::beginTransaction();

            $invoice = $payment->invoice;
            $journalEntry = $payment->journalEntry;

            // Create reversal journal entry
            if ($journalEntry) {
                $financialYear = \App\Models\FinancialYear::where('is_active', true)->first();

                $companySetting = CompanySetting::first();
                $journalPrefix = $companySetting ? $companySetting->journal_prefix : 'JE-';

                $reversalJournal = JournalEntry::create([
                    'reference_number' => $journalPrefix . 'REV-' . $payment->id,
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

            // Update invoice amount_paid and status
            if ($invoice) {
                $newAmountPaid = $invoice->amount_paid - $payment->amount;
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
            }

            // Delete payment
            $payment->delete();

            DB::commit();

            return redirect()->route('payments.index')
                ->with('success', 'Payment deleted successfully and all related transactions reversed.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Print the payment receipt.
     */
    public function print(Payment $payment)
    {
        $payment->load([
            'invoice.contact',
            'account',
            'createdBy'
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/Print', [
            'payment' => $payment,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate payment receipt PDF.
     */
    public function generateReceipt(Payment $payment)
    {
        $payment->load([
            'invoice.contact',
            'account',
            'createdBy'
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/Receipt', [
            'payment' => $payment,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Export payments to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = Payment::with(['invoice.contact', 'account', 'createdBy'])
            ->orderBy('payment_date', 'desc');

        // Apply filters
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        if ($request->has('invoice_type') && $request->invoice_type !== 'all') {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('type', $request->invoice_type);
            });
        }

        $payments = $query->get();
        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/ExportPdf', [
            'payments' => $payments,
            'companySetting' => $companySetting,
            'filters' => $request->only([
                'payment_method',
                'account_id',
                'from_date',
                'to_date',
                'invoice_type'
            ]),
        ]);
    }

    /**
     * Generate cash flow report.
     */
    public function cashFlowReport(Request $request)
    {
        $startDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::today()->subMonths(1);
        $endDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::today();

        $query = Payment::with(['invoice.contact', 'account'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'asc');

        // Filter by account
        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        $payments = $query->get();

        // Group payments by day
        $cashFlowByDay = [];

        foreach ($payments as $payment) {
            $date = $payment->payment_date->format('Y-m-d');
            $invoiceType = $payment->invoice->type ?? '';

            if (!isset($cashFlowByDay[$date])) {
                $cashFlowByDay[$date] = [
                    'date' => $date,
                    'sales_receipts' => 0,
                    'purchase_payments' => 0,
                    'net_flow' => 0,
                    'details' => [],
                ];
            }

            if ($invoiceType === 'sales') {
                $cashFlowByDay[$date]['sales_receipts'] += $payment->amount;
                $cashFlowByDay[$date]['net_flow'] += $payment->amount;
            } elseif ($invoiceType === 'purchase') {
                $cashFlowByDay[$date]['purchase_payments'] += $payment->amount;
                $cashFlowByDay[$date]['net_flow'] -= $payment->amount;
            }

            $cashFlowByDay[$date]['details'][] = [
                'id' => $payment->id,
                'reference_number' => $payment->reference_number,
                'invoice_reference' => $payment->invoice->reference_number ?? '',
                'contact_name' => $payment->invoice->contact->name ?? 'Unknown',
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method_display,
                'type' => $invoiceType,
            ];
        }

        // Sort by date
        ksort($cashFlowByDay);

        // Calculate cumulative cash flow
        $runningBalance = 0;
        $cashFlowReport = [];

        foreach ($cashFlowByDay as $date => $dayData) {
            $runningBalance += $dayData['net_flow'];
            $dayData['cumulative_balance'] = $runningBalance;
            $cashFlowReport[] = $dayData;
        }

        // Get accounts for filter
        $accounts = ChartOfAccount::whereHas('category', function ($query) {
                $query->where('type', 'Asset');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Cash%')
                      ->orWhere('name', 'like', '%Bank%');
            })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/CashFlowReport', [
            'cashFlowReport' => $cashFlowReport,
            'totalSalesReceipts' => $payments->whereHas('invoice', function ($q) {
                $q->where('type', 'sales');
            })->sum('amount'),
            'totalPurchasePayments' => $payments->whereHas('invoice', function ($q) {
                $q->where('type', 'purchase');
            })->sum('amount'),
            'netCashFlow' => $payments->reduce(function ($carry, $payment) {
                $type = $payment->invoice->type ?? '';
                if ($type === 'sales') {
                    return $carry + $payment->amount;
                } elseif ($type === 'purchase') {
                    return $carry - $payment->amount;
                }
                return $carry;
            }, 0),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'accounts' => $accounts,
            'filters' => $request->only(['account_id', 'from_date', 'to_date']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Generate payment method summary report.
     */
    public function methodSummaryReport(Request $request)
    {
        $startDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::today()->subMonths(1);
        $endDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::today();

        $query = Payment::with(['invoice'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'asc');

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->get();

        // Group by payment method
        $methodSummary = $payments->groupBy('payment_method')
            ->map(function ($items, $method) {
                $salesPayments = $items->filter(function ($payment) {
                    return $payment->invoice && $payment->invoice->type === 'sales';
                });

                $purchasePayments = $items->filter(function ($payment) {
                    return $payment->invoice && $payment->invoice->type === 'purchase';
                });

                return [
                    'method' => $method,
                    'method_display' => $items->first()->payment_method_display,
                    'count' => $items->count(),
                    'total_amount' => $items->sum('amount'),
                    'sales_amount' => $salesPayments->sum('amount'),
                    'purchase_amount' => $purchasePayments->sum('amount'),
                    'sales_count' => $salesPayments->count(),
                    'purchase_count' => $purchasePayments->count(),
                ];
            })
            ->values()
            ->all();

        // Calculate monthly trend
        $monthlyTrend = [];

        if ($startDate->diffInMonths($endDate) > 0) {
            $currentDate = $startDate->copy()->startOfMonth();

            while ($currentDate <= $endDate) {
                $monthStart = $currentDate->copy()->startOfMonth();
                $monthEnd = $currentDate->copy()->endOfMonth();

                if ($monthEnd > $endDate) {
                    $monthEnd = $endDate;
                }

                $monthPayments = $payments->filter(function ($payment) use ($monthStart, $monthEnd) {
                    return $payment->payment_date >= $monthStart && $payment->payment_date <= $monthEnd;
                });

                $methodBreakdown = $monthPayments->groupBy('payment_method')
                    ->map(function ($items, $method) {
                        return [
                            'method' => $method,
                            'method_display' => $items->first()->payment_method_display,
                            'count' => $items->count(),
                            'amount' => $items->sum('amount'),
                        ];
                    })
                    ->values()
                    ->all();

                $monthlyTrend[] = [
                    'month' => $monthStart->format('F Y'),
                    'total_amount' => $monthPayments->sum('amount'),
                    'count' => $monthPayments->count(),
                    'methods' => $methodBreakdown,
                ];

                $currentDate->addMonth();
            }
        }

        $companySetting = CompanySetting::first();

        return Inertia::render('Payments/MethodSummaryReport', [
            'methodSummary' => $methodSummary,
            'monthlyTrend' => $monthlyTrend,
            'totalAmount' => $payments->sum('amount'),
            'totalCount' => $payments->count(),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'filters' => $request->only(['payment_method', 'from_date', 'to_date']),
            'companySetting' => $companySetting,
        ]);
    }
}
