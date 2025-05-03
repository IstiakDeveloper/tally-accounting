<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
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
        $supplierId = $request->input('supplier_id');
        $status = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Query purchase orders with filters
        $query = PurchaseOrder::with(['supplier', 'createdBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->whereDate('order_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('order_date', '<=', $endDate);
        }

        // Get paginated results
        $purchaseOrders = $query->orderBy('order_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Get suppliers for filter dropdown
        $suppliers = Contact::suppliers()->where('is_active', true)->get();

        return Inertia::render('Purchases/Orders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'supplier_id' => $supplierId,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'statuses' => [
                'draft' => 'ড্রাফট',
                'confirmed' => 'নিশ্চিত',
                'received' => 'গৃহীত',
                'cancelled' => 'বাতিল',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get suppliers for dropdown
        $suppliers = Contact::suppliers()->where('is_active', true)->get();

        // Get products for dropdown
        $products = Product::where('is_active', true)->with('category')->get();

        // Get warehouses for dropdown
        $warehouses = Warehouse::where('is_active', true)->get();

        // Get company settings for reference number prefix
        $companySettings = \App\Models\CompanySetting::getDefault();
        $prefix = $companySettings->purchase_prefix;

        // Generate a new reference number
        $lastOrder = PurchaseOrder::orderBy('id', 'desc')->first();
        $nextNumber = $lastOrder ? intval(substr($lastOrder->reference_number, strlen($prefix))) + 1 : 1;
        $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return Inertia::render('Purchases/Orders/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'warehouses' => $warehouses,
            'referenceNumber' => $referenceNumber,
            'today' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|max:20|unique:purchase_orders,reference_number',
            'supplier_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        // Calculate totals
        $totalAmount = 0;

        foreach ($validated['items'] as &$item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $discount = $item['discount'] ?? 0;
            $taxAmount = $item['tax_amount'] ?? 0;

            $item['total'] = $subtotal - $discount + $taxAmount;
            $totalAmount += $item['total'];
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'reference_number' => $validated['reference_number'],
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'],
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'remarks' => $validated['remarks'],
                'created_by' => Auth::id(),
            ]);

            // Create purchase order items
            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'total' => $item['total'],
                ]);
            }

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($purchaseOrder)
                ->withProperties([
                    'reference_number' => $purchaseOrder->reference_number,
                    'supplier' => Contact::find($purchaseOrder->supplier_id)->name,
                    'total_amount' => $totalAmount,
                ])
                ->log('created');

            DB::commit();

            return redirect()->route('purchase-orders.index')
                ->with('success', 'ক্রয় অর্ডার সফলভাবে তৈরি করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'createdBy', 'items.product']);

        return Inertia::render('Purchases/Orders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'statuses' => [
                'draft' => 'ড্রাফট',
                'confirmed' => 'নিশ্চিত',
                'received' => 'গৃহীত',
                'cancelled' => 'বাতিল',
            ],
            'canConfirm' => $purchaseOrder->canBeConfirmed(),
            'canReceive' => $purchaseOrder->canBeReceived(),
            'canCancel' => $purchaseOrder->canBeCancelled(),
            'canInvoice' => $purchaseOrder->canBeInvoiced(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order is editable
        if (!$purchaseOrder->isEditable()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'শুধুমাত্র ড্রাফট ক্রয় অর্ডার সম্পাদনা করা যাবে।');
        }

        $purchaseOrder->load(['supplier', 'items.product']);

        // Get suppliers for dropdown
        $suppliers = Contact::suppliers()->where('is_active', true)->get();

        // Get products for dropdown
        $products = Product::where('is_active', true)->with('category')->get();

        return Inertia::render('Purchases/Orders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order is editable
        if (!$purchaseOrder->isEditable()) {
            return back()->with('error', 'শুধুমাত্র ড্রাফট ক্রয় অর্ডার সম্পাদনা করা যাবে।');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        // Calculate totals
        $totalAmount = 0;

        foreach ($validated['items'] as &$item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $discount = $item['discount'] ?? 0;
            $taxAmount = $item['tax_amount'] ?? 0;

            $item['total'] = $subtotal - $discount + $taxAmount;
            $totalAmount += $item['total'];
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Store old values for audit log
            $oldValues = [
                'supplier_id' => $purchaseOrder->supplier_id,
                'order_date' => $purchaseOrder->order_date,
                'expected_delivery_date' => $purchaseOrder->expected_delivery_date,
                'total_amount' => $purchaseOrder->total_amount,
                'remarks' => $purchaseOrder->remarks,
            ];

            // Update purchase order
            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'],
                'total_amount' => $totalAmount,
                'remarks' => $validated['remarks'],
            ]);

            // Get existing item IDs
            $existingItemIds = $purchaseOrder->items->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create purchase order items
            foreach ($validated['items'] as $itemData) {
                if (isset($itemData['id']) && $itemData['id']) {
                    // Update existing item
                    $item = PurchaseOrderItem::find($itemData['id']);
                    $item->update([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'total' => $itemData['total'],
                    ]);
                    $updatedItemIds[] = $item->id;
                } else {
                    // Create new item
                    $item = PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'total' => $itemData['total'],
                    ]);
                    $updatedItemIds[] = $item->id;
                }
            }

            // Delete items that were removed
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            PurchaseOrderItem::whereIn('id', $itemsToDelete)->delete();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($purchaseOrder)
                ->withProperties([
                    'old' => $oldValues,
                    'new' => [
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'order_date' => $purchaseOrder->order_date,
                        'expected_delivery_date' => $purchaseOrder->expected_delivery_date,
                        'total_amount' => $purchaseOrder->total_amount,
                        'remarks' => $purchaseOrder->remarks,
                    ],
                ])
                ->log('updated');

            DB::commit();

            return redirect()->route('purchase-orders.index')
                ->with('success', 'ক্রয় অর্ডার সফলভাবে আপডেট করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Confirm the purchase order.
     */
    public function confirm(PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order can be confirmed
        if (!$purchaseOrder->canBeConfirmed()) {
            return back()->with('error', 'এই ক্রয় অর্ডার নিশ্চিত করা যাবে না।');
        }

        $purchaseOrder->status = 'confirmed';
        $purchaseOrder->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($purchaseOrder)
            ->withProperties([
                'reference_number' => $purchaseOrder->reference_number,
                'supplier' => Contact::find($purchaseOrder->supplier_id)->name,
            ])
            ->log('confirmed');

        return back()->with('success', 'ক্রয় অর্ডার সফলভাবে নিশ্চিত করা হয়েছে।');
    }

    /**
     * Receive the purchase order.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order can be received
        if (!$purchaseOrder->canBeReceived()) {
            return back()->with('error', 'এই ক্রয় অর্ডার গ্রহণ করা যাবে না।');
        }

        // Validate warehouse selection
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        // Begin transaction
        DB::beginTransaction();

        try {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

            // Generate a reference number for the stock movement
            $companySettings = \App\Models\CompanySetting::getDefault();
            $prefix = 'PO-RCV-';

            // Generate a new reference number
            $lastMovement = StockMovement::where('type', 'purchase')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = $lastMovement ? intval(substr($lastMovement->reference_number, strlen($prefix))) + 1 : 1;
            $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // Process each purchase order item
            foreach ($purchaseOrder->items as $item) {
                $product = Product::findOrFail($item->product_id);

                // Get or create stock balance
                $stockBalance = StockBalance::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => 0, 'average_cost' => $product->purchase_price]
                );

                // Update average cost
                $currentValue = $stockBalance->quantity * $stockBalance->average_cost;
                $newValue = $item->quantity * $item->unit_price;
                $newTotalQuantity = $stockBalance->quantity + $item->quantity;

                if ($newTotalQuantity > 0) {
                    $newAverageCost = ($currentValue + $newValue) / $newTotalQuantity;
                    $stockBalance->average_cost = $newAverageCost;
                }

                // Update stock balance
                $stockBalance->quantity += $item->quantity;
                $stockBalance->save();

                // Create stock movement
                StockMovement::create([
                    'reference_number' => $referenceNumber . '-' . $item->id,
                    'type' => 'purchase',
                    'transaction_date' => now(),
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'remarks' => 'ক্রয় অর্ডার গ্রহণ: ' . $purchaseOrder->reference_number,
                    'created_by' => Auth::id(),
                ]);
            }

            // Update purchase order status
            $purchaseOrder->status = 'received';
            $purchaseOrder->save();

            // Log the activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($purchaseOrder)
                ->withProperties([
                    'reference_number' => $purchaseOrder->reference_number,
                    'supplier' => Contact::find($purchaseOrder->supplier_id)->name,
                    'warehouse' => $warehouse->name,
                ])
                ->log('received');

            DB::commit();

            return back()->with('success', 'ক্রয় অর্ডার সফলভাবে গ্রহণ করা হয়েছে।');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'একটি ত্রুটি ঘটেছে: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancel the purchase order.
     */
    public function cancel(PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order can be cancelled
        if (!$purchaseOrder->canBeCancelled()) {
            return back()->with('error', 'এই ক্রয় অর্ডার বাতিল করা যাবে না।');
        }

        $purchaseOrder->status = 'cancelled';
        $purchaseOrder->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($purchaseOrder)
            ->withProperties([
                'reference_number' => $purchaseOrder->reference_number,
                'supplier' => Contact::find($purchaseOrder->supplier_id)->name,
            ])
            ->log('cancelled');

        return back()->with('success', 'ক্রয় অর্ডার সফলভাবে বাতিল করা হয়েছে।');
    }

    /**
     * Show the form for creating an invoice from the purchase order.
     */
    public function createInvoice(PurchaseOrder $purchaseOrder)
    {
        // Check if purchase order can be invoiced
        if (!$purchaseOrder->canBeInvoiced()) {
            return back()->with('error', 'এই ক্রয় অর্ডার থেকে ইনভয়েস তৈরি করা যাবে না।');
        }

        $purchaseOrder->load(['supplier', 'items.product']);

        // Get accounts for dropdown
        $accounts = \App\Models\ChartOfAccount::where('is_active', true)
            ->whereHas('category', function ($query) {
                $query->whereIn('type', ['Asset', 'Expense']);
            })
            ->get();

        // Get tax settings
        $taxSettings = \App\Models\TaxSetting::where('is_active', true)->get();

        // Get company settings for reference number prefix
        $companySettings = \App\Models\CompanySetting::getDefault();
        $prefix = $companySettings->invoice_prefix;

        // Generate a new reference number
        $lastInvoice = \App\Models\Invoice::orderBy('id', 'desc')->first();
        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->reference_number, strlen($prefix))) + 1 : 1;
        $referenceNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return Inertia::render('Purchases/Invoices/Create', [
            'purchaseOrder' => $purchaseOrder,
            'accounts' => $accounts,
            'taxSettings' => $taxSettings,
            'referenceNumber' => $referenceNumber,
            'today' => Carbon::today()->format('Y-m-d'),
            'dueDate' => Carbon::today()->addDays(30)->format('Y-m-d'),
        ]);
    }
}
