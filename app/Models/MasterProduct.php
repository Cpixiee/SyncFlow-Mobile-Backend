<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_category_id',
        'product_name',
        'product_spec_name',
        'ref_spec_number',
        'nom_size_vo',
        'article_code',
        'no_document',
        'no_doc_reference',
        'color',
        'size',
    ];

    /**
     * Relationship dengan product category
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Check if this master product has been created as actual product
     * Based on product_spec_name (unique combination)
     */
    public function isCreated(): bool
    {
        return Product::where('product_spec_name', $this->product_spec_name)
            ->exists();
    }
}
