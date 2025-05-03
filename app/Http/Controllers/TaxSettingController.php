<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\TaxSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaxSettingController extends Controller
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
        $status = $request->input('status');

        // Query tax settings with filters
        $query = TaxSetting::with('account');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $taxSettings = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        return Inertia::render('Settings/Taxes/Index', [
            'taxSettings' => $taxSettings,
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
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereHas('category', function ($query) {
                $query->where('type', 'Liability');
            })
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Settings/Taxes/Create', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        $taxSetting = TaxSetting::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($taxSetting)
            ->withProperties([
                'name' => $taxSetting->name,
                'rate' => $taxSetting->rate,
            ])
            ->log('created');

        return redirect()->route('settings.taxes.index')
            ->with('success', 'ট্যাক্স সেটিং সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxSetting $taxSetting)
    {
        // Get accounts for dropdown
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereHas('category', function ($query) {
                $query->where('type', 'Liability');
            })
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Settings/Taxes/Edit', [
            'taxSetting' => $taxSetting,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxSetting $taxSetting)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $taxSetting->name,
            'rate' => $taxSetting->rate,
            'description' => $taxSetting->description,
            'is_active' => $taxSetting->is_active,
            'account_id' => $taxSetting->account_id,
        ];

        $taxSetting->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($taxSetting)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $taxSetting->name,
                    'rate' => $taxSetting->rate,
                    'description' => $taxSetting->description,
                    'is_active' => $taxSetting->is_active,
                    'account_id' => $taxSetting->account_id,
                ],
            ])
            ->log('updated');

        return redirect()->route('settings.taxes.index')
            ->with('success', 'ট্যাক্স সেটিং সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxSetting $taxSetting)
    {
        // Store tax setting details for audit log
        $taxSettingDetails = [
            'id' => $taxSetting->id,
            'name' => $taxSetting->name,
            'rate' => $taxSetting->rate,
        ];

        $taxSetting->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($taxSettingDetails)
            ->log('deleted tax setting');

        return redirect()->route('settings.taxes.index')
            ->with('success', 'ট্যাক্স সেটিং সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the tax setting.
     */
    public function toggleStatus(TaxSetting $taxSetting)
    {
        $oldStatus = $taxSetting->is_active;
        $taxSetting->is_active = !$oldStatus;
        $taxSetting->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($taxSetting)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $taxSetting->is_active,
            ])
            ->log('toggled status');

        $statusText = $taxSetting->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "ট্যাক্স সেটিং সফলভাবে {$statusText} করা হয়েছে।");
    }
}
