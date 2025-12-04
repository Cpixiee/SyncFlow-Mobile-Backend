<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\Quarter;
use App\Models\ProductCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quarter_id' => null, // Quarter is nullable - only for measurement results
            'product_category_id' => ProductCategory::factory(),
            'product_name' => $this->faker->randomElement(['VO', 'COT', 'CAVS', 'CIVUS']),
            'ref_spec_number' => 'SPEC-' . strtoupper($this->faker->lexify('???-###')),
            'nom_size_vo' => $this->faker->randomFloat(1, 1.0, 5.0) . 'mm',
            'article_code' => 'ART-' . strtoupper($this->faker->lexify('???-###')),
            'no_document' => 'DOC-' . $this->faker->numerify('####'),
            'no_doc_reference' => 'REF-' . $this->faker->numerify('####'),
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Test Measurement',
                        'name_id' => 'test_measurement',
                        'sample_amount' => $this->faker->numberBetween(3, 10),
                        'source' => $this->faker->randomElement(['MANUAL', 'INSTRUMENT', 'DERIVED']),
                        'type' => $this->faker->randomElement(['SINGLE', 'BEFORE_AFTER']),
                        'nature' => $this->faker->randomElement(['QUALITATIVE', 'QUANTITATIVE'])
                    ],
                    'evaluation_type' => $this->faker->randomElement(['PER_SAMPLE', 'JOINT', 'SKIP_CHECK']),
                    'evaluation_setting' => [
                        'per_sample_setting' => [
                            'is_raw_data' => true
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => 'mm',
                        'value' => $this->faker->randomFloat(2, 10.0, 20.0),
                        'tolerance_minus' => 0.5,
                        'tolerance_plus' => 0.5
                    ]
                ]
            ]
        ];
    }
}
