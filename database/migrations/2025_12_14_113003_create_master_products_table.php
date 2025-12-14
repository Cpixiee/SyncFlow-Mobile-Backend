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
        Schema::create('master_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories');
            $table->string('product_name'); // Nama Produk (e.g., "COT", "AVSSH")
            $table->string('product_spec_name'); // Spesifikasi Produk (Alias) - auto-generated from product_name + size + color
            $table->string('ref_spec_number')->nullable(); // Ref Spec Number
            $table->string('nom_size_vo')->nullable(); // Nom Size VO
            $table->string('article_code')->nullable(); // Article Code
            $table->string('no_document')->nullable(); // No Document
            $table->string('no_doc_reference')->nullable(); // No Doc. Reference
            $table->string('color')->nullable(); // COLOR (e.g., "Black", "F", "B", "W")
            $table->string('size')->nullable(); // Size (e.g., "150mm", "0.3", "0.5")
            $table->timestamps();
            
            // Indexes
            $table->index('product_category_id');
            $table->index('product_spec_name');
            $table->index(['product_name', 'color', 'size']); // For search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_products');
    }
};
