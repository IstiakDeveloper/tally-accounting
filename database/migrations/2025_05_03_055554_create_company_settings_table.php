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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Bangladesh');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('currency')->default('BDT');
            $table->string('currency_symbol')->default('৳');
            $table->string('date_format')->default('d/m/Y');
            $table->string('time_format')->default('h:i A');
            $table->string('timezone')->default('Asia/Dhaka');
            $table->string('fiscal_year_start_month')->default('January');
            $table->string('decimal_separator')->default('.');
            $table->string('thousand_separator')->default(',');
            $table->string('invoice_prefix')->default('INV-');
            $table->string('purchase_prefix')->default('PO-');
            $table->string('sales_prefix')->default('SO-');
            $table->string('receipt_prefix')->default('REC-');
            $table->string('payment_prefix')->default('PAY-');
            $table->string('journal_prefix')->default('JE-');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
