<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'quarter_id',
        'product_category_id',
        'product_name',
        'ref_spec_number',
        'nom_size_vo',
        'article_code',
        'no_document',
        'no_doc_reference',
        'measurement_points',
        'measurement_groups'
    ];

    protected $casts = [
        'measurement_points' => 'array',
        'measurement_groups' => 'array',
    ];

    /**
     * Relationship dengan quarter
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /**
     * Relationship dengan product category
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Relationship dengan product measurements
     */
    public function productMeasurements(): HasMany
    {
        return $this->hasMany(ProductMeasurement::class);
    }

    /**
     * Boot method untuk auto-generate product_id
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->product_id)) {
                $product->product_id = self::generateProductId();
            }
        });
    }

    /**
     * Generate unique product ID
     */
    public static function generateProductId(): string
    {
        do {
            $productId = 'PRD-' . strtoupper(Str::random(8));
        } while (self::where('product_id', $productId)->exists());

        return $productId;
    }

    /**
     * Validate measurement points structure
     */
    public function validateMeasurementPoints(): array
    {
        $errors = [];
        $measurementPoints = $this->measurement_points ?? [];

        if (empty($measurementPoints)) {
            $errors[] = 'Measurement points tidak boleh kosong';
            return $errors;
        }

        foreach ($measurementPoints as $index => $point) {
            $pointErrors = $this->validateSingleMeasurementPoint($point, $index);
            $errors = array_merge($errors, $pointErrors);
        }

        return $errors;
    }

    /**
     * Validate single measurement point
     */
    private function validateSingleMeasurementPoint(array $point, int $index): array
    {
        $errors = [];
        $pointPrefix = "Measurement Point #{$index}: ";

        // Validate setup
        if (!isset($point['setup'])) {
            $errors[] = $pointPrefix . 'Setup wajib diisi';
            return $errors;
        }

        $setup = $point['setup'];
        $requiredSetupFields = ['name', 'name_id', 'sample_amount', 'source', 'type', 'nature'];
        
        foreach ($requiredSetupFields as $field) {
            if (!isset($setup[$field]) || empty($setup[$field])) {
                $errors[] = $pointPrefix . "Setup.{$field} wajib diisi";
            }
        }

        // Validate source-specific fields
        if (isset($setup['source'])) {
            switch ($setup['source']) {
                case 'INSTRUMENT':
                    if (!isset($setup['source_instrument_id']) || empty($setup['source_instrument_id'])) {
                        $errors[] = $pointPrefix . 'source_instrument_id wajib diisi untuk source INSTRUMENT';
                    }
                    break;
                case 'DERIVED':
                    if (!isset($setup['source_derived_name_id']) || empty($setup['source_derived_name_id'])) {
                        $errors[] = $pointPrefix . 'source_derived_name_id wajib diisi untuk source DERIVED';
                    }
                    break;
                case 'TOOL':
                    if (!isset($setup['source_tool_model']) || empty($setup['source_tool_model'])) {
                        $errors[] = $pointPrefix . 'source_tool_model wajib diisi untuk source TOOL';
                    }
                    break;
            }
        }

        // Validate variables (optional but if exists must be valid)
        if (isset($point['variables']) && is_array($point['variables'])) {
            foreach ($point['variables'] as $varIndex => $variable) {
                $varErrors = $this->validateVariable($variable, $index, $varIndex);
                $errors = array_merge($errors, $varErrors);
            }
        }

        // Validate evaluation type
        if (!isset($point['evaluation_type'])) {
            $errors[] = $pointPrefix . 'evaluation_type wajib diisi';
        }

        return $errors;
    }

    /**
     * Validate variable structure
     */
    private function validateVariable(array $variable, int $pointIndex, int $varIndex): array
    {
        $errors = [];
        $prefix = "Measurement Point #{$pointIndex}, Variable #{$varIndex}: ";

        $requiredFields = ['type', 'name'];
        foreach ($requiredFields as $field) {
            if (!isset($variable[$field]) || empty($variable[$field])) {
                $errors[] = $prefix . "{$field} wajib diisi";
            }
        }

        // Type-specific validation
        if (isset($variable['type'])) {
            switch ($variable['type']) {
                case 'FIXED':
                    if (!isset($variable['value']) || !is_numeric($variable['value'])) {
                        $errors[] = $prefix . 'value wajib diisi dan harus berupa angka untuk type FIXED';
                    }
                    break;
                case 'FORMULA':
                    if (!isset($variable['formula']) || empty($variable['formula'])) {
                        $errors[] = $prefix . 'formula wajib diisi untuk type FORMULA';
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Check if product already exists with same basic info
     */
    public static function checkProductExists(array $basicInfo): bool
    {
        // Validate required fields
        if (!isset($basicInfo['product_category_id']) || !isset($basicInfo['product_name'])) {
            throw new \InvalidArgumentException('product_category_id and product_name are required');
        }

        $query = self::where('product_category_id', $basicInfo['product_category_id'])
                    ->where('product_name', $basicInfo['product_name']);

        // Check optional fields
        $optionalFields = ['ref_spec_number', 'nom_size_vo', 'article_code', 'no_document', 'no_doc_reference'];
        
        foreach ($optionalFields as $field) {
            if (isset($basicInfo[$field]) && !empty($basicInfo[$field])) {
                $query->where($field, $basicInfo[$field]);
            } else {
                $query->whereNull($field);
            }
        }

        return $query->exists();
    }

    /**
     * Get measurement point by name_id
     */
    public function getMeasurementPointByNameId(string $nameId): ?array
    {
        $measurementPoints = $this->measurement_points ?? [];
        
        foreach ($measurementPoints as $point) {
            if (isset($point['setup']['name_id']) && $point['setup']['name_id'] === $nameId) {
                return $point;
            }
        }

        return null;
    }

    /**
     * Get all measurement point name_ids untuk digunakan sebagai source_derived
     */
    public function getAvailableSourceDerivedIds(): array
    {
        $measurementPoints = $this->measurement_points ?? [];
        $nameIds = [];

        foreach ($measurementPoints as $point) {
            if (isset($point['setup']['name_id'])) {
                $nameIds[] = $point['setup']['name_id'];
            }
        }

        return $nameIds;
    }
}
