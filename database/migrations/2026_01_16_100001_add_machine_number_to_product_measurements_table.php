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
        if (!Schema::hasColumn('product_measurements', 'machine_number')) {
            Schema::table('product_measurements', function (Blueprint $table) {
                $table->string('machine_number')->nullable()->after('batch_number');
                $table->index('machine_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        if (Schema::hasColumn('product_measurements', 'machine_number')) {
            Schema::table('product_measurements', function (Blueprint $table) {
                // Try to drop index first (ignore if doesn't exist)
                try {
                    $table->dropIndex(['machine_number']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
                
                $table->dropColumn('machine_number');
            });
        }
    }
};
