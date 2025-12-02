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
        Schema::create('scale_measurements', function (Blueprint $table) {
            $table->id();
            $table->string('scale_measurement_id')->unique(); // Custom ID
            $table->foreignId('product_id')->constrained('products');
            $table->date('measurement_date'); // Tanggal pengukuran
            $table->decimal('weight', 10, 2)->nullable(); // Berat dalam satuan tertentu (gram/kg)
            $table->enum('status', ['NOT_CHECKED', 'CHECKED'])->default('NOT_CHECKED');
            $table->foreignId('measured_by')->nullable()->constrained('login_users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('scale_measurement_id');
            $table->index('product_id');
            $table->index('measurement_date');
            $table->index('status');
            
            // Constraint: 1 product hanya bisa punya 1 measurement per hari
            $table->unique(['product_id', 'measurement_date'], 'unique_product_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scale_measurements');
    }
};

