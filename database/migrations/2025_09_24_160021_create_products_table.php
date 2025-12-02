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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique(); // Custom product ID
            $table->foreignId('quarter_id')->nullable()->constrained('quarters'); // Nullable: quarter only for measurement results
            $table->foreignId('product_category_id')->constrained('product_categories');
            
            // Basic Info Fields
            $table->string('product_name'); // Enum based on product_category
            $table->string('ref_spec_number')->nullable();
            $table->string('nom_size_vo')->nullable();
            $table->string('article_code')->nullable();
            $table->string('no_document')->nullable();
            $table->string('no_doc_reference')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            
            // Measurement Points - JSON untuk menyimpan complex structure
            $table->json('measurement_points'); // Array of measurement point configurations
            
            $table->timestamps();
            
            // Indexes untuk performa
            $table->index('product_id');
            $table->index('quarter_id');
            $table->index('product_category_id');
            $table->index(['product_category_id', 'product_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
