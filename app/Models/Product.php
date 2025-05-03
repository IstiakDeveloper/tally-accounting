<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'unit',
        'purchase_price',
        'selling_price',
        'reorder_level',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that the product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get the user who created the product.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the stock balances for the product.
     */
    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * Get the total stock quantity of the product.
     */
    public function getTotalStockAttribute()
    {
        return $this->stockBalances()->sum('quantity');
    }

    /**
     * Get the stock level status of the product.
     */
    public function getStockStatusAttribute()
    {
        $totalStock = $this->total_stock;

        if ($totalStock <= 0) {
            return 'out_of_stock';
        } elseif ($totalStock <= $this->reorder_level) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    /**
     * Check if the product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->total_stock > 0;
    }

    /**
     * Check if the product is low in stock.
     */
    public function isLowInStock(): bool
    {
        return $this->total_stock <= $this->reorder_level && $this->total_stock > 0;
    }

    /**
     * Check if the product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->total_stock <= 0;
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter products by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to filter products that are low in stock.
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('stockBalances', function ($q) {
            $q->whereRaw('quantity <= products.reorder_level AND quantity > 0');
        });
    }

    /**
     * Scope a query to filter products that are out of stock.
     */
    public function scopeOutOfStock($query)
    {
        return $query->whereDoesntHave('stockBalances', function ($q) {
            $q->where('quantity', '>', 0);
        });
    }
}
