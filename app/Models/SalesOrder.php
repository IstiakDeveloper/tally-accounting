<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'customer_id',
        'order_date',
        'delivery_date',
        'status',
        'total_amount',
        'remarks',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the customer that the sales order belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    /**
     * Get the user who created the sales order.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items for the sales order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * Get the invoice associated with the sales order.
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'sales_order_id');
    }

    /**
     * Scope a query to only include draft sales orders.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include confirmed sales orders.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include delivered sales orders.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope a query to only include cancelled sales orders.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if the sales order is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the sales order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status !== 'delivered' && $this->status !== 'cancelled';
    }

    /**
     * Check if the sales order can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the sales order can be delivered.
     */
    public function canBeDelivered(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if the sales order can be invoiced.
     */
    public function canBeInvoiced(): bool
    {
        return $this->status === 'confirmed' || $this->status === 'delivered';
    }

    /**
     * Get the formatted total amount with currency symbol.
     */
    public function getFormattedTotalAmountAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total_amount, 2, '.', ',');
    }
}
