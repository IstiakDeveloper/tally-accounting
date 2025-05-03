<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // Asset, Liability, Equity, Revenue, Expense
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('account_categories');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('account_categories');
    }
};
