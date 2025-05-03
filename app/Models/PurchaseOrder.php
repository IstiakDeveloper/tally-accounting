<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'supplier_id',
        'order_date',
        'expected_delivery_date',
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
        'expected_delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the supplier that the purchase order belongs to.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    /**
     * Get the user who created the purchase order.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items for the purchase order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the invoice associated with the purchase order.
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'purchase_order_id');
    }

    /**
     * Scope a query to only include draft purchase orders.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include confirmed purchase orders.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include received purchase orders.
     */
    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    /**
     * Scope a query to only include cancelled purchase orders.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if the purchase order is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the purchase order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status !== 'received' && $this->status !== 'cancelled';
    }

    /**
     * Check if the purchase order can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the purchase order can be received.
     */
    public function canBeReceived(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if the purchase order can be invoiced.
     */
    public function canBeInvoiced(): bool
    {
        return $this->status === 'confirmed' || $this->status === 'received';
    }
}
