<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySlipDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'salary_slip_id',
        'type',
        'name',
        'amount',
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
     * Get the salary slip that the detail belongs to.
     */
    public function salarySlip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class);
    }

    /**
     * Get the formatted amount with currency symbol.
     */
    public function getFormattedAmountAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->amount, 2, '.', ',');
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to get allowances.
     */
    public function scopeAllowances($query)
    {
        return $query->where('type', 'allowance');
    }

    /**
     * Scope a query to get deductions.
     */
    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }
}
