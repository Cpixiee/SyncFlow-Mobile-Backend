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
            // Add due_date column - terpisah dari measured_at
            $table->timestamp('due_date')->nullable()->after('measured_at');
            
            // Add sample_status enum
            $table->enum('sample_status', ['OK', 'NG', 'NOT_COMPLETE'])->default('NOT_COMPLETE')->after('overall_result');
            
            // Update status enum - tambah TODO status
            $table->dropColumn('status');
        });
        
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->enum('status', ['TODO', 'PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('TODO')->after('measurement_type');
            
            // Add index untuk due_date
            $table->index('due_date');
            $table->index('sample_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'sample_status']);
            $table->dropColumn('status');
        });
        
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('PENDING')->after('measurement_type');
        });
    }
};

