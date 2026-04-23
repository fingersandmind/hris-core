<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_overtime_requests', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->date('date');
            $table->time('planned_start');
            $table->time('planned_end');
            $table->decimal('planned_hours', 5, 2);
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->text('reason');
            $table->boolean('is_rest_day')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_type')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->datetime('rendered_at')->nullable();
            $table->timestamps();

            $table->index([$scopeColumn, 'employee_id', 'date']);
            $table->index([$scopeColumn, 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_overtime_requests');
    }
};
