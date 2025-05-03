<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'user_id',
        'department_id',
        'designation_id',
        'joining_date',
        'employment_status',
        'contract_end_date',
        'basic_salary',
        'salary_account_id',
        'bank_name',
        'bank_account_number',
        'tax_identification_number',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joining_date' => 'date',
        'contract_end_date' => 'date',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user associated with the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department that the employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation that the employee has.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the salary account that the employee has.
     */
    public function salaryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'salary_account_id');
    }

    /**
     * Get the allowances for the employee.
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * Get the deductions for the employee.
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * Get the salary slips for the employee.
     */
    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    /**
     * Get the leave applications for the employee.
     */
    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    /**
     * Get the full name from the associated user.
     */
    public function getFullNameAttribute()
    {
        return $this->user ? $this->user->name : '';
    }

    /**
     * Get the email from the associated user.
     */
    public function getEmailAttribute()
    {
        return $this->user ? $this->user->email : '';
    }

    /**
     * Get the phone from the associated user.
     */
    public function getPhoneAttribute()
    {
        return $this->user ? $this->user->phone : '';
    }

    /**
     * Calculate the total allowances.
     */
    public function getTotalAllowancesAttribute()
    {
        return $this->allowances()->sum('amount');
    }

    /**
     * Calculate the total deductions.
     */
    public function getTotalDeductionsAttribute()
    {
        return $this->deductions()->sum('amount');
    }

    /**
     * Calculate the net salary.
     */
    public function getNetSalaryAttribute()
    {
        return $this->basic_salary + $this->total_allowances - $this->total_deductions;
    }

    /**
     * Get employment status display text.
     */
    public function getEmploymentStatusDisplayAttribute()
    {
        $statuses = [
            'permanent' => 'স্থায়ী',
            'probation' => 'প্রবেশন',
            'contract' => 'চুক্তিভিত্তিক',
            'part-time' => 'খণ্ডকালীন',
        ];

        return $statuses[$this->employment_status] ?? $this->employment_status;
    }

    /**
     * Get the service duration.
     */
    public function getServiceDurationAttribute()
    {
        return $this->joining_date->diffForHumans(null, true);
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by department.
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope a query to filter by designation.
     */
    public function scopeByDesignation($query, $designationId)
    {
        return $query->where('designation_id', $designationId);
    }

    /**
     * Scope a query to filter by employment status.
     */
    public function scopeByEmploymentStatus($query, $status)
    {
        return $query->where('employment_status', $status);
    }
}
