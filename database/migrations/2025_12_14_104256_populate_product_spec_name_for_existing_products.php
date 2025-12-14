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
        // Populate product_spec_name for existing products
        // Format: "{product_name} {size} {color}" (trimmed, with single spaces)
        \DB::table('products')->whereNull('product_spec_name')->orWhere('product_spec_name', '')->chunkById(100, function ($products) {
            foreach ($products as $product) {
                $parts = array_filter([
                    $product->product_name ?? '',
                    $product->size ?? null,
                    $product->color ?? null
                ], function($value) {
                    return !empty($value) && trim($value) !== '';
                });

                $productSpecName = trim(implode(' ', $parts));

                \DB::table('products')
                    ->where('id', $product->id)
                    ->update(['product_spec_name' => $productSpecName]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - data population can't be undone safely
        // If needed, set all to null
        // \DB::table('products')->update(['product_spec_name' => null]);
    }
};
