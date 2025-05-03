<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'tax_identification_number',
        'registration_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'logo',
        'currency',
        'currency_symbol',
        'date_format',
        'time_format',
        'timezone',
        'fiscal_year_start_month',
        'decimal_separator',
        'thousand_separator',
        'invoice_prefix',
        'purchase_prefix',
        'sales_prefix',
        'receipt_prefix',
        'payment_prefix',
        'journal_prefix',
    ];

    /**
     * Get the default company settings.
     */
    public static function getDefault()
    {
        $setting = self::first();

        if (!$setting) {
            // Create default settings if not exists
            $setting = self::create([
                'name' => 'আমার প্রতিষ্ঠান',
                'address' => 'ঢাকা, বাংলাদেশ',
                'country' => 'Bangladesh',
                'phone' => '+880 1XXXXXXXXX',
                'currency' => 'BDT',
                'currency_symbol' => '৳',
                'date_format' => 'd/m/Y',
                'time_format' => 'h:i A',
                'timezone' => 'Asia/Dhaka',
                'fiscal_year_start_month' => 'January',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'invoice_prefix' => 'INV-',
                'purchase_prefix' => 'PO-',
                'sales_prefix' => 'SO-',
                'receipt_prefix' => 'REC-',
                'payment_prefix' => 'PAY-',
                'journal_prefix' => 'JE-',
            ]);
        }

        return $setting;
    }

    /**
     * Get logo URL.
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo && file_exists(public_path('storage/' . $this->logo))) {
            return asset('storage/' . $this->logo);
        }

        return asset('images/default-logo.png');
    }

    /**
     * Format number according to company settings.
     */
    public function formatNumber($number, $decimals = 2)
    {
        return number_format(
            $number,
            $decimals,
            $this->decimal_separator,
            $this->thousand_separator
        );
    }

    /**
     * Format currency according to company settings.
     */
    public function formatCurrency($amount, $decimals = 2)
    {
        return $this->currency_symbol . ' ' . $this->formatNumber($amount, $decimals);
    }

    /**
     * Format date according to company settings.
     */
    public function formatDate($date)
    {
        if (!$date) {
            return null;
        }

        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $carbon->format($this->date_format);
    }
}
