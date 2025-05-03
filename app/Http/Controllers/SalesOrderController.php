<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    /**
     * Display a listing of the sales orders.
     */
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        // Search by reference number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $salesOrders = $query->paginate(10)->withQueryString();
        $customers = Contact::where('type', 'customer')
            ->orWhere('type', 'both')
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/Index', [
            'salesOrders' => $salesOrders,
            'customers' => $customers,
            'filters' => $request->only(['status', 'customer_id', 'from_date', 'to_date', 'search']),
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for creating a new sales order.
     */
    public function create()
    {
        $customers = Contact::where(function ($query) {
                $query->where('type', 'customer')
                    ->orWhere('type', 'both');
            })
            ->where('is_active', true)
            ->get();

        $products = Product::with('category')
            ->where('is_active', true)
            ->get();

        $warehouses = Warehouse::where('is_active', true)->get();

        $companySetting = CompanySetting::first();
        $salesPrefix = $companySetting ? $companySetting->sales_prefix : 'SO-';

        // Generate reference number
        $lastOrder = SalesOrder::latest()->first();
        $referenceNumber = $salesPrefix . '00001';

        if ($lastOrder) {
            $lastReferenceNumber = $lastOrder->reference_number;
            $lastNumber = (int) substr($lastReferenceNumber, strlen($salesPrefix));
            $referenceNumber = $salesPrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        }

        return Inertia::render('SalesOrders/Create', [
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'referenceNumber' => $referenceNumber,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Store a newly created sales order in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string|unique:sales_orders',
            'customer_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Create sales order
            $salesOrder = SalesOrder::create([
                'reference_number' => $request->reference_number,
                'customer_id' => $request->customer_id,
                'order_date' => $request->order_date,
                'delivery_date' => $request->delivery_date,
                'status' => 'draft',
                'total_amount' => $request->total_amount,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            // Create sales order items
            foreach ($request->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'total' => $item['total'],
                ]);
            }

            DB::commit();

            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('success', 'Sales order created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified sales order.
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'customer',
            'items.product',
            'createdBy',
            'invoice'
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/Show', [
            'salesOrder' => $salesOrder,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Show the form for editing the specified sales order.
     */
    public function edit(SalesOrder $salesOrder)
    {
        if (!$salesOrder->isEditable()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be edited.');
        }

        $salesOrder->load(['customer', 'items.product']);

        $customers = Contact::where(function ($query) {
                $query->where('type', 'customer')
                    ->orWhere('type', 'both');
            })
            ->where('is_active', true)
            ->get();

        $products = Product::with('category')
            ->where('is_active', true)
            ->get();

        $warehouses = Warehouse::where('is_active', true)->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/Edit', [
            'salesOrder' => $salesOrder,
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Update the specified sales order in storage.
     */
    public function update(Request $request, SalesOrder $salesOrder)
    {
        if (!$salesOrder->isEditable()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be updated.');
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Update sales order
            $salesOrder->update([
                'customer_id' => $request->customer_id,
                'order_date' => $request->order_date,
                'delivery_date' => $request->delivery_date,
                'total_amount' => $request->total_amount,
                'remarks' => $request->remarks,
            ]);

            // Delete existing items
            $salesOrder->items()->delete();

            // Create new sales order items
            foreach ($request->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'total' => $item['total'],
                ]);
            }

            DB::commit();

            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('success', 'Sales order updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Confirm the sales order.
     */
    public function confirm(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeConfirmed()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be confirmed.');
        }

        $salesOrder->update([
            'status' => 'confirmed',
        ]);

        return redirect()->route('sales-orders.show', $salesOrder->id)
            ->with('success', 'Sales order confirmed successfully.');
    }

    /**
     * Mark the sales order as delivered.
     */
    public function deliver(Request $request, SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeDelivered()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be marked as delivered.');
        }

        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Update sales order status
            $salesOrder->update([
                'status' => 'delivered',
            ]);

            // Create stock movements for each item
            $companySetting = CompanySetting::first();
            $referencePrefix = $companySetting ? $companySetting->receipt_prefix : 'REC-';

            $lastMovement = StockMovement::latest()->first();
            $referenceNumber = $referencePrefix . '00001';

            if ($lastMovement) {
                $lastReferenceNumber = $lastMovement->reference_number;
                $lastNumber = (int) substr($lastReferenceNumber, strlen($referencePrefix));
                $referenceNumber = $referencePrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            }

            foreach ($salesOrder->items as $item) {
                StockMovement::create([
                    'reference_number' => $referenceNumber,
                    'type' => 'sale',
                    'transaction_date' => now(),
                    'warehouse_id' => $request->warehouse_id,
                    'product_id' => $item->product_id,
                    'quantity' => -$item->quantity, // Negative quantity for sales
                    'unit_price' => $item->unit_price,
                    'remarks' => 'Stock movement for sales order: ' . $salesOrder->reference_number,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('success', 'Sales order marked as delivered successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Cancel the sales order.
     */
    public function cancel(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeCancelled()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be cancelled.');
        }

        $salesOrder->update([
            'status' => 'cancelled',
        ]);

        return redirect()->route('sales-orders.show', $salesOrder->id)
            ->with('success', 'Sales order cancelled successfully.');
    }

    /**
     * Create an invoice from the sales order.
     */
    public function createInvoice(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeInvoiced()) {
            return redirect()->route('sales-orders.show', $salesOrder->id)
                ->with('error', 'This sales order cannot be invoiced.');
        }

        // Check if an invoice already exists
        if ($salesOrder->invoice) {
            return redirect()->route('invoices.show', $salesOrder->invoice->id)
                ->with('info', 'An invoice already exists for this sales order.');
        }

        // Generate invoice reference number
        $companySetting = CompanySetting::first();
        $invoicePrefix = $companySetting ? $companySetting->invoice_prefix : 'INV-';

        $lastInvoice = Invoice::latest()->first();
        $referenceNumber = $invoicePrefix . '00001';

        if ($lastInvoice) {
            $lastReferenceNumber = $lastInvoice->reference_number;
            $lastNumber = (int) substr($lastReferenceNumber, strlen($invoicePrefix));
            $referenceNumber = $invoicePrefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        }

        return redirect()->route('invoices.create', [
            'sales_order_id' => $salesOrder->id,
            'reference_number' => $referenceNumber,
        ]);
    }

    /**
     * Print the sales order.
     */
    public function print(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'customer',
            'items.product',
            'createdBy',
        ]);

        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/Print', [
            'salesOrder' => $salesOrder,
            'companySetting' => $companySetting,
        ]);
    }

    /**
     * Export sales orders to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = SalesOrder::with(['customer', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        $salesOrders = $query->get();
        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/ExportPdf', [
            'salesOrders' => $salesOrders,
            'companySetting' => $companySetting,
            'filters' => $request->only(['status', 'customer_id', 'from_date', 'to_date']),
        ]);
    }

    /**
     * Generate sales report.
     */
    public function report(Request $request)
    {
        $query = SalesOrder::with(['customer', 'items.product'])
            ->whereIn('status', ['confirmed', 'delivered'])
            ->orderBy('order_date', 'desc');

        // Filter by customer
        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        $salesOrders = $query->get();

        // Calculate total sales
        $totalSales = $salesOrders->sum('total_amount');

        // Calculate sales by customer
        $salesByCustomer = $salesOrders->groupBy('customer_id')
            ->map(function ($orders) {
                $firstOrder = $orders->first();
                return [
                    'customer_name' => $firstOrder->customer->name,
                    'total_amount' => $orders->sum('total_amount'),
                    'order_count' => $orders->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        // Calculate sales by product
        $salesByProduct = [];
        foreach ($salesOrders as $order) {
            foreach ($order->items as $item) {
                $productId = $item->product_id;
                $productName = $item->product->name;

                if (!isset($salesByProduct[$productId])) {
                    $salesByProduct[$productId] = [
                        'product_name' => $productName,
                        'quantity' => 0,
                        'total_amount' => 0,
                    ];
                }

                $salesByProduct[$productId]['quantity'] += $item->quantity;
                $salesByProduct[$productId]['total_amount'] += $item->total;
            }
        }

        $salesByProduct = collect($salesByProduct)->sortByDesc('total_amount')->values();

        // Calculate sales by month if date range spans multiple months
        $salesByMonth = [];
        if ($request->has('from_date') && $request->has('to_date')) {
            $fromDate = \Carbon\Carbon::parse($request->from_date);
            $toDate = \Carbon\Carbon::parse($request->to_date);

            if ($fromDate->format('Y-m') != $toDate->format('Y-m')) {
                $salesByMonth = $salesOrders->groupBy(function ($order) {
                    return \Carbon\Carbon::parse($order->order_date)->format('Y-m');
                })
                ->map(function ($orders, $month) {
                    return [
                        'month' => \Carbon\Carbon::parse($month.'-01')->format('F Y'),
                        'total_amount' => $orders->sum('total_amount'),
                        'order_count' => $orders->count(),
                    ];
                })
                ->sortBy('month')
                ->values();
            }
        }

        $customers = Contact::where(function ($query) {
                $query->where('type', 'customer')
                    ->orWhere('type', 'both');
            })
            ->where('is_active', true)
            ->get();

        $companySetting = CompanySetting::first();

        return Inertia::render('SalesOrders/Report', [
            'salesOrders' => $salesOrders,
            'customers' => $customers,
            'filters' => $request->only(['customer_id', 'from_date', 'to_date']),
            'totalSales' => $totalSales,
            'salesByCustomer' => $salesByCustomer,
            'salesByProduct' => $salesByProduct,
            'salesByMonth' => $salesByMonth,
            'companySetting' => $companySetting,
        ]);
    }
}
