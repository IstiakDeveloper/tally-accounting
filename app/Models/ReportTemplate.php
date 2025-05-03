<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
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
        'description',
        'structure',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'structure' => 'json',
        'is_active' => 'boolean',
    ];

    /**
     * Get the saved reports for the template.
     */
    public function savedReports(): HasMany
    {
        return $this->hasMany(SavedReport::class);
    }

    /**
     * Get the type display text.
     */
    public function getTypeDisplayAttribute()
    {
        $types = [
            'financial' => 'আর্থিক',
            'inventory' => 'ইনভেন্টরি',
            'sales' => 'বিক্রয়',
            'purchase' => 'ক্রয়',
            'payroll' => 'পেরোল',
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Scope a query to only include active report templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

}
