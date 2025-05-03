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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // customer, supplier, both
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->foreignId('account_receivable_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('account_payable_id')->nullable()->constrained('chart_of_accounts');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft'); // draft, confirmed, received, cancelled
            $table->decimal('total_amount', 15, 2); // বাংলাদেশি টাকা
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('discount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('tax_amount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('total', 15, 2); // বাংলাদেশি টাকা
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('customer_id')->constrained('contacts');
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->string('status')->default('draft'); // draft, confirmed, delivered, cancelled
            $table->decimal('total_amount', 15, 2); // বাংলাদেশি টাকা
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('discount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('tax_amount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('total', 15, 2); // বাংলাদেশি টাকা
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('type'); // sales, purchase
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('sub_total', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('discount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('tax_amount', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->decimal('total', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('amount_paid', 15, 2)->default(0.00); // বাংলাদেশি টাকা
            $table->string('status')->default('unpaid'); // unpaid, partially_paid, paid, cancelled
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2); // বাংলাদেশি টাকা
            $table->string('payment_method'); // cash, bank, mobile_banking
            $table->string('transaction_id')->nullable();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('contacts');
    }
};
