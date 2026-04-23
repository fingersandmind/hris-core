<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        Schema::create('hris_government_reports', function (Blueprint $table) use ($scopeColumn) {
            $table->id();
            $table->unsignedBigInteger($scopeColumn);
            $table->string('report_type');
            $table->integer('period_month')->nullable();
            $table->integer('period_year');
            $table->string('status')->default('draft');
            $table->string('file_path')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('generated_by')->nullable();
            $table->datetime('generated_at')->nullable();
            $table->datetime('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique([$scopeColumn, 'report_type', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_government_reports');
    }
};
