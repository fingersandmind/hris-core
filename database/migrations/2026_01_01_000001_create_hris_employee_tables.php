<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_employees', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_number');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('nationality')->default('Filipino');
            $table->string('tin')->nullable();
            $table->string('sss_number')->nullable();
            $table->string('philhealth_number')->nullable();
            $table->string('pagibig_number')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_status')->default('probationary');
            $table->string('employment_type')->default('full_time');
            $table->date('date_hired');
            $table->date('date_regularized')->nullable();
            $table->date('date_separated')->nullable();
            $table->string('separation_reason')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->string('pay_frequency')->default('semi_monthly');
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->boolean('deduct_sss')->default(true);
            $table->boolean('deduct_philhealth')->default(true);
            $table->boolean('deduct_pagibig')->default(true);
            $table->boolean('deduct_tax')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique([$scopeColumn, 'employee_number']);
            $table->index([$scopeColumn, 'is_active']);
            $table->index([$scopeColumn, 'department']);
        });

        Schema::create('hris_employee_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hris_employees')->cascadeOnDelete();
            $table->string('method');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('split_type')->nullable();
            $table->decimal('split_value', 10, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hris_departments', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('head_employee_id')->nullable()->constrained('hris_employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([$scopeColumn, 'name']);
        });

        Schema::create('hris_positions', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('hris_departments')->nullOnDelete();
            $table->string('salary_grade')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([$scopeColumn, 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_positions');
        Schema::dropIfExists('hris_departments');
        Schema::dropIfExists('hris_employee_payout_accounts');
        Schema::dropIfExists('hris_employees');
    }
};
