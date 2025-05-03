<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
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
        'value',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee deductions for this type.
     */
    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * Calculate deduction amount based on basic salary.
     */
    public function calculateAmount($basicSalary)
    {
        if ($this->type === 'fixed') {
            return $this->value;
        } else {
            // Percentage
            return ($basicSalary * $this->value) / 100;
        }
    }

    /**
     * Get the formatted value with percentage sign for percentage type.
     */
    public function getFormattedValueAttribute()
    {
        if ($this->type === 'fixed') {
            $companySetting = CompanySetting::first();
            $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

            return $currencySymbol . ' ' . number_format($this->value, 2, '.', ',');
        } else {
            return number_format($this->value, 2) . '%';
        }
    }

    /**
     * Get the type display text.
     */
    public function getTypeDisplayAttribute()
    {
        $types = [
            'fixed' => 'নির্দিষ্ট',
            'percentage' => 'শতকরা',
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Scope a query to only include active deduction types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by deduction type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
