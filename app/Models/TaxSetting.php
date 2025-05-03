<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'rate',
        'description',
        'is_active',
        'account_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the account that the tax setting belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Calculate tax amount from the given amount.
     */
    public function calculateTax($amount)
    {
        return ($amount * $this->rate) / 100;
    }

    /**
     * Scope a query to only include active tax settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Format the rate with percentage sign.
     */
    public function getFormattedRateAttribute()
    {
        return number_format($this->rate, 2) . '%';
    }
}
