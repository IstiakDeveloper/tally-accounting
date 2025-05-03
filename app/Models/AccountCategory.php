<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCategory extends Model
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
    ];

    /**
     * Get the accounts for the category.
     */
    public function accounts()
    {
        return $this->hasMany(ChartOfAccount::class, 'category_id');
    }

    /**
     * Scope a query to filter by category type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get asset categories.
     */
    public static function assets()
    {
        return self::where('type', 'Asset')->get();
    }

    /**
     * Get liability categories.
     */
    public static function liabilities()
    {
        return self::where('type', 'Liability')->get();
    }

    /**
     * Get equity categories.
     */
    public static function equity()
    {
        return self::where('type', 'Equity')->get();
    }

    /**
     * Get revenue categories.
     */
    public static function revenue()
    {
        return self::where('type', 'Revenue')->get();
    }

    /**
     * Get expense categories.
     */
    public static function expenses()
    {
        return self::where('type', 'Expense')->get();
    }
}
