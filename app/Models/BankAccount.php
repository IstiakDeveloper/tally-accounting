<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_name',
        'account_number',
        'bank_name',
        'branch_name',
        'swift_code',
        'routing_number',
        'address',
        'contact_person',
        'contact_number',
        'account_id',
        'business_id',
        'is_active',
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
     * Get the account that the bank account belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Get the balance of the account.
     */
    public function getBalanceAttribute()
    {
        return $this->account ? $this->account->getBalance() : 0;
    }

    /**
     * Get the formatted balance with currency symbol.
     */
    public function getFormattedBalanceAttribute()
    {
        $companySetting = CompanySetting::first();
        $currencySymbol = $companySetting ? $companySetting->currency_symbol : '৳';

        return $currencySymbol . ' ' . number_format($this->balance, 2, '.', ',');
    }

    /**
     * Scope a query to only include active bank accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by bank name.
     */
    public function scopeByBank($query, $bankName)
    {
        return $query->where('bank_name', 'like', "%{$bankName}%");
    }


    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

}
