<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('time');
            $table->enum('type', ['check_in', 'check_out']);
            $table->enum('category', [
                'shift_start',
                'break_start', 
                'offsite_start',
                'shift_end', 
                'break_end',
                'offsite_end'
            ])->nullable()->default('shift_start');
            $table->uuid('paired_log_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Index to optimize queries for employee work logs within date ranges
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
