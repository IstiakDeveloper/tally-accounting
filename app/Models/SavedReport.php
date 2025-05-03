<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_template_id',
        'name',
        'parameters',
        'from_date',
        'to_date',
        'data',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'json',
        'data' => 'json',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    /**
     * Get the report template that the saved report belongs to.
     */
    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Get the user who created the saved report.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the date range in readable format.
     */
    public function getDateRangeFormattedAttribute()
    {
        if ($this->from_date && $this->to_date) {
            return $this->from_date->format('d/m/Y') . ' - ' . $this->to_date->format('d/m/Y');
        } elseif ($this->from_date) {
            return 'থেকে ' . $this->from_date->format('d/m/Y');
        } elseif ($this->to_date) {
            return 'পর্যন্ত ' . $this->to_date->format('d/m/Y');
        } else {
            return 'সকল সময়';
        }
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('from_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('to_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope a query to filter by report template.
     */
    public function scopeByReportTemplate($query, $reportTemplateId)
    {
        return $query->where('report_template_id', $reportTemplateId);
    }

    /**
     * Scope a query to filter by created by.
     */
    public function scopeByCreatedBy($query, $createdBy)
    {
        return $query->where('created_by', $createdBy);
    }
}
