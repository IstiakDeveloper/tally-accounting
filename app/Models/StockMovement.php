<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_number',
        'type',
        'transaction_date',
        'warehouse_id',
        'product_id',
        'quantity',
        'unit_price',
        'remarks',
        'related_journal_entry_id',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transaction_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    /**
     * Get the warehouse that the movement belongs to.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the product that the movement belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created the movement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the related journal entry if any.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'related_journal_entry_id');
    }

    /**
     * Get the total value of the movement.
     */
    public function getTotalValueAttribute()
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Scope a query to filter by movement type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        return $query;
    }
}
