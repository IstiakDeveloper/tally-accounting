<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DocumentTemplateController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('role:admin,accountant');
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

        // Query document templates with filters
        $query = DocumentTemplate::with('createdBy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $templates = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('Settings/DocumentTemplates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ],
            'types' => [
                'invoice' => 'ইনভয়েস',
                'purchase_order' => 'ক্রয় অর্ডার',
                'sales_order' => 'বিক্রয় অর্ডার',
                'receipt' => 'রসিদ',
                'payment_voucher' => 'পেমেন্ট ভাউচার',
                'salary_slip' => 'বেতন স্লিপ',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Settings/DocumentTemplates/Create', [
            'types' => [
                'invoice' => 'ইনভয়েস',
                'purchase_order' => 'ক্রয় অর্ডার',
                'sales_order' => 'বিক্রয় অর্ডার',
                'receipt' => 'রসিদ',
                'payment_voucher' => 'পেমেন্ট ভাউচার',
                'salary_slip' => 'বেতন স্লিপ',
            ],
            'placeholders' => [
                'company' => [
                    '{{company_name}}' => 'কোম্পানির নাম',
                    '{{company_address}}' => 'কোম্পানির ঠিকানা',
                    '{{company_phone}}' => 'কোম্পানির ফোন নম্বর',
                    '{{company_email}}' => 'কোম্পানির ইমেইল',
                    '{{company_website}}' => 'কোম্পানির ওয়েবসাইট',
                    '{{company_tax_number}}' => 'কোম্পানির ট্যাক্স নম্বর',
                ],
                'document' => [
                    '{{document_number}}' => 'ডকুমেন্ট নম্বর',
                    '{{document_date}}' => 'ডকুমেন্ট তারিখ',
                    '{{document_due_date}}' => 'পরিশোধের শেষ তারিখ',
                    '{{document_total}}' => 'মোট পরিমাণ',
                    '{{document_subtotal}}' => 'সাবটোটাল',
                    '{{document_tax}}' => 'ট্যাক্স',
                    '{{document_discount}}' => 'ডিসকাউন্ট',
                ],
                'contact' => [
                    '{{contact_name}}' => 'যোগাযোগের নাম',
                    '{{contact_address}}' => 'যোগাযোগের ঠিকানা',
                    '{{contact_phone}}' => 'যোগাযোগের ফোন নম্বর',
                    '{{contact_email}}' => 'যোগাযোগের ইমেইল',
                    '{{contact_tax_number}}' => 'যোগাযোগের ট্যাক্স নম্বর',
                ],
                'items' => [
                    '{{items_table}}' => 'আইটেম টেবিল',
                ],
                'payment' => [
                    '{{payment_method}}' => 'পেমেন্ট পদ্ধতি',
                    '{{payment_details}}' => 'পেমেন্ট বিবরণ',
                ],
                'employee' => [
                    '{{employee_name}}' => 'কর্মচারীর নাম',
                    '{{employee_id}}' => 'কর্মচারী আইডি',
                    '{{employee_designation}}' => 'কর্মচারীর পদবি',
                    '{{employee_department}}' => 'কর্মচারীর বিভাগ',
                    '{{employee_salary}}' => 'কর্মচারীর বেতন',
                ],
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
            'type' => 'required|in:invoice,purchase_order,sales_order,receipt,payment_voucher,salary_slip',
            'content' => 'required|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        // If this is set as default, unset default for other templates of this type
        if ($validated['is_default']) {
            DocumentTemplate::where('type', $validated['type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = DocumentTemplate::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($template)
            ->withProperties([
                'name' => $template->name,
                'type' => $template->type,
            ])
            ->log('created');

        return redirect()->route('settings.document-templates.index')
            ->with('success', 'ডকুমেন্ট টেমপ্লেট সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load('createdBy');

        return Inertia::render('Settings/DocumentTemplates/Show', [
            'template' => $documentTemplate,
            'types' => [
                'invoice' => 'ইনভয়েস',
                'purchase_order' => 'ক্রয় অর্ডার',
                'sales_order' => 'বিক্রয় অর্ডার',
                'receipt' => 'রসিদ',
                'payment_voucher' => 'পেমেন্ট ভাউচার',
                'salary_slip' => 'বেতন স্লিপ',
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentTemplate $documentTemplate)
    {
        return Inertia::render('Settings/DocumentTemplates/Edit', [
            'template' => $documentTemplate,
            'types' => [
                'invoice' => 'ইনভয়েস',
                'purchase_order' => 'ক্রয় অর্ডার',
                'sales_order' => 'বিক্রয় অর্ডার',
                'receipt' => 'রসিদ',
                'payment_voucher' => 'পেমেন্ট ভাউচার',
                'salary_slip' => 'বেতন স্লিপ',
            ],
            'placeholders' => [
                'company' => [
                    '{{company_name}}' => 'কোম্পানির নাম',
                    '{{company_address}}' => 'কোম্পানির ঠিকানা',
                    '{{company_phone}}' => 'কোম্পানির ফোন নম্বর',
                    '{{company_email}}' => 'কোম্পানির ইমেইল',
                    '{{company_website}}' => 'কোম্পানির ওয়েবসাইট',
                    '{{company_tax_number}}' => 'কোম্পানির ট্যাক্স নম্বর',
                ],
                'document' => [
                    '{{document_number}}' => 'ডকুমেন্ট নম্বর',
                    '{{document_date}}' => 'ডকুমেন্ট তারিখ',
                    '{{document_due_date}}' => 'পরিশোধের শেষ তারিখ',
                    '{{document_total}}' => 'মোট পরিমাণ',
                    '{{document_subtotal}}' => 'সাবটোটাল',
                    '{{document_tax}}' => 'ট্যাক্স',
                    '{{document_discount}}' => 'ডিসকাউন্ট',
                ],
                'contact' => [
                    '{{contact_name}}' => 'যোগাযোগের নাম',
                    '{{contact_address}}' => 'যোগাযোগের ঠিকানা',
                    '{{contact_phone}}' => 'যোগাযোগের ফোন নম্বর',
                    '{{contact_email}}' => 'যোগাযোগের ইমেইল',
                    '{{contact_tax_number}}' => 'যোগাযোগের ট্যাক্স নম্বর',
                ],
                'items' => [
                    '{{items_table}}' => 'আইটেম টেবিল',
                ],
                'payment' => [
                    '{{payment_method}}' => 'পেমেন্ট পদ্ধতি',
                    '{{payment_details}}' => 'পেমেন্ট বিবরণ',
                ],
                'employee' => [
                    '{{employee_name}}' => 'কর্মচারীর নাম',
                    '{{employee_id}}' => 'কর্মচারী আইডি',
                    '{{employee_designation}}' => 'কর্মচারীর পদবি',
                    '{{employee_department}}' => 'কর্মচারীর বিভাগ',
                    '{{employee_salary}}' => 'কর্মচারীর বেতন',
                ],
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:invoice,purchase_order,sales_order,receipt,payment_voucher,salary_slip',
            'content' => 'required|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $documentTemplate->name,
            'type' => $documentTemplate->type,
            'is_default' => $documentTemplate->is_default,
            'is_active' => $documentTemplate->is_active,
        ];

        // If this is set as default, unset default for other templates of this type
        if ($validated['is_default'] && !$documentTemplate->is_default) {
            DocumentTemplate::where('type', $validated['type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $documentTemplate->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($documentTemplate)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $documentTemplate->name,
                    'type' => $documentTemplate->type,
                    'is_default' => $documentTemplate->is_default,
                    'is_active' => $documentTemplate->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('settings.document-templates.index')
            ->with('success', 'ডকুমেন্ট টেমপ্লেট সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentTemplate $documentTemplate)
    {
        // Check if the template is the default one
        if ($documentTemplate->is_default) {
            return back()->with('error', 'ডিফল্ট টেমপ্লেট মুছে ফেলা যাবে না।');
        }

        // Store template details for audit log
        $templateDetails = [
            'id' => $documentTemplate->id,
            'name' => $documentTemplate->name,
            'type' => $documentTemplate->type,
        ];

        $documentTemplate->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($templateDetails)
            ->log('deleted document template');

        return redirect()->route('settings.document-templates.index')
            ->with('success', 'ডকুমেন্ট টেমপ্লেট সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the document template.
     */
    public function toggleStatus(DocumentTemplate $documentTemplate)
    {
        $oldStatus = $documentTemplate->is_active;
        $documentTemplate->is_active = !$oldStatus;
        $documentTemplate->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($documentTemplate)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $documentTemplate->is_active,
            ])
            ->log('toggled status');

        $statusText = $documentTemplate->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "ডকুমেন্ট টেমপ্লেট সফলভাবে {$statusText} করা হয়েছে।");
    }

    /**
     * Set the template as default.
     */
    public function setDefault(DocumentTemplate $documentTemplate)
    {
        // Unset default for other templates of this type
        DocumentTemplate::where('type', $documentTemplate->type)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this template as default
        $documentTemplate->is_default = true;
        $documentTemplate->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($documentTemplate)
            ->log('set as default');

        return back()->with('success', 'ডকুমেন্ট টেমপ্লেট সফলভাবে ডিফল্ট হিসেবে সেট করা হয়েছে।');
    }
}
