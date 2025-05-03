<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAllowance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'allowance_type_id',
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
     * Get the employee that the allowance belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the allowance type that the allowance belongs to.
     */
    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
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
     * Scope a query to filter by employee.
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to filter by allowance type.
     */
    public function scopeByAllowanceType($query, $allowanceTypeId)
    {
        return $query->where('allowance_type_id', $allowanceTypeId);
    }

    /**
     * Scope a query to filter by taxable status.
     */
    public function scopeTaxable($query, $isTaxable = true)
    {
        return $query->whereHas('allowanceType', function ($q) use ($isTaxable) {
            $q->where('is_taxable', $isTaxable);
        });
    }
}
