<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('role:admin,manager')->except(['index', 'show']);
        $this->middleware('role:admin,manager,accountant,user');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');
        $stockStatus = $request->input('stock_status');

        // Query products with filters
        $query = Product::with(['category', 'createdBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        if ($stockStatus) {
            switch ($stockStatus) {
                case 'in_stock':
                    $query->whereHas('stockBalances', function ($q) {
                        $q->where('quantity', '>', 0);
                    });
                    break;
                case 'low_stock':
                    $query->whereHas('stockBalances', function ($q) {
                        $q->whereRaw('quantity <= products.reorder_level AND quantity > 0');
                    });
                    break;
                case 'out_of_stock':
                    $query->whereDoesntHave('stockBalances', function ($q) {
                        $q->where('quantity', '>', 0);
                    });
                    break;
            }
        }

        // Get paginated results
        $products = $query->orderBy('name')->paginate(10)
            ->withQueryString();

        // Load stock balances for each product
        $products->each(function ($product) {
            $product->load('stockBalances.warehouse');
            $product->append('total_stock');
        });

        // Get all product categories for the filter dropdown
        $categories = ProductCategory::where('is_active', true)->get();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'status' => $status,
                'stock_status' => $stockStatus,
            ],
            'canEdit' => Auth::user()->role === 'admin' || Auth::user()->role === 'manager',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = ProductCategory::where('is_active', true)->get();

        return Inertia::render('Inventory/Products/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:products,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:product_categories,id',
            'unit' => 'required|string|max:20',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        $product = Product::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($product)
            ->withProperties([
                'code' => $product->code,
                'name' => $product->name,
            ])
            ->log('created');

        return redirect()->route('products.index')
            ->with('success', 'পণ্য সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'createdBy', 'stockBalances.warehouse']);

        // Get stock movements for this product
        $stockMovements = $product->stockMovements()
            ->with('warehouse')
            ->orderBy('transaction_date', 'desc')
            ->paginate(10);

        return Inertia::render('Inventory/Products/Show', [
            'product' => $product,
            'totalStock' => $product->total_stock,
            'stockStatus' => $product->stock_status,
            'stockMovements' => $stockMovements,
            'canEdit' => Auth::user()->role === 'admin' || Auth::user()->role === 'manager',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $categories = ProductCategory::where('is_active', true)->get();

        return Inertia::render('Inventory/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('products')->ignore($product->id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:product_categories,id',
            'unit' => 'required|string|max:20',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'code' => $product->code,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'unit' => $product->unit,
            'purchase_price' => $product->purchase_price,
            'selling_price' => $product->selling_price,
            'reorder_level' => $product->reorder_level,
            'is_active' => $product->is_active,
        ];

        $product->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($product)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'code' => $product->code,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'unit' => $product->unit,
                    'purchase_price' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'reorder_level' => $product->reorder_level,
                    'is_active' => $product->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('products.index')
            ->with('success', 'পণ্য সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // Check if the product has any stock movements
        if ($product->stockMovements()->exists()) {
            return back()->with('error', 'এই পণ্যের স্টক মুভমেন্ট রেকর্ড আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Check if the product has any stock balances
        if ($product->stockBalances()->exists()) {
            return back()->with('error', 'এই পণ্যের স্টক ব্যালেন্স আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store product details for audit log
        $productDetails = [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
        ];

        $product->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($productDetails)
            ->log('deleted product');

        return redirect()->route('products.index')
            ->with('success', 'পণ্য সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the product.
     */
    public function toggleStatus(Product $product)
    {
        $oldStatus = $product->is_active;
        $product->is_active = !$oldStatus;
        $product->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($product)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $product->is_active,
            ])
            ->log('toggled status');

        $statusText = $product->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "পণ্য সফলভাবে {$statusText} করা হয়েছে।");
    }

    /**
     * Show the form for adjusting stock.
     */
    public function showAdjustStockForm(Product $product)
    {
        $product->load('category');

        // Get all warehouses
        $warehouses = Warehouse::where('is_active', true)->get();

        // Get current stock balances
        $stockBalances = $product->stockBalances()->with('warehouse')->get();

        return Inertia::render('Inventory/Products/AdjustStock', [
            'product' => $product,
            'warehouses' => $warehouses,
            'stockBalances' => $stockBalances,
        ]);
    }

    /**
     * Adjust stock quantity.
     */
    public function adjustStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric',
            'type' => 'required|in:add,subtract',
            'remarks' => 'nullable|string',
        ]);

        // Begin transaction
        DB::beginTransaction();

        try {
            $warehouseId = $validated['warehouse_id'];
            $quantity = abs($validated['quantity']);
            $type = $validated['type'];
            $remarks = $validated['remarks'] ?? 'স্টক সমন্বয়';

            // Get or create stock balance
            $stockBalance = StockBalance::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'average_cost' => $product->purchase_price]
            );

            // Update stock balance
            if ($type === 'add') {
                $stockBalance->quantity += $quantity;
                $movementType = 'adjustment_in';
            } else {
                // Check if there is enough stock
                if ($stockBalance->quantity < $quantity) {
                    return back()->withErrors(['quantity' => 'পর্যাপ্ত স্টক নেই।']);
                }

                $stockBalance->quantity -= $quantity;
                $movementType = 'adjustment_out';
            }

            $stockBalance->save();

            // Create stock movement record
            $companySettings = \App\Models\CompanySetting::getDefault();
            $prefix = $type === 'add' ? 'ADJ-IN-' : 'ADJ-OUT-';

            // Generate a new reference number
            $lastMovement = StockMovement::where('type', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            $nextNumber = $lastMovement ? intval(substr($lastMovement->reference_number, strlen($prefix))) + 1 : 1;
            $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // Create stock movement
            $stockMovement = StockMovement::create([
                'reference_number' => $referenceNumber,
                'type' => $movementType,
                'transaction_date' => now(),
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->purchase_price,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
            ]);

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($product)
                ->withProperties([
                    'type' => $type,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'reference_number' => $referenceNumber,
                ])
                ->log('adjusted stock');

            DB::commit();

            return redirect()->route('products.show', $product)
                ->with('success', 'স্টক সফলভাবে সমন্বয় করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }
}
