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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained('departments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('designation_id')->constrained('designations');
            $table->date('joining_date');
            $table->string('employment_status'); // permanent, probation, contract, part-time
            $table->date('contract_end_date')->nullable();
            $table->decimal('basic_salary', 15, 2); // বাংলাদেশি টাকা
            $table->foreignId('salary_account_id')->constrained('chart_of_accounts');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // fixed, percentage
            $table->decimal('value', 10, 2); // Amount or percentage value
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // fixed, percentage
            $table->decimal('value', 10, 2); // Amount or percentage value
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('allowance_type_id')->constrained('allowance_types');
            $table->decimal('amount', 15, 2); // বাংলাদেশি টাকা
            $table->timestamps();
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('deduction_type_id')->constrained('deduction_types');
            $table->decimal('amount', 15, 2); // বাংলাদেশি টাকা
            $table->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('month_year');
            $table->decimal('basic_salary', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('total_allowances', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('total_deductions', 15, 2); // বাংলাদেশি টাকা
            $table->decimal('net_salary', 15, 2); // বাংলাদেশি টাকা
            $table->string('payment_status')->default('unpaid'); // unpaid, paid
            $table->date('payment_date')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('salary_slip_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained('salary_slips')->onDelete('cascade');
            $table->string('type'); // allowance, deduction
            $table->string('name');
            $table->decimal('amount', 15, 2); // বাংলাদেশি টাকা
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('days_allowed_per_year');
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('salary_slip_details');
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('employee_allowances');
        Schema::dropIfExists('deduction_types');
        Schema::dropIfExists('allowance_types');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
