<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_employee_documents', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees')->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index([$scopeColumn, 'employee_id']);
            $table->index(['employee_id', 'category']);
        });

        Schema::create('hris_salary_adjustments', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->decimal('previous_salary', 12, 2);
            $table->decimal('new_salary', 12, 2);
            $table->decimal('previous_daily_rate', 10, 2)->nullable();
            $table->decimal('new_daily_rate', 10, 2)->nullable();
            $table->string('reason');
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_salary_adjustments');
        Schema::dropIfExists('hris_employee_documents');
    }
};
