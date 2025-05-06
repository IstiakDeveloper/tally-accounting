<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'legal_name', 'tax_identification_number', 'registration_number',
        'address', 'city', 'state', 'postal_code', 'country', 'phone', 'email', 'website',
        'logo', 'is_active', 'created_by'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    public function accountCategories()
    {
        return $this->hasMany(AccountCategory::class);
    }

    public function chartOfAccounts()
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function financialYears()
    {
        return $this->hasMany(FinancialYear::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
