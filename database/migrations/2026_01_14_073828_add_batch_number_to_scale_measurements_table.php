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
        // Check if column already exists before adding
        if (!Schema::hasColumn('scale_measurements', 'batch_number')) {
            Schema::table('scale_measurements', function (Blueprint $table) {
                $table->string('batch_number')->unique()->after('scale_measurement_id');
                $table->index('batch_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        if (Schema::hasColumn('scale_measurements', 'batch_number')) {
            Schema::table('scale_measurements', function (Blueprint $table) {
                // Try to drop index first (ignore if doesn't exist)
                try {
                    $table->dropIndex(['batch_number']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
                
                // Try to drop unique constraint
                try {
                    $connection = Schema::getConnection();
                    $connection->statement('ALTER TABLE scale_measurements DROP INDEX scale_measurements_batch_number_unique');
                } catch (\Exception $e) {
                    // Constraint might not exist, ignore
                }
                
                $table->dropColumn('batch_number');
            });
        }
    }
};
