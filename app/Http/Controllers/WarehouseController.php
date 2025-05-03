<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('role:admin,manager');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $status = $request->input('status');

        // Query warehouses with filters
        $query = Warehouse::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results
        $warehouses = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        // Load stock balances for summary
        $warehouses->each(function ($warehouse) {
            $warehouse->loadCount('stockBalances');
            $warehouse->total_stock_value = $warehouse->stockBalances()
                ->join('products', 'stock_balances.product_id', '=', 'products.id')
                ->selectRaw('SUM(stock_balances.quantity * products.purchase_price) as total_value')
                ->value('total_value') ?? 0;
        });

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => $warehouses,
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
        return Inertia::render('Inventory/Warehouses/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $warehouse = Warehouse::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($warehouse)
            ->withProperties([
                'name' => $warehouse->name,
            ])
            ->log('created');

        return redirect()->route('warehouses.index')
            ->with('success', 'গুদাম সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse)
    {
        // Load stock balances for this warehouse
        $stockBalances = $warehouse->stockBalances()
            ->with('product.category')
            ->whereHas('product', function ($query) {
                $query->where('is_active', true);
            })
            ->paginate(20);

        // Calculate total stock value
        $totalStockValue = $warehouse->stockBalances()
            ->join('products', 'stock_balances.product_id', '=', 'products.id')
            ->selectRaw('SUM(stock_balances.quantity * products.purchase_price) as total_value')
            ->value('total_value') ?? 0;

        return Inertia::render('Inventory/Warehouses/Show', [
            'warehouse' => $warehouse,
            'stockBalances' => $stockBalances,
            'totalStockValue' => $totalStockValue,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Warehouse $warehouse)
    {
        return Inertia::render('Inventory/Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $warehouse->name,
            'address' => $warehouse->address,
            'contact_person' => $warehouse->contact_person,
            'contact_number' => $warehouse->contact_number,
            'is_active' => $warehouse->is_active,
        ];

        $warehouse->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($warehouse)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $warehouse->name,
                    'address' => $warehouse->address,
                    'contact_person' => $warehouse->contact_person,
                    'contact_number' => $warehouse->contact_number,
                    'is_active' => $warehouse->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('warehouses.index')
            ->with('success', 'গুদাম সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        // Check if the warehouse has any stock balances
        if ($warehouse->stockBalances()->exists()) {
            return back()->with('error', 'এই গুদামে পণ্য মজুদ আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Check if the warehouse has any stock movements
        if ($warehouse->stockMovements()->exists()) {
            return back()->with('error', 'এই গুদামের সাথে স্টক মুভমেন্ট সম্পর্কিত আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store warehouse details for audit log
        $warehouseDetails = [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
        ];

        $warehouse->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($warehouseDetails)
            ->log('deleted warehouse');

        return redirect()->route('warehouses.index')
            ->with('success', 'গুদাম সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the warehouse.
     */
    public function toggleStatus(Warehouse $warehouse)
    {
        $oldStatus = $warehouse->is_active;
        $warehouse->is_active = !$oldStatus;
        $warehouse->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($warehouse)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $warehouse->is_active,
            ])
            ->log('toggled status');

        $statusText = $warehouse->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "গুদাম সফলভাবে {$statusText} করা হয়েছে।");
    }
}
