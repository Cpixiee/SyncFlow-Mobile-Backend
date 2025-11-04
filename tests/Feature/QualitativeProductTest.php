<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Quarter;
use App\Models\ProductCategory;
use App\Models\Product;
use Tymon\JWTAuth\Facades\JWTAuth;

class QualitativeProductTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminToken;
    protected $quarter;
    protected $tubeTestCategory;
    protected $wireTestCategory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = LoginUser::factory()->create([
            'username' => 'testadmin',
            'role' => 'admin',
            'password' => bcrypt('testpass')
        ]);
        
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
        
        // Create test quarter
        $this->quarter = Quarter::create([
            'name' => 'Q4',
            'year' => 2024,
            'start_month' => 10,
            'end_month' => 12,
            'start_date' => '2024-10-01',
            'end_date' => '2024-12-31',
            'is_active' => true
        ]);
        
        // Seed categories
        ProductCategory::seedDefaultCategories();
        $this->tubeTestCategory = ProductCategory::where('name', 'Tube Test')->first();
        $this->wireTestCategory = ProductCategory::where('name', 'Wire Test Reguler')->first();
    }

    /** @test */
    public function test_can_create_pure_qualitative_product()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->tubeTestCategory->id,
                'product_name' => 'VO',
                'ref_spec_number' => 'QUAL-001'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Visual Appearance',
                        'name_id' => 'visual_appearance',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Visual Quality',
                            'options' => ['Good', 'Fair', 'Poor'],
                            'passing_criteria' => 'All samples must be Good or Fair'
                        ]
                    ],
                    'rule_evaluation_setting' => null
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('products', [
            'product_name' => 'VO',
            'ref_spec_number' => 'QUAL-001'
        ]);
    }

    /** @test */
    public function test_can_create_mixed_quantitative_and_qualitative_product()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->wireTestCategory->id,
                'product_name' => 'CAVS',
                'ref_spec_number' => 'MIXED-001'
            ],
            'measurement_points' => [
                // Quantitative measurement
                [
                    'setup' => [
                        'name' => 'Wire Diameter',
                        'name_id' => 'wire_diameter',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => [
                        'per_sample_setting' => [
                            'is_raw_data' => true,
                            'pre_processing_formula_name' => null
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => 'mm',
                        'value' => 1.5,
                        'tolerance_minus' => 0.1,
                        'tolerance_plus' => 0.1
                    ]
                ],
                // Qualitative measurement
                [
                    'setup' => [
                        'name' => 'Wire Color',
                        'name_id' => 'wire_color',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Color Match',
                            'options' => ['Exact Match', 'Close Match', 'No Match'],
                            'passing_criteria' => 'Exact Match or Close Match'
                        ]
                    ],
                    'rule_evaluation_setting' => null
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);
        
        $product = Product::where('ref_spec_number', 'MIXED-001')->first();
        $this->assertNotNull($product);
        
        // Verify both measurement types exist
        $this->assertCount(2, $product->measurement_points);
        $this->assertEquals('QUANTITATIVE', $product->measurement_points[0]['setup']['nature']);
        $this->assertEquals('QUALITATIVE', $product->measurement_points[1]['setup']['nature']);
    }

    /** @test */
    public function test_qualitative_measurement_requires_qualitative_setting()
    {
        $invalidData = [
            'basic_info' => [
                'product_category_id' => $this->tubeTestCategory->id,
                'product_name' => 'VO',
                'ref_spec_number' => 'INVALID-001'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Visual Check',
                        'name_id' => 'visual_check',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        // Missing qualitative_setting!
                    ],
                    'rule_evaluation_setting' => null
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $invalidData);

        $response->assertStatus(400);
    }

    /** @test */
    public function test_qualitative_measurement_must_have_null_rule_evaluation()
    {
        $invalidData = [
            'basic_info' => [
                'product_category_id' => $this->tubeTestCategory->id,
                'product_name' => 'VO',
                'ref_spec_number' => 'INVALID-002'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Visual Check',
                        'name_id' => 'visual_check',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Quality',
                            'options' => ['Good', 'Bad']
                        ]
                    ],
                    // Should be null for qualitative!
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'value' => 10
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $invalidData);

        $response->assertStatus(400);
    }

    /** @test */
    public function test_qualitative_measurement_evaluation_type_should_be_skip_check()
    {
        $invalidData = [
            'basic_info' => [
                'product_category_id' => $this->tubeTestCategory->id,
                'product_name' => 'VO',
                'ref_spec_number' => 'INVALID-003'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Visual Check',
                        'name_id' => 'visual_check',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    // Should be SKIP_CHECK for qualitative!
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Quality',
                            'options' => ['Good', 'Bad']
                        ]
                    ],
                    'rule_evaluation_setting' => null
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $invalidData);

        $response->assertStatus(400);
    }

    /** @test */
    public function test_can_retrieve_qualitative_product()
    {
        // Create qualitative product
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->tubeTestCategory->id,
            'product_name' => 'VO',
            'ref_spec_number' => 'RETRIEVE-001',
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Visual Check',
                        'name_id' => 'visual_check',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Quality',
                            'options' => ['Good', 'Bad'],
                            'passing_criteria' => 'Must be Good'
                        ]
                    ],
                    'rule_evaluation_setting' => null
                ]
            ],
            'measurement_groups' => []
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products/' . $product->product_id);

        $response->assertStatus(200);
        
        $measurementPoints = $response->json('data.measurement_points');
        $this->assertCount(1, $measurementPoints);
        $this->assertEquals('QUALITATIVE', $measurementPoints[0]['setup']['nature']);
        $this->assertEquals('SKIP_CHECK', $measurementPoints[0]['evaluation_type']);
        $this->assertNull($measurementPoints[0]['rule_evaluation_setting']);
        $this->assertArrayHasKey('qualitative_setting', $measurementPoints[0]['evaluation_setting']);
    }

    /** @test */
    public function test_qualitative_measurement_structure_is_valid()
    {
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->tubeTestCategory->id,
            'product_name' => 'VO',
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Color Check',
                        'name_id' => 'color_check',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUALITATIVE'
                    ],
                    'variables' => [],
                    'pre_processing_formulas' => [],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => [
                        'qualitative_setting' => [
                            'label' => 'Color Match',
                            'options' => ['Perfect', 'Good', 'Fair', 'Poor'],
                            'passing_criteria' => 'Perfect or Good'
                        ]
                    ],
                    'rule_evaluation_setting' => null
                ]
            ],
            'measurement_groups' => []
        ]);

        $measurementPoint = $product->measurement_points[0];
        
        // Verify structure
        $this->assertEquals('QUALITATIVE', $measurementPoint['setup']['nature']);
        $this->assertEquals('SKIP_CHECK', $measurementPoint['evaluation_type']);
        $this->assertNull($measurementPoint['rule_evaluation_setting']);
        
        $qualSetting = $measurementPoint['evaluation_setting']['qualitative_setting'];
        $this->assertArrayHasKey('label', $qualSetting);
        $this->assertArrayHasKey('options', $qualSetting);
        $this->assertArrayHasKey('passing_criteria', $qualSetting);
        $this->assertIsArray($qualSetting['options']);
        $this->assertGreaterThan(0, count($qualSetting['options']));
    }

    /** @test */
    public function test_can_search_qualitative_products()
    {
        // Create some qualitative products
        Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->tubeTestCategory->id,
            'product_name' => 'VO',
            'ref_spec_number' => 'VISUAL-QUAL-001',
            'measurement_points' => $this->getSimpleQualitativeMeasurementPoint(),
            'measurement_groups' => []
        ]);

        Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->tubeTestCategory->id,
            'product_name' => 'COT',
            'ref_spec_number' => 'COLOR-QUAL-002',
            'measurement_points' => $this->getSimpleQualitativeMeasurementPoint(),
            'measurement_groups' => []
        ]);

        // Search products
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products?query=QUAL');

        $response->assertStatus(200);
        
        $docs = $response->json('data.docs');
        $this->assertGreaterThanOrEqual(2, count($docs));
    }

    /**
     * Helper method to get simple qualitative measurement point
     */
    private function getSimpleQualitativeMeasurementPoint(): array
    {
        return [
            [
                'setup' => [
                    'name' => 'Visual Inspection',
                    'name_id' => 'visual_inspection',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE'
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Quality Grade',
                        'options' => ['Excellent', 'Good', 'Fair', 'Poor'],
                        'passing_criteria' => 'Excellent or Good'
                    ]
                ],
                'rule_evaluation_setting' => null
            ]
        ];
    }
}

