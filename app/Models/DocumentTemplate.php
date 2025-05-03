<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
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
        'content',
        'is_default',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created the template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the type display text.
     */
    public function getTypeDisplayAttribute()
    {
        $types = [
            'invoice' => 'ইনভয়েস',
            'purchase_order' => 'ক্রয় অর্ডার',
            'sales_order' => 'বিক্রয় অর্ডার',
            'receipt' => 'রসিদ',
            'payment_voucher' => 'পেমেন্ট ভাউচার',
            'salary_slip' => 'বেতন স্লিপ',
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Render the template with the given data.
     */
    public function render($data = [])
    {
        $content = $this->content;

        // Replace placeholders with actual data
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
