<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_leave_types', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn)->nullable();
            $table->string('code');
            $table->string('name');
            $table->decimal('max_days_per_year', 5, 2)->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_convertible')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->string('gender_restriction')->nullable();
            $table->integer('min_service_months')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([$scopeColumn, 'code']);
        });

        Schema::create('hris_leave_balances', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->foreignId('leave_type_id')->constrained('hris_leave_types');
            $table->integer('year');
            $table->decimal('total_credits', 5, 2)->default(0);
            $table->decimal('used_credits', 5, 2)->default(0);
            $table->decimal('pending_credits', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('hris_leave_requests', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->foreignId('leave_type_id')->constrained('hris_leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 2);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_period')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index([$scopeColumn, 'employee_id', 'status']);
            $table->index([$scopeColumn, 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_leave_requests');
        Schema::dropIfExists('hris_leave_balances');
        Schema::dropIfExists('hris_leave_types');
    }
};
