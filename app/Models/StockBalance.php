<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'average_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'average_cost' => 'decimal:2',
    ];

    /**
     * Get the product that the balance belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that the balance belongs to.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the total value of the stock.
     */
    public function getTotalValueAttribute()
    {
        return $this->quantity * $this->average_cost;
    }

    /**
     * Scope a query to filter by positive stock quantity.
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope a query to filter by low stock level.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= products.reorder_level AND quantity > 0')
            ->join('products', 'stock_balances.product_id', '=', 'products.id');
    }

    /**
     * Scope a query to filter by out of stock.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }
}
