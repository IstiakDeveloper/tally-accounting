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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('action'); // create, update, delete, login, logout
            $table->string('module'); // users, accounts, inventory, etc.
            $table->string('reference_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
