<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'account_receivable_id',
        'account_payable_id',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created the contact.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the account receivable associated with the contact.
     */
    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_receivable_id');
    }

    /**
     * Get the account payable associated with the contact.
     */
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_payable_id');
    }

    /**
     * Get the purchase orders for the contact.
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    /**
     * Get the sales orders for the contact.
     */
    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    /**
     * Get the invoices for the contact.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope a query to only include active contacts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include customers.
     */
    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer')->orWhere('type', 'both');
    }

    /**
     * Scope a query to only include suppliers.
     */
    public function scopeSuppliers($query)
    {
        return $query->where('type', 'supplier')->orWhere('type', 'both');
    }

    /**
     * Get the total outstanding receivable amount.
     */
    public function getOutstandingReceivableAttribute()
    {
        return $this->invoices()
            ->where('type', 'sales')
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum(\DB::raw('total - amount_paid'));
    }

    /**
     * Get the total outstanding payable amount.
     */
    public function getOutstandingPayableAttribute()
    {
        return $this->invoices()
            ->where('type', 'purchase')
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum(\DB::raw('total - amount_paid'));
    }
}
