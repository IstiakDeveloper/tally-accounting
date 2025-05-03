<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'tax_amount',
        'total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the sales order that the item belongs to.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the product that the item belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the subtotal before tax and discount.
     */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Calculate the net amount (subtotal - discount).
     */
    public function getNetAmountAttribute()
    {
        return $this->subtotal - $this->discount;
    }

    /**
     * Calculate the profit margin.
     */
    public function getProfitMarginAttribute()
    {
        $product = $this->product;

        if (!$product) {
            return 0;
        }

        $costPrice = $product->purchase_price * $this->quantity;
        $sellingPrice = $this->net_amount;

        if ($costPrice <= 0) {
            return 0;
        }

        return (($sellingPrice - $costPrice) / $costPrice) * 100;
    }

    /**
     * Format the total with currency symbol.
     */
    public function getFormattedTotalAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total, 2, '.', ',');
    }
}
