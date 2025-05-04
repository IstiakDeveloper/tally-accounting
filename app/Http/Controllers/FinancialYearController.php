<?php

namespace App\Http\Controllers;

use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinancialYearController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $financialYears = FinancialYear::orderBy('start_date', 'desc')->get();

        return Inertia::render('Accounting/FinancialYears/Index', [
            'financialYears' => $financialYears,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Accounting/FinancialYears/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:financial_years,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        // Validate that date range doesn't overlap with existing financial years
        $overlapping = FinancialYear::where(function ($query) use ($validated) {
            $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                ->orWhere(function ($q) use ($validated) {
                    $q->where('start_date', '<=', $validated['start_date'])
                      ->where('end_date', '>=', $validated['end_date']);
                });
        })->exists();

        if ($overlapping) {
            return back()->withErrors(['date_range' => 'নির্বাচিত তারিখ পরিসীমা অন্য অর্থবছরের সাথে ওভারল্যাপ করে।']);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            $financialYear = FinancialYear::create($validated);

            // If this financial year is set as active, deactivate others
            if ($validated['is_active'] ?? false) {
                FinancialYear::where('id', '!=', $financialYear->id)
                    ->update(['is_active' => false]);
            }

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($financialYear)
                ->withProperties([
                    'name' => $financialYear->name,
                    'start_date' => $financialYear->start_date->format('Y-m-d'),
                    'end_date' => $financialYear->end_date->format('Y-m-d'),
                    'is_active' => $financialYear->is_active,
                ])
                ->log('created');

            DB::commit();

            return redirect()->route('financial-years.index')
                ->with('success', 'অর্থবছর সফলভাবে তৈরি করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialYear $financialYear)
    {
        return Inertia::render('Accounting/FinancialYears/Edit', [
            'financialYear' => $financialYear,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialYear $financialYear)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:financial_years,name,' . $financialYear->id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        // Validate that date range doesn't overlap with existing financial years
        $overlapping = FinancialYear::where('id', '!=', $financialYear->id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_date', '<=', $validated['start_date'])
                          ->where('end_date', '>=', $validated['end_date']);
                    });
            })->exists();

        if ($overlapping) {
            return back()->withErrors(['date_range' => 'নির্বাচিত তারিখ পরিসীমা অন্য অর্থবছরের সাথে ওভারল্যাপ করে।']);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Store old values for audit log
            $oldValues = [
                'name' => $financialYear->name,
                'start_date' => $financialYear->start_date->format('Y-m-d'),
                'end_date' => $financialYear->end_date->format('Y-m-d'),
                'is_active' => $financialYear->is_active,
            ];

            $financialYear->update($validated);

            // If this financial year is set as active, deactivate others
            if ($validated['is_active'] ?? false) {
                FinancialYear::where('id', '!=', $financialYear->id)
                    ->update(['is_active' => false]);
            }

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($financialYear)
                ->withProperties([
                    'old' => $oldValues,
                    'new' => [
                        'name' => $financialYear->name,
                        'start_date' => $financialYear->start_date->format('Y-m-d'),
                        'end_date' => $financialYear->end_date->format('Y-m-d'),
                        'is_active' => $financialYear->is_active,
                    ],
                ])
                ->log('updated');

            DB::commit();

            return redirect()->route('financial-years.index')
                ->with('success', 'অর্থবছর সফলভাবে আপডেট করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialYear $financialYear)
    {
        // Check if the financial year has related records
        if ($financialYear->journalEntries()->exists()) {
            return back()->with('error', 'এই অর্থবছরের সাথে সম্পর্কিত জার্নাল এন্ট্রি আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Check if the financial year is active
        if ($financialYear->is_active) {
            return back()->with('error', 'সক্রিয় অর্থবছর মুছে ফেলা যাবে না।');
        }

        // Store financial year details for audit log
        $yearDetails = [
            'id' => $financialYear->id,
            'name' => $financialYear->name,
            'start_date' => $financialYear->start_date->format('Y-m-d'),
            'end_date' => $financialYear->end_date->format('Y-m-d'),
        ];

        $financialYear->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($yearDetails)
            ->log('deleted financial year');

        return redirect()->route('financial-years.index')
            ->with('success', 'অর্থবছর সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Activate the specified financial year.
     */
    public function activate(FinancialYear $financialYear)
    {
        // Begin transaction
        DB::beginTransaction();

        try {
            // Deactivate all financial years
            FinancialYear::where('id', '!=', $financialYear->id)
                ->update(['is_active' => false]);

            // Activate this financial year
            $financialYear->is_active = true;
            $financialYear->save();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($financialYear)
                ->log('activated');

            DB::commit();

            return back()->with('success', 'অর্থবছর সফলভাবে সক্রিয় করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }
}
