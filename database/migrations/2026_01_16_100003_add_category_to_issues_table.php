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
        if (!Schema::hasColumn('issues', 'category')) {
            Schema::table('issues', function (Blueprint $table) {
                $table->enum('category', [
                    'CUSTOMER_CLAIM',
                    'INTERNAL_DEFECT',
                    'NON_CONFORMITY',
                    'QUALITY_INFORMATION',
                    'OTHER'
                ])->default('OTHER')->after('status');
                $table->index('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        if (Schema::hasColumn('issues', 'category')) {
            Schema::table('issues', function (Blueprint $table) {
                // Try to drop index first (ignore if doesn't exist)
                try {
                    $table->dropIndex(['category']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
                
                $table->dropColumn('category');
            });
        }
    }
};
