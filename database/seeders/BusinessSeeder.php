<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin user for created_by field
        $userId = DB::table('users')->where('email', 'admin@example.com')->first()->id ?? 1;

        $business = [
            'name' => 'Default Business',
            'code' => 'DEF001',
            'legal_name' => 'Default Business Ltd.',
            'tax_identification_number' => '123456789',
            'registration_number' => 'REG123456',
            'address' => 'Default Address',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postal_code' => '1000',
            'country' => 'Bangladesh',
            'phone' => '+880123456789',
            'email' => 'info@defaultbusiness.com',
            'website' => 'www.defaultbusiness.com',
            'is_active' => true,
            'created_by' => $userId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Insert business
        $businessId = DB::table('businesses')->insertGetId($business);

        // Attach business to admin user
        DB::table('business_user')->insert([
            'business_id' => $businessId,
            'user_id' => $userId,
            'role' => 'admin',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
