<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the user ID for seeding
        $userId = DB::table('users')->where('email', 'admin@example.com')->first()->id ?? 1;

        // Asset Accounts
        $assetCategoryId = DB::table('account_categories')->where('type', 'Asset')->first()->id;

        $assetAccounts = [
            [
                'account_code' => '1001',
                'name' => 'নগদ (Cash)',
                'category_id' => $assetCategoryId,
                'description' => 'নগদ অর্থ',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '1002',
                'name' => 'ব্যাংক (Bank)',
                'category_id' => $assetCategoryId,
                'description' => 'ব্যাংক অ্যাকাউন্ট',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '1003',
                'name' => 'পাওনা হিসাব (Accounts Receivable)',
                'category_id' => $assetCategoryId,
                'description' => 'গ্রাহকদের কাছ থেকে পাওনা অর্থ',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '1004',
                'name' => 'মজুদ পণ্য (Inventory)',
                'category_id' => $assetCategoryId,
                'description' => 'মজুদ পণ্যের মূল্য',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '1005',
                'name' => 'বিনিয়োগ (Investments)',
                'category_id' => $assetCategoryId,
                'description' => 'দীর্ঘমেয়াদী বিনিয়োগ',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '1006',
                'name' => 'সরঞ্জাম ও যন্ত্রপাতি (Equipment)',
                'category_id' => $assetCategoryId,
                'description' => 'অফিস ও অন্যান্য সরঞ্জাম',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Liability Accounts
        $liabilityCategoryId = DB::table('account_categories')->where('type', 'Liability')->first()->id;

        $liabilityAccounts = [
            [
                'account_code' => '2001',
                'name' => 'দেনা হিসাব (Accounts Payable)',
                'category_id' => $liabilityCategoryId,
                'description' => 'সাপ্লায়ারদের প্রদেয় অর্থ',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '2002',
                'name' => 'বেতন প্রদেয় (Salaries Payable)',
                'category_id' => $liabilityCategoryId,
                'description' => 'কর্মচারীদের প্রদেয় বেতন',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '2003',
                'name' => 'ঋণ (Loans)',
                'category_id' => $liabilityCategoryId,
                'description' => 'ব্যাংক ও অন্যান্য ঋণ',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '2004',
                'name' => 'প্রদেয় ভ্যাট (VAT Payable)',
                'category_id' => $liabilityCategoryId,
                'description' => 'সরকারকে প্রদেয় ভ্যাট',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Equity Accounts
        $equityCategoryId = DB::table('account_categories')->where('type', 'Equity')->first()->id;

        $equityAccounts = [
            [
                'account_code' => '3001',
                'name' => 'মূলধন (Capital)',
                'category_id' => $equityCategoryId,
                'description' => 'প্রতিষ্ঠানের মূলধন',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '3002',
                'name' => 'সঞ্চিত মুনাফা (Retained Earnings)',
                'category_id' => $equityCategoryId,
                'description' => 'সঞ্চিত লাভ-ক্ষতি',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Revenue Accounts
        $revenueCategoryId = DB::table('account_categories')->where('type', 'Revenue')->first()->id;

        $revenueAccounts = [
            [
                'account_code' => '4001',
                'name' => 'বিক্রয় আয় (Sales Revenue)',
                'category_id' => $revenueCategoryId,
                'description' => 'পণ্য বিক্রয় থেকে আয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '4002',
                'name' => 'সেবা আয় (Service Revenue)',
                'category_id' => $revenueCategoryId,
                'description' => 'সেবা প্রদান থেকে আয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '4003',
                'name' => 'ডিসকাউন্ট (Discount)',
                'category_id' => $revenueCategoryId,
                'description' => 'বিক্রয়ে প্রদত্ত ডিসকাউন্ট',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Expense Accounts
        $expenseCategoryId = DB::table('account_categories')->where('type', 'Expense')->first()->id;

        $expenseAccounts = [
            [
                'account_code' => '5001',
                'name' => 'পণ্যের ব্যয় (Cost of Goods Sold)',
                'category_id' => $expenseCategoryId,
                'description' => 'বিক্রিত পণ্যের ব্যয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5002',
                'name' => 'বেতন ব্যয় (Salary Expense)',
                'category_id' => $expenseCategoryId,
                'description' => 'কর্মচারীদের বেতন',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5003',
                'name' => 'অফিস ভাড়া (Office Rent)',
                'category_id' => $expenseCategoryId,
                'description' => 'অফিস ভাড়া ব্যয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5004',
                'name' => 'ইউটিলিটি বিল (Utility Expense)',
                'category_id' => $expenseCategoryId,
                'description' => 'বিদ্যুৎ, পানি, গ্যাস ইত্যাদি',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5005',
                'name' => 'মার্কেটিং ব্যয় (Marketing Expense)',
                'category_id' => $expenseCategoryId,
                'description' => 'বিজ্ঞাপন ও প্রচার ব্যয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5006',
                'name' => 'যাতায়াত ব্যয় (Transportation Expense)',
                'category_id' => $expenseCategoryId,
                'description' => 'যাতায়াত ও পরিবহন ব্যয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'account_code' => '5007',
                'name' => 'অবচয় ব্যয় (Depreciation Expense)',
                'category_id' => $expenseCategoryId,
                'description' => 'সম্পদের অবচয় ব্যয়',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Insert all accounts
        DB::table('chart_of_accounts')->insert(array_merge(
            $assetAccounts,
            $liabilityAccounts,
            $equityAccounts,
            $revenueAccounts,
            $expenseAccounts
        ));
    }
}
