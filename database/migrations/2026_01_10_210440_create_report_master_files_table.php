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
        Schema::create('report_master_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('login_users')->onDelete('cascade');
            $table->foreignId('product_measurement_id')->constrained('product_measurements')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path', 500);
            $table->json('sheet_names'); // Array of sheet names: ['Cover', 'Summary', 'raw_data', 'Appendix']
            $table->timestamps();
            
            // Indexes
            $table->index('product_measurement_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_master_files');
    }
};
