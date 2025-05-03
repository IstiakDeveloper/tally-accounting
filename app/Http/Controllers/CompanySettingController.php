<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompanySettingController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companySetting = CompanySetting::getDefault();

        return Inertia::render('Settings/Company/Index', [
            'company' => $companySetting,
            'currencies' => [
                'BDT' => 'বাংলাদেশি টাকা (BDT)',
            ],
            'dateFormats' => [
                'd/m/Y' => 'দিন/মাস/বছর (31/12/2023)',
                'm/d/Y' => 'মাস/দিন/বছর (12/31/2023)',
                'Y-m-d' => 'বছর-মাস-দিন (2023-12-31)',
            ],
            'timeFormats' => [
                'h:i A' => '12-ঘন্টা (09:30 AM)',
                'H:i' => '24-ঘন্টা (09:30)',
            ],
            'timezones' => [
                'Asia/Dhaka' => 'ঢাকা (GMT+6)',
                'Asia/Kolkata' => 'কলকাতা (GMT+5:30)',
            ],
            'fiscalYearStartMonths' => [
                'January' => 'জানুয়ারি',
                'April' => 'এপ্রিল',
                'July' => 'জুলাই',
                'October' => 'অক্টোবর',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $companySetting = CompanySetting::getDefault();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|string|max:20',
            'timezone' => 'required|string|max:100',
            'fiscal_year_start_month' => 'required|string|max:20',
            'decimal_separator' => 'required|string|max:1',
            'thousand_separator' => 'required|string|max:1',
            'invoice_prefix' => 'required|string|max:10',
            'purchase_prefix' => 'required|string|max:10',
            'sales_prefix' => 'required|string|max:10',
            'receipt_prefix' => 'required|string|max:10',
            'payment_prefix' => 'required|string|max:10',
            'journal_prefix' => 'required|string|max:10',
        ]);

        // Store old values for audit log
        $oldValues = $companySetting->toArray();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($companySetting->logo && Storage::disk('public')->exists($companySetting->logo)) {
                Storage::disk('public')->delete($companySetting->logo);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $logoPath;
        }

        $companySetting->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($companySetting)
            ->withProperties([
                'old' => $oldValues,
                'new' => $companySetting->toArray(),
            ])
            ->log('updated');

        return back()->with('success', 'কোম্পানি সেটিংস সফলভাবে আপডেট করা হয়েছে।');
    }
}
