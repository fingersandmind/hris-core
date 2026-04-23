<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hris_sss_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('range_from', 12, 2);
            $table->decimal('range_to', 12, 2);
            $table->decimal('monthly_salary_credit', 12, 2);
            $table->decimal('employee_share', 10, 2);
            $table->decimal('employer_share', 10, 2);
            $table->decimal('ec_contribution', 10, 2)->default(0);
            $table->integer('effective_year');
            $table->timestamps();

            $table->index(['effective_year', 'range_from', 'range_to']);
        });

        Schema::create('hris_tax_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('range_from', 12, 2);
            $table->decimal('range_to', 12, 2)->nullable();
            $table->decimal('fixed_tax', 12, 2);
            $table->decimal('rate_over_excess', 5, 4);
            $table->integer('effective_year');
            $table->string('pay_period');
            $table->timestamps();

            $table->index(['effective_year', 'pay_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_tax_table');
        Schema::dropIfExists('hris_sss_contribution_table');
    }
};
