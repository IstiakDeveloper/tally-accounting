<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the default business ID (creating a default business first is required)
        $businessId = DB::table('businesses')->first()->id ?? 1;

        // Get admin user for created_by field
        $userId = DB::table('users')->where('email', 'admin@example.com')->first()->id ?? 1;

        $categories = [
            [
                'business_id' => $businessId,
                'name' => 'Assets',
                'type' => 'Asset',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'name' => 'Liabilities',
                'type' => 'Liability',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'name' => 'Equity',
                'type' => 'Equity',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'name' => 'Revenue',
                'type' => 'Revenue',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'name' => 'Expense',
                'type' => 'Expense',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('account_categories')->insert($categories);
    }
}
