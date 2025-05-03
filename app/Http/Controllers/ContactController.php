<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('role:admin,manager,accountant');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $type = $request->input('type');
        $status = $request->input('status');

        // Query contacts with filters
        $query = Contact::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $contacts = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ],
            'types' => [
                'customer' => 'ক্রেতা',
                'supplier' => 'সাপ্লায়ার',
                'both' => 'উভয়',
            ],
        ]);
    }

    /**
     * Display a listing of customers.
     */
    public function customers(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $status = $request->input('status');

        // Query customers with filters
        $query = Contact::customers();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $customers = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('Contacts/Customers', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display a listing of suppliers.
     */
    public function suppliers(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $status = $request->input('status');

        // Query suppliers with filters
        $query = Contact::suppliers();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $suppliers = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('Contacts/Suppliers', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get accounts for dropdown
        $accounts = ChartOfAccount::with('category')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Contacts/Create', [
            'accounts' => $accounts,
            'types' => [
                'customer' => 'ক্রেতা',
                'supplier' => 'সাপ্লায়ার',
                'both' => 'উভয়',
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
            'type' => 'required|in:customer,supplier,both',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'tax_number' => 'nullable|string|max:50',
            'account_receivable_id' => 'nullable|exists:chart_of_accounts,id',
            'account_payable_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        $contact = Contact::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($contact)
            ->withProperties([
                'name' => $contact->name,
                'type' => $contact->type,
            ])
            ->log('created');

        // Redirect based on contact type
        if ($contact->type === 'customer') {
            return redirect()->route('contacts.customers')
                ->with('success', 'ক্রেতা সফলভাবে তৈরি করা হয়েছে।');
        } elseif ($contact->type === 'supplier') {
            return redirect()->route('contacts.suppliers')
                ->with('success', 'সাপ্লায়ার সফলভাবে তৈরি করা হয়েছে।');
        } else {
            return redirect()->route('contacts.index')
                ->with('success', 'যোগাযোগ সফলভাবে তৈরি করা হয়েছে।');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        $contact->load('accountReceivable', 'accountPayable', 'createdBy');

        // Get transactions summary
        $salesOrders = $contact->salesOrders()->count();
        $purchaseOrders = $contact->purchaseOrders()->count();
        $salesInvoices = $contact->invoices()->where('type', 'sales')->count();
        $purchaseInvoices = $contact->invoices()->where('type', 'purchase')->count();

        // Get account balances
        $outstandingReceivable = $contact->outstanding_receivable;
        $outstandingPayable = $contact->outstanding_payable;

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'transactions' => [
                'salesOrders' => $salesOrders,
                'purchaseOrders' => $purchaseOrders,
                'salesInvoices' => $salesInvoices,
                'purchaseInvoices' => $purchaseInvoices,
            ],
            'balances' => [
                'outstandingReceivable' => $outstandingReceivable,
                'outstandingPayable' => $outstandingPayable,
            ],
            'types' => [
                'customer' => 'ক্রেতা',
                'supplier' => 'সাপ্লায়ার',
                'both' => 'উভয়',
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        // Get accounts for dropdown
        $accounts = ChartOfAccount::with('category')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Contacts/Edit', [
            'contact' => $contact,
            'accounts' => $accounts,
            'types' => [
                'customer' => 'ক্রেতা',
                'supplier' => 'সাপ্লায়ার',
                'both' => 'উভয়',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:customer,supplier,both',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'tax_number' => 'nullable|string|max:50',
            'account_receivable_id' => 'nullable|exists:chart_of_accounts,id',
            'account_payable_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $contact->name,
            'type' => $contact->type,
            'contact_person' => $contact->contact_person,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'address' => $contact->address,
            'tax_number' => $contact->tax_number,
            'account_receivable_id' => $contact->account_receivable_id,
            'account_payable_id' => $contact->account_payable_id,
            'is_active' => $contact->is_active,
        ];

        $contact->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($contact)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $contact->name,
                    'type' => $contact->type,
                    'contact_person' => $contact->contact_person,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'address' => $contact->address,
                    'tax_number' => $contact->tax_number,
                    'account_receivable_id' => $contact->account_receivable_id,
                    'account_payable_id' => $contact->account_payable_id,
                    'is_active' => $contact->is_active,
                ],
            ])
            ->log('updated');

        // Redirect based on contact type
        if ($contact->type === 'customer') {
            return redirect()->route('contacts.customers')
                ->with('success', 'ক্রেতা সফলভাবে আপডেট করা হয়েছে।');
        } elseif ($contact->type === 'supplier') {
            return redirect()->route('contacts.suppliers')
                ->with('success', 'সাপ্লায়ার সফলভাবে আপডেট করা হয়েছে।');
        } else {
            return redirect()->route('contacts.index')
                ->with('success', 'যোগাযোগ সফলভাবে আপডেট করা হয়েছে।');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        // Begin transaction
        DB::beginTransaction();

        try {
            // Check if the contact has related records
            if ($contact->salesOrders()->exists() ||
                $contact->purchaseOrders()->exists() ||
                $contact->invoices()->exists()) {

                return back()->with('error', 'এই যোগাযোগের সাথে সম্পর্কিত লেনদেন আছে, তাই এটি মুছে ফেলা যাবে না।');
            }

            // Store contact details for audit log
            $contactDetails = [
                'id' => $contact->id,
                'name' => $contact->name,
                'type' => $contact->type,
            ];

            $contactType = $contact->type;

            $contact->delete();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->withProperties($contactDetails)
                ->log('deleted contact');

            DB::commit();

            // Redirect based on contact type
            if ($contactType === 'customer') {
                return redirect()->route('contacts.customers')
                    ->with('success', 'ক্রেতা সফলভাবে মুছে ফেলা হয়েছে।');
            } elseif ($contactType === 'supplier') {
                return redirect()->route('contacts.suppliers')
                    ->with('success', 'সাপ্লায়ার সফলভাবে মুছে ফেলা হয়েছে।');
            } else {
                return redirect()->route('contacts.index')
                    ->with('success', 'যোগাযোগ সফলভাবে মুছে ফেলা হয়েছে।');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle the active status of the contact.
     */
    public function toggleStatus(Contact $contact)
    {
        $oldStatus = $contact->is_active;
        $contact->is_active = !$oldStatus;
        $contact->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($contact)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $contact->is_active,
            ])
            ->log('toggled status');

        $statusText = $contact->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "যোগাযোগ সফলভাবে {$statusText} করা হয়েছে।");
    }
}
