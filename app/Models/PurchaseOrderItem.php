<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
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
     * Get the purchase order that the item belongs to.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * Format the total with currency symbol.
     */
    public function getFormattedTotalAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total, 2, '.', ',');
    }
}
