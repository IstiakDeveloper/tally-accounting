<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'business_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the journal entries for the financial year.
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Activate this financial year and deactivate others.
     */
    public function activate()
    {
        // Start a transaction
        \DB::beginTransaction();

        try {
            // Deactivate all financial years
            self::where('id', '!=', $this->id)->update(['is_active' => false]);

            // Activate this financial year
            $this->is_active = true;
            $this->save();

            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            return false;
        }
    }


    /**
     * Scope a query to only include active financial year.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a date falls within this financial year.
     */
    public function isDateWithin($date)
    {
        $dateToCheck = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $dateToCheck->between($this->start_date, $this->end_date);
    }

    /**
     * Generate financial year name from start and end dates.
     */
    public static function generateName($startDate, $endDate)
    {
        $start = $startDate instanceof \Carbon\Carbon ? $startDate : \Carbon\Carbon::parse($startDate);
        $end = $endDate instanceof \Carbon\Carbon ? $endDate : \Carbon\Carbon::parse($endDate);

        return $start->format('Y') . '-' . $end->format('Y');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // getActive স্ট্যাটিক মেথড পরিবর্তন করুন
    public static function getActive()
    {
        return self::where('business_id', session('active_business_id'))
            ->where('is_active', true)
            ->first();
    }

}
