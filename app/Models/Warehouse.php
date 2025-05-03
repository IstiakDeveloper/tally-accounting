<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'contact_person',
        'contact_number',
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
     * Get the stock movements for the warehouse.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the stock balances for the warehouse.
     */
    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * Scope a query to only include active warehouses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
