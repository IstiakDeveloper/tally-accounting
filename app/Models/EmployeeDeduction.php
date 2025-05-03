<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeduction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'deduction_type_id',
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
     * Get the employee that the deduction belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the deduction type that the deduction belongs to.
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
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
     * Scope a query to filter by deduction type.
     */
    public function scopeByDeductionType($query, $deductionTypeId)
    {
        return $query->where('deduction_type_id', $deductionTypeId);
    }
}
