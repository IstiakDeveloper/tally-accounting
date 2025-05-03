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
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // financial, inventory, sales, purchase, payroll
            $table->text('description')->nullable();
            $table->json('structure')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained('report_templates');
            $table->string('name');
            $table->json('parameters')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
        Schema::dropIfExists('report_templates');
    }
};
