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
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('absence_type_id')->constrained('absence_types')->restrictOnDelete();
            $table->foreignUuid('request_id')->nullable()->constrained('absence_requests')->nullOnDelete();
            $table->date('date');
            $table->boolean('is_partial')->default(false);
            $table->time('start_time')->nullable(); // Only used when is_partial = true
            $table->time('end_time')->nullable(); // Only used when is_partial = true
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Index to optimize queries for employee absences within date ranges
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
