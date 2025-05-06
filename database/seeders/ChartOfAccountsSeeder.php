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
        // Get the default business ID
        $businessId = DB::table('businesses')->first()->id ?? 1;

        // Get the user ID for seeding
        $userId = DB::table('users')->where('email', 'admin@example.com')->first()->id ?? 1;

        // Asset Accounts
        $assetCategoryId = DB::table('account_categories')
            ->where('type', 'Asset')
            ->where('business_id', $businessId)
            ->first()->id;

        $assetAccounts = [
            [
                'business_id' => $businessId,
                'account_code' => '1001',
                'name' => 'Cash',
                'category_id' => $assetCategoryId,
                'description' => 'Cash on hand',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '1002',
                'name' => 'Bank',
                'category_id' => $assetCategoryId,
                'description' => 'Bank accounts',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '1003',
                'name' => 'Accounts Receivable',
                'category_id' => $assetCategoryId,
                'description' => 'Money owed by customers',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '1004',
                'name' => 'Inventory',
                'category_id' => $assetCategoryId,
                'description' => 'Value of inventory',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '1005',
                'name' => 'Investments',
                'category_id' => $assetCategoryId,
                'description' => 'Long-term investments',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '1006',
                'name' => 'Equipment',
                'category_id' => $assetCategoryId,
                'description' => 'Office and other equipment',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Liability Accounts
        $liabilityCategoryId = DB::table('account_categories')
            ->where('type', 'Liability')
            ->where('business_id', $businessId)
            ->first()->id;

        $liabilityAccounts = [
            [
                'business_id' => $businessId,
                'account_code' => '2001',
                'name' => 'Accounts Payable',
                'category_id' => $liabilityCategoryId,
                'description' => 'Money owed to suppliers',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '2002',
                'name' => 'Salaries Payable',
                'category_id' => $liabilityCategoryId,
                'description' => 'Salaries payable to employees',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '2003',
                'name' => 'Loans',
                'category_id' => $liabilityCategoryId,
                'description' => 'Bank and other loans',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '2004',
                'name' => 'VAT Payable',
                'category_id' => $liabilityCategoryId,
                'description' => 'VAT payable to government',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Equity Accounts
        $equityCategoryId = DB::table('account_categories')
            ->where('type', 'Equity')
            ->where('business_id', $businessId)
            ->first()->id;

        $equityAccounts = [
            [
                'business_id' => $businessId,
                'account_code' => '3001',
                'name' => 'Capital',
                'category_id' => $equityCategoryId,
                'description' => 'Business capital',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '3002',
                'name' => 'Retained Earnings',
                'category_id' => $equityCategoryId,
                'description' => 'Accumulated profit and loss',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Revenue Accounts
        $revenueCategoryId = DB::table('account_categories')
            ->where('type', 'Revenue')
            ->where('business_id', $businessId)
            ->first()->id;

        $revenueAccounts = [
            [
                'business_id' => $businessId,
                'account_code' => '4001',
                'name' => 'Sales Revenue',
                'category_id' => $revenueCategoryId,
                'description' => 'Income from product sales',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '4002',
                'name' => 'Service Revenue',
                'category_id' => $revenueCategoryId,
                'description' => 'Income from services',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '4003',
                'name' => 'Discount',
                'category_id' => $revenueCategoryId,
                'description' => 'Discounts given on sales',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Expense Accounts
        $expenseCategoryId = DB::table('account_categories')
            ->where('type', 'Expense')
            ->where('business_id', $businessId)
            ->first()->id;

        $expenseAccounts = [
            [
                'business_id' => $businessId,
                'account_code' => '5001',
                'name' => 'Cost of Goods Sold',
                'category_id' => $expenseCategoryId,
                'description' => 'Cost of sold products',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5002',
                'name' => 'Salary Expense',
                'category_id' => $expenseCategoryId,
                'description' => 'Employee salaries',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5003',
                'name' => 'Office Rent',
                'category_id' => $expenseCategoryId,
                'description' => 'Office rent expense',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5004',
                'name' => 'Utility Expense',
                'category_id' => $expenseCategoryId,
                'description' => 'Electricity, water, gas, etc.',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5005',
                'name' => 'Marketing Expense',
                'category_id' => $expenseCategoryId,
                'description' => 'Advertising and promotion expenses',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5006',
                'name' => 'Transportation Expense',
                'category_id' => $expenseCategoryId,
                'description' => 'Travel and transportation costs',
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'business_id' => $businessId,
                'account_code' => '5007',
                'name' => 'Depreciation Expense',
                'category_id' => $expenseCategoryId,
                'description' => 'Asset depreciation expenses',
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
