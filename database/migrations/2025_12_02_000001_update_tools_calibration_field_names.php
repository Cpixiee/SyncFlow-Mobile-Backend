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
        Schema::table('tools', function (Blueprint $table) {
            // Rename last_calibration to last_calibration_at
            $table->renameColumn('last_calibration', 'last_calibration_at');
            // Rename next_calibration to next_calibration_at
            $table->renameColumn('next_calibration', 'next_calibration_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            // Revert back to original names
            $table->renameColumn('last_calibration_at', 'last_calibration');
            $table->renameColumn('next_calibration_at', 'next_calibration');
        });
    }
};

