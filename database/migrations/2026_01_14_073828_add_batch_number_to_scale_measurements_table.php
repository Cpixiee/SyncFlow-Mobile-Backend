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
        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->string('batch_number')->unique()->after('scale_measurement_id');
            $table->index('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->dropIndex(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
