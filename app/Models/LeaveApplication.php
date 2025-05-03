<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'approved_by',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
    ];

    /**
     * Get the employee that the leave application belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave type that the leave application belongs to.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the user who approved the leave application.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the status display text.
     */
    public function getStatusDisplayAttribute()
    {
        $statuses = [
            'pending' => 'অপেক্ষমান',
            'approved' => 'অনুমোদিত',
            'rejected' => 'প্রত্যাখ্যাত',
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Check if the leave application is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the leave application is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the leave application is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to filter by leave type.
     */
    public function scopeByLeaveType($query, $leaveTypeId)
    {
        return $query->where('leave_type_id', $leaveTypeId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('end_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope a query to filter current leaves.
     */
    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('status', 'approved');
    }

    /**
     * Scope a query to filter upcoming leaves.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now())
            ->where('status', 'approved');
    }

    /**
     * Scope a query to filter past leaves.
     */
    public function scopePast($query)
    {
        return $query->where('end_date', '<', now())
            ->where('status', 'approved');
    }
}
