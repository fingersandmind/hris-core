<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_schedules', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_minutes')->default(60);
            $table->json('work_days')->default('[1,2,3,4,5]');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('hris_holidays', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn)->nullable();
            $table->string('name');
            $table->date('date');
            $table->string('type');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
        });

        Schema::create('hris_attendances', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->foreignId('employee_id')->constrained('hris_employees');
            $table->date('date');
            $table->datetime('clock_in')->nullable();
            $table->datetime('clock_out')->nullable();
            $table->datetime('break_start')->nullable();
            $table->datetime('break_end')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('undertime_hours', 5, 2)->default(0);
            $table->integer('tardiness_minutes')->default(0);
            $table->decimal('night_diff_hours', 5, 2)->default(0);
            $table->boolean('is_rest_day')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_type')->nullable();
            $table->string('status')->default('present');
            $table->string('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique([$scopeColumn, 'employee_id', 'date']);
            $table->index([$scopeColumn, 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_attendances');
        Schema::dropIfExists('hris_holidays');
        Schema::dropIfExists('hris_schedules');
    }
};
