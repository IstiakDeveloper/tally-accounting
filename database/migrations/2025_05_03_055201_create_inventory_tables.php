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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('product_categories');
            $table->string('unit'); // pieces, kg, liter, etc.
            $table->decimal('purchase_price', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('selling_price', 15, 2); // বাংলাদেশি টাকা
            $table->integer('reorder_level')->default(10);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('type'); // purchase, sale, transfer, adjustment
            $table->date('transaction_date');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2); // বাংলাদেশি টাকা
            $table->text('remarks')->nullable();
            $table->foreignId('related_journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('quantity', 15, 2);
            $table->decimal('average_cost', 15, 2); // বাংলাদেশি টাকা
            $table->unique(['product_id', 'warehouse_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
