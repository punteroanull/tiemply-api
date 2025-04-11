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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('tax_id')->unique(); // CIF
            $table->string('contact_email');
            $table->string('contact_person');
            $table->string('address');
            $table->string('phone');
            $table->enum('vacation_type', ['business_days', 'calendar_days'])->default('business_days');
            $table->integer('max_vacation_days')->default(22);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
