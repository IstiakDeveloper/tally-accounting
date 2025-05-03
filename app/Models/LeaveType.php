<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'days_allowed_per_year',
        'is_paid',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'days_allowed_per_year' => 'integer',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the leave applications for this type.
     */
    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class);
    }

    /**
     * Get the paid status display text.
     */
    public function getPaidStatusDisplayAttribute()
    {
        return $this->is_paid ? 'বেতনসহ' : 'বেতন ছাড়া';
    }

    /**
     * Scope a query to only include active leave types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by paid status.
     */
    public function scopeByPaidStatus($query, $isPaid = true)
    {
        return $query->where('is_paid', $isPaid);
    }
}
