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
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->enum('measurement_type', ['FULL_MEASUREMENT', 'SCALE_MEASUREMENT'])
                  ->default('FULL_MEASUREMENT')
                  ->after('sample_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->dropColumn('measurement_type');
        });
    }
};
