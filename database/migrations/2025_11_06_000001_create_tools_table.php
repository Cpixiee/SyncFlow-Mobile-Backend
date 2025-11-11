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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name'); // Tools Name - untuk penamaan
            $table->string('tool_model'); // Tool Model - nama tools yang bisa di select saat create product
            $table->enum('tool_type', ['OPTICAL', 'MECHANICAL']); // Tools Type
            $table->date('last_calibration')->nullable(); // Last Calibration
            $table->date('next_calibration')->nullable(); // Next Calibration (auto update saat edit)
            $table->string('imei')->unique(); // IMEI - nilai atau seri dari setiap models
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE'); // Status - Active/Inactive
            $table->timestamps();
            
            // Indexes untuk performa
            $table->index('tool_model');
            $table->index('imei');
            $table->index('status');
            $table->index(['tool_model', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};

