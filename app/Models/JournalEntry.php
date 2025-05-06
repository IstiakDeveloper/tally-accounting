<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'financial_year_id',
        'business_id',
        'entry_date',
        'narration',
        'status',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_date' => 'date',
    ];

    /**
     * Get the financial year that the journal entry belongs to.
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    /**
     * Get the user who created the journal entry.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the journal items for the journal entry.
     */
    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    /**
     * Get total debit amount.
     */
    public function getTotalDebitAttribute()
    {
        return $this->items()->where('type', 'debit')->sum('amount');
    }

    /**
     * Get total credit amount.
     */
    public function getTotalCreditAttribute()
    {
        return $this->items()->where('type', 'credit')->sum('amount');
    }

    /**
     * Check if the journal entry is balanced.
     */
    public function isBalanced(): bool
    {
        return $this->total_debit == $this->total_credit;
    }

    /**
     * Post the journal entry.
     */
    public function post()
    {
        if ($this->isBalanced() && $this->status === 'draft') {
            $this->status = 'posted';
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Cancel the journal entry.
     */
    public function cancel()
    {
        if ($this->status !== 'cancelled') {
            $this->status = 'cancelled';
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Scope a query to only include draft journal entries.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include posted journal entries.
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope a query to only include cancelled journal entries.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
