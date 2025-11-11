<?php

namespace Tests\Feature;

use App\Models\LoginUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminToken;
    protected $productCategory;
    protected $quarter;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $admin = LoginUser::factory()->create([
            'username' => 'admin',
            'role' => 'admin'
        ]);

        // Login to get token
        $response = $this->postJson('/api/v1/login', [
            'username' => 'admin',
            'password' => 'password'
        ]);

        $this->adminToken = $response->json('data.token');

        // Create quarter
        $this->quarter = Quarter::factory()->create([
            'is_active' => true
        ]);

        // Create product category
        $this->productCategory = ProductCategory::factory()->create([
            'name' => 'Tube Test',
            'products' => ['VO', 'CAVS']
        ]);
    }

    /**
     * Test 1: Formula must start with =
     */
    public function test_formula_must_start_with_equals_sign()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Test',
                        'name_id' => 'test',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'calc',
                            'formula' => 'avg(thickness_a)',  // Missing =
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Formula validation failed'
                ]);
    }

    /**
     * Test 2: Formula with valid = prefix should pass
     */
    public function test_formula_with_equals_sign_should_pass()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Result',
                        'name_id' => 'result',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'calc',
                            'formula' => '=avg(thickness_a)',  // Valid with =
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);
    }

    /**
     * Test 3: Formula dependency validation - missing measurement item
     */
    public function test_formula_dependency_validation_should_fail_if_measurement_item_not_defined()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Average',
                        'name_id' => 'average',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'calc_avg',
                            'formula' => '=avg(thickness_a) + avg(thickness_b)',  // thickness_b not defined!
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Formula validation failed'
                ])
                ->assertJsonFragment([
                    'thickness_b'
                ]);
    }

    /**
     * Test 4: Formula dependency validation - correct order should pass
     */
    public function test_formula_dependency_validation_should_pass_with_correct_order()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Thickness B',
                        'name_id' => 'thickness_b',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Average',
                        'name_id' => 'average',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'calc_avg',
                            'formula' => '=(avg(thickness_a) + avg(thickness_b)) / 2',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        // Verify formula was processed (= stripped and normalized)
        $product = Product::first();
        $this->assertStringContainsString('avg(thickness_a)', $product->measurement_points[2]['variables'][0]['formula']);
        $this->assertStringNotContainsString('=', $product->measurement_points[2]['variables'][0]['formula']);
    }

    /**
     * Test 5: Function name normalization (AVG -> avg)
     */
    public function test_function_names_should_be_normalized_to_lowercase()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Result',
                        'name_id' => 'result',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'calc',
                            'formula' => '=AVG(thickness_a) + SIN(angle)',  // Uppercase functions
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        // Verify functions were normalized
        $product = Product::first();
        $formula = $product->measurement_points[1]['variables'][0]['formula'];
        $this->assertStringContainsString('avg(thickness_a)', $formula);
        $this->assertStringContainsString('sin(angle)', $formula);
        $this->assertStringNotContainsString('AVG', $formula);
        $this->assertStringNotContainsString('SIN', $formula);
    }

    /**
     * Test 6: Auto-generate name_id from name
     */
    public function test_name_id_should_be_auto_generated_from_name()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Room Temp',  // No name_id provided
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        // Verify name_id was auto-generated
        $product = Product::first();
        $this->assertEquals('room_temp', $product->measurement_points[0]['setup']['name_id']);
    }

    /**
     * Test 7: Complex formula with multiple math functions
     */
    public function test_complex_formula_with_multiple_math_functions()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Angle',
                        'name_id' => 'angle',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Radius',
                        'name_id' => 'radius',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ],
                [
                    'setup' => [
                        'name' => 'Result',
                        'name_id' => 'result',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'complex_calc',
                            'formula' => '=SQRT(POW(SIN(angle), 2) + POW(COS(angle), 2)) * radius',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => []
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        // Verify all functions normalized
        $product = Product::first();
        $formula = $product->measurement_points[2]['variables'][0]['formula'];
        $this->assertStringContainsString('sqrt', $formula);
        $this->assertStringContainsString('pow', $formula);
        $this->assertStringContainsString('sin', $formula);
        $this->assertStringContainsString('cos', $formula);
    }

    /**
     * Test 8: Pre-processing formula validation
     */
    public function test_pre_processing_formula_validation()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Temperature',
                        'name_id' => 'temperature',
                        'sample_amount' => 5,
                        'source' => 'INSTRUMENT',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'pre_processing_formulas' => [
                        [
                            'name' => 'temp_celsius',
                            'formula' => '=(temperature - 32) * 5 / 9',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => [
                        'per_sample_setting' => [
                            'is_raw_data' => false,
                            'pre_processing_formula_name' => 'temp_celsius'
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => '°C',
                        'value' => 25,
                        'tolerance_minus' => 5,
                        'tolerance_plus' => 5
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);

        // Verify formula was processed
        $product = Product::first();
        $formula = $product->measurement_points[0]['pre_processing_formulas'][0]['formula'];
        $this->assertStringNotContainsString('=', $formula);
        $this->assertStringContainsString('(temperature - 32)', $formula);
    }
}

