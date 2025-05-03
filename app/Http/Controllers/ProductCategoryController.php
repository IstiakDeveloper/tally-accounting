<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductCategoryController extends Controller
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

        // Query categories with filters
        $query = ProductCategory::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        // Get paginated results with product count
        $categories = $query->withCount('products')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Inventory/ProductCategories/Index', [
            'categories' => $categories,
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
        return Inertia::render('Inventory/ProductCategories/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = ProductCategory::create($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($category)
            ->withProperties([
                'name' => $category->name,
            ])
            ->log('created');

        return redirect()->route('product-categories.index')
            ->with('success', 'পণ্য ক্যাটাগরি সফলভাবে তৈরি করা হয়েছে।');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductCategory $productCategory)
    {
        return Inertia::render('Inventory/ProductCategories/Edit', [
            'category' => $productCategory,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit log
        $oldValues = [
            'name' => $productCategory->name,
            'description' => $productCategory->description,
            'is_active' => $productCategory->is_active,
        ];

        $productCategory->update($validated);

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($productCategory)
            ->withProperties([
                'old' => $oldValues,
                'new' => [
                    'name' => $productCategory->name,
                    'description' => $productCategory->description,
                    'is_active' => $productCategory->is_active,
                ],
            ])
            ->log('updated');

        return redirect()->route('product-categories.index')
            ->with('success', 'পণ্য ক্যাটাগরি সফলভাবে আপডেট করা হয়েছে।');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductCategory $productCategory)
    {
        // Check if the category has any products
        if ($productCategory->products()->exists()) {
            return back()->with('error', 'এই ক্যাটাগরির অধীনে পণ্য আছে, তাই এটি মুছে ফেলা যাবে না।');
        }

        // Store category details for audit log
        $categoryDetails = [
            'id' => $productCategory->id,
            'name' => $productCategory->name,
        ];

        $productCategory->delete();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->withProperties($categoryDetails)
            ->log('deleted product category');

        return redirect()->route('product-categories.index')
            ->with('success', 'পণ্য ক্যাটাগরি সফলভাবে মুছে ফেলা হয়েছে।');
    }

    /**
     * Toggle the active status of the category.
     */
    public function toggleStatus(ProductCategory $productCategory)
    {
        $oldStatus = $productCategory->is_active;
        $productCategory->is_active = !$oldStatus;
        $productCategory->save();

        // Log the activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($productCategory)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $productCategory->is_active,
            ])
            ->log('toggled status');

        $statusText = $productCategory->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়';

        return back()->with('success', "পণ্য ক্যাটাগরি সফলভাবে {$statusText} করা হয়েছে।");
    }
}
