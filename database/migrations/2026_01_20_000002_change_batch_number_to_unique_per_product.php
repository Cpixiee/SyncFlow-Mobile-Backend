<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scale_measurements', function (Blueprint $table) {
            // Drop existing global unique constraint on batch_number if exists
            $connection = Schema::getConnection();
            try {
                // Try to drop unique index (MySQL)
                $connection->statement('ALTER TABLE scale_measurements DROP INDEX scale_measurements_batch_number_unique');
            } catch (\Exception $e) {
                // Try alternative index name
                try {
                    $connection->statement('ALTER TABLE scale_measurements DROP INDEX batch_number');
                } catch (\Exception $e2) {
                    // Index might not exist, continue
                }
            }
        });

        Schema::table('scale_measurements', function (Blueprint $table) {
            // Add composite unique constraint: batch_number must be unique per product_id
            $table->unique(['product_id', 'batch_number'], 'unique_product_batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scale_measurements', function (Blueprint $table) {
            // Drop composite unique constraint
            $table->dropUnique('unique_product_batch_number');
        });

        Schema::table('scale_measurements', function (Blueprint $table) {
            // Restore global unique constraint on batch_number
            $table->string('batch_number')->unique()->change();
        });
    }
};
