<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_pay_periods', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->string('type'); // semi_monthly_first, semi_monthly_second, monthly, weekly
            $table->string('status')->default('draft'); // draft, processing, computed, approved, paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->foreignId('processed_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamps();

            $table->unique([$scopeColumn, 'start_date', 'end_date']);
        });

        Schema::create('hris_payslips', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('pay_period_id')->constrained('hris_pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('night_diff_pay', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('sss_contribution', 10, 2)->default(0);
            $table->decimal('philhealth_contribution', 10, 2)->default(0);
            $table->decimal('pagibig_contribution', 10, 2)->default(0);
            $table->decimal('withholding_tax', 10, 2)->default(0);
            $table->decimal('total_gov_deductions', 10, 2)->default(0);
            $table->decimal('loan_deductions', 10, 2)->default(0);
            $table->decimal('cash_advance_deductions', 10, 2)->default(0);
            $table->decimal('absent_deduction', 10, 2)->default(0);
            $table->integer('absent_days')->default(0);
            $table->decimal('tardiness_deduction', 10, 2)->default(0);
            $table->decimal('undertime_deduction', 10, 2)->default(0);
            $table->integer('late_count')->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->json('earnings_breakdown')->nullable();
            $table->json('deductions_breakdown')->nullable();
            $table->json('attendance_summary')->nullable();
            $table->string('status')->default('draft'); // draft, final
            $table->timestamps();

            $table->unique(['pay_period_id', 'employee_id']);
            $table->index([$scopeColumn, 'employee_id']);
        });

        Schema::create('hris_allowances', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hris_payslip_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('hris_payslips')->cascadeOnDelete();
            $table->foreignId('payout_account_id')->nullable();
            $table->string('method');
            $table->string('account_details')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending, disbursed, failed
            $table->datetime('disbursed_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index('payslip_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_payslip_disbursements');
        Schema::dropIfExists('hris_allowances');
        Schema::dropIfExists('hris_payslips');
        Schema::dropIfExists('hris_pay_periods');
    }
};
