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
        if (!Schema::hasColumn('issues', 'is_archived')) {
            Schema::table('issues', function (Blueprint $table) {
                $table->boolean('is_archived')->default(false)->after('status');
                $table->index('is_archived');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        if (Schema::hasColumn('issues', 'is_archived')) {
            Schema::table('issues', function (Blueprint $table) {
                // Try to drop index first (ignore if doesn't exist)
                try {
                    $table->dropIndex(['is_archived']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
                
                $table->dropColumn('is_archived');
            });
        }
    }
};
