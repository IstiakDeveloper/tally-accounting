<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalarySlip extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'employee_id',
        'month_year',
        'basic_salary',
        'total_allowances',
        'total_deductions',
        'net_salary',
        'payment_status',
        'payment_date',
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
        'month_year' => 'date',
        'basic_salary' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Get the employee that the salary slip belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the journal entry associated with the salary slip.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the user who created the salary slip.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the details for the salary slip.
     */
    public function details(): HasMany
    {
        return $this->hasMany(SalarySlipDetail::class);
    }

    /**
     * Get the allowance details.
     */
    public function allowances()
    {
        return $this->details()->where('type', 'allowance');
    }

    /**
     * Get the deduction details.
     */
    public function deductions()
    {
        return $this->details()->where('type', 'deduction');
    }

    /**
     * Get the month and year in readable format.
     */
    public function getMonthYearFormattedAttribute()
    {
        return $this->month_year->format('F Y');
    }

    /**
     * Get the formatted basic salary with currency symbol.
     */
    public function getFormattedBasicSalaryAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->basic_salary, 2, '.', ',');
    }

    /**
     * Get the formatted total allowances with currency symbol.
     */
    public function getFormattedTotalAllowancesAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total_allowances, 2, '.', ',');
    }

    /**
     * Get the formatted total deductions with currency symbol.
     */
    public function getFormattedTotalDeductionsAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->total_deductions, 2, '.', ',');
    }

    /**
     * Get the formatted net salary with currency symbol.
     */
    public function getFormattedNetSalaryAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->net_salary, 2, '.', ',');
    }

    /**
     * Get the payment status display text.
     */
    public function getPaymentStatusDisplayAttribute()
    {
        $statuses = [
            'unpaid' => 'অপরিশোধিত',
            'paid' => 'পরিশোধিত',
        ];

        return $statuses[$this->payment_status] ?? $this->payment_status;
    }

    /**
     * Scope a query to filter by payment status.
     */
    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope a query to filter by month and year.
     */
    public function scopeByMonthYear($query, $monthYear)
    {
        return $query->whereYear('month_year', $monthYear->year)
            ->whereMonth('month_year', $monthYear->month);
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
