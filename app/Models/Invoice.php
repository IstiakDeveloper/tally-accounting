<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'type',
        'contact_id',
        'sales_order_id',
        'purchase_order_id',
        'invoice_date',
        'due_date',
        'sub_total',
        'discount',
        'tax_amount',
        'total',
        'amount_paid',
        'status',
        'journal_entry_id',
        'remarks',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Get the contact that the invoice belongs to.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the sales order associated with the invoice.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the purchase order associated with the invoice.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the journal entry associated with the invoice.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the user who created the invoice.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the remaining amount to be paid.
     */
    public function getRemainingAmountAttribute()
    {
        return $this->total - $this->amount_paid;
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->status === 'paid' || $this->remaining_amount <= 0;
    }

    /**
     * Check if the invoice is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid' || ($this->amount_paid > 0 && $this->remaining_amount > 0);
    }

    /**
     * Check if the invoice is unpaid.
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid' || $this->amount_paid <= 0;
    }

    /**
     * Check if the invoice is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return !$this->isFullyPaid() && !$this->isCancelled() && $this->due_date < now();
    }

    /**
     * Get the days overdue.
     */
    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return $this->due_date->diffInDays(now());
    }

    /**
     * Get the formatted total amount with currency symbol.
     */
    public function getFormattedTotalAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total, 2, '.', ',');
    }

    /**
     * Get the formatted amount paid with currency symbol.
     */
    public function getFormattedAmountPaidAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->amount_paid, 2, '.', ',');
    }

    /**
     * Get the formatted remaining amount with currency symbol.
     */
    public function getFormattedRemainingAmountAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->remaining_amount, 2, '.', ',');
    }

    /**
     * Scope a query to only include sales invoices.
     */
    public function scopeSales($query)
    {
        return $query->where('type', 'sales');
    }

    /**
     * Scope a query to only include purchase invoices.
     */
    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    /**
     * Scope a query to only include unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope a query to only include partially paid invoices.
     */
    public function scopePartiallyPaid($query)
    {
        return $query->where('status', 'partially_paid');
    }

    /**
     * Scope a query to only include paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include cancelled invoices.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled'])
            ->where('due_date', '<', now());
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        return $query;
    }
}
