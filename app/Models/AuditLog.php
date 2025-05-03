<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'action',
        'module',
        'reference_id',
        'old_values',
        'new_values',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope a query to filter by reference id.
     */
    public function scopeByReference($query, $referenceId)
    {
        return $query->where('reference_id', $referenceId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Get the action display text.
     */
    public function getActionDisplayAttribute()
    {
        $actions = [
            'create' => 'তৈরি',
            'update' => 'হালনাগাদ',
            'delete' => 'মুছে ফেলা',
            'login' => 'লগইন',
            'logout' => 'লগআউট',
            'post' => 'পোস্ট',
            'cancel' => 'বাতিল',
            'toggle_status' => 'স্ট্যাটাস পরিবর্তন',
        ];

        return $actions[$this->action] ?? $this->action;
    }

    /**
     * Get the module display text.
     */
    public function getModuleDisplayAttribute()
    {
        $modules = [
            'users' => 'ব্যবহারকারী',
            'accounts' => 'হিসাব',
            'journal_entries' => 'জার্নাল এন্ট্রি',
            'financial_years' => 'অর্থবছর',
            'products' => 'পণ্য',
            'purchases' => 'ক্রয়',
            'sales' => 'বিক্রয়',
            'invoices' => 'ইনভয়েস',
            'payments' => 'পেমেন্ট',
            'employees' => 'কর্মচারী',
            'departments' => 'বিভাগ',
            'salary_slips' => 'বেতন স্লিপ',
            'leaves' => 'ছুটি',
        ];

        return $modules[$this->module] ?? $this->module;
    }
}
