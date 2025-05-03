<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StockMovementController extends Controller
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
        $type = $request->input('type');
        $warehouseId = $request->input('warehouse_id');
        $productId = $request->input('product_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Query stock movements with filters
        $query = StockMovement::with(['product.category', 'warehouse', 'createdBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('remarks', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($query) use ($search) {
                      $query->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        // Get paginated results
        $stockMovements = $query->orderBy('transaction_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get warehouses and products for filter dropdowns
        $warehouses = Warehouse::where('is_active', true)->get();
        $products = Product::where('is_active', true)->get();

        // Get movement types for filter dropdown
        $movementTypes = [
            'purchase' => 'ক্রয়',
            'sale' => 'বিক্রয়',
            'transfer' => 'ট্রান্সফার',
            'adjustment_in' => 'পরিমাণ বৃদ্ধি',
            'adjustment_out' => 'পরিমাণ হ্রাস',
        ];

        return Inertia::render('Inventory/StockMovements/Index', [
            'stockMovements' => $stockMovements,
            'warehouses' => $warehouses,
            'products' => $products,
            'movementTypes' => $movementTypes,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(StockMovement $stockMovement)
    {
        $stockMovement->load(['product.category', 'warehouse', 'createdBy', 'journalEntry']);

        return Inertia::render('Inventory/StockMovements/Show', [
            'stockMovement' => $stockMovement,
            'movementTypes' => [
                'purchase' => 'ক্রয়',
                'sale' => 'বিক্রয়',
                'transfer' => 'ট্রান্সফার',
                'adjustment_in' => 'পরিমাণ বৃদ্ধি',
                'adjustment_out' => 'পরিমাণ হ্রাস',
            ],
        ]);
    }

    /**
     * Show the form for transfer stock.
     */
    public function showTransferForm()
    {
        // Get active warehouses
        $warehouses = Warehouse::where('is_active', true)->get();

        // Get active products with stock balances
        $products = Product::where('is_active', true)
            ->whereHas('stockBalances', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->with(['stockBalances.warehouse'])
            ->get();

        return Inertia::render('Inventory/StockMovements/Transfer', [
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        // Begin transaction
        \DB::beginTransaction();

        try {
            $product = Product::findOrFail($validated['product_id']);
            $fromWarehouse = Warehouse::findOrFail($validated['from_warehouse_id']);
            $toWarehouse = Warehouse::findOrFail($validated['to_warehouse_id']);

            // Check if there is enough stock in the source warehouse
            $sourceStockBalance = $product->stockBalances()
                ->where('warehouse_id', $fromWarehouse->id)
                ->first();

            if (!$sourceStockBalance || $sourceStockBalance->quantity < $validated['quantity']) {
                return back()->withErrors(['quantity' => 'পর্যাপ্ত স্টক নেই।']);
            }

            // Get or create stock balance for the destination warehouse
            $destinationStockBalance = $product->stockBalances()
                ->firstOrCreate(
                    ['warehouse_id' => $toWarehouse->id],
                    ['quantity' => 0, 'average_cost' => $product->purchase_price]
                );

            // Update stock balances
            $sourceStockBalance->quantity -= $validated['quantity'];
            $sourceStockBalance->save();

            $destinationStockBalance->quantity += $validated['quantity'];
            $destinationStockBalance->save();

            // Generate a reference number for the transfer
            $companySettings = \App\Models\CompanySetting::getDefault();
            $prefix = 'TRF-';

            $lastMovement = StockMovement::where('type', 'transfer')->orderBy('id', 'desc')->first();
            $nextNumber = $lastMovement ? intval(substr($lastMovement->reference_number, strlen($prefix))) + 1 : 1;
            $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // Create stock movement records for the transfer
            // 1. Outgoing movement from source warehouse
            $outgoingMovement = StockMovement::create([
                'reference_number' => $referenceNumber . '-OUT',
                'type' => 'transfer',
                'transaction_date' => $validated['transaction_date'],
                'warehouse_id' => $fromWarehouse->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $product->purchase_price,
                'remarks' => 'স্টক ট্রান্সফার আউট: ' . $fromWarehouse->name . ' থেকে ' . $toWarehouse->name . ' - ' . ($validated['remarks'] ?? ''),
                'created_by' => Auth::id(),
            ]);

            // 2. Incoming movement to destination warehouse
            $incomingMovement = StockMovement::create([
                'reference_number' => $referenceNumber . '-IN',
                'type' => 'transfer',
                'transaction_date' => $validated['transaction_date'],
                'warehouse_id' => $toWarehouse->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $product->purchase_price,
                'remarks' => 'স্টক ট্রান্সফার ইন: ' . $fromWarehouse->name . ' থেকে ' . $toWarehouse->name . ' - ' . ($validated['remarks'] ?? ''),
                'created_by' => Auth::id(),
            ]);

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'product' => $product->name,
                    'from_warehouse' => $fromWarehouse->name,
                    'to_warehouse' => $toWarehouse->name,
                    'quantity' => $validated['quantity'],
                    'reference_number' => $referenceNumber,
                ])
                ->log('transferred stock');

            \DB::commit();

            return redirect()->route('stock-movements.index')
                ->with('success', 'স্টক সফলভাবে ট্রান্সফার করা হয়েছে।');
        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }
}
