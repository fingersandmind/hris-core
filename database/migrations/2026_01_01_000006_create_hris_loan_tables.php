<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_loans', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->string('loan_type'); // sss_salary, sss_calamity, pagibig_mpl, pagibig_calamity, company, cash_advance
            $table->string('reference_number')->nullable();
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('total_payable', 12, 2);
            $table->decimal('monthly_amortization', 10, 2);
            $table->decimal('interest_rate', 5, 4)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('pending'); // pending, approved, active, fully_paid, defaulted, cancelled
            $table->foreignId('approved_by')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index([$scopeColumn, 'employee_id', 'status']);
        });

        Schema::create('hris_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('hris_loans')->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('hris_thirteenth_month', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->integer('year');
            $table->decimal('total_basic_salary', 14, 2)->default(0);
            $table->decimal('computed_amount', 12, 2)->default(0);
            $table->decimal('adjustments', 10, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->string('status')->default('draft'); // draft, computed, approved, paid
            $table->datetime('computed_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_thirteenth_month');
        Schema::dropIfExists('hris_loan_payments');
        Schema::dropIfExists('hris_loans');
    }
};
