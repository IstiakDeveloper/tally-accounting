<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'type',
        'amount',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the journal entry that the item belongs to.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account that the item belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Scope a query to only include debit items.
     */
    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope a query to only include credit items.
     */
    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Format the amount with currency symbol.
     */
    public function getFormattedAmountAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->amount, 2, '.', ',');
    }
}
