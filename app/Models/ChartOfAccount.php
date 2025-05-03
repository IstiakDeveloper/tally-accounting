<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartOfAccount extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chart_of_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_code',
        'name',
        'category_id',
        'description',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that the account belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    /**
     * Get the user who created the account.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the journal items associated with the account.
     */
    public function journalItems()
    {
        return $this->hasMany(JournalItem::class, 'account_id');
    }

    /**
     * Get the current balance of the account.
     */
    public function getBalance()
    {
        $debitSum = $this->journalItems()->where('type', 'debit')->sum('amount');
        $creditSum = $this->journalItems()->where('type', 'credit')->sum('amount');

        $category = $this->category;

        // For Asset and Expense accounts: Debit increases, Credit decreases
        if ($category->type === 'Asset' || $category->type === 'Expense') {
            return $debitSum - $creditSum;
        }

        // For Liability, Equity, and Revenue accounts: Credit increases, Debit decreases
        return $creditSum - $debitSum;
    }

    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by account category type.
     */
    public function scopeByType($query, $type)
    {
        return $query->whereHas('category', function ($q) use ($type) {
            $q->where('type', $type);
        });
    }
}
