<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Quarter;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\ProductMeasurement;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductMeasurementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminToken;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = LoginUser::factory()->create([
            'username' => 'testadmin',
            'role' => 'admin',
            'password' => bcrypt('testpassword')
        ]);
        
        // Generate JWT token
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
        
        // Create test data
        $quarter = Quarter::create([
            'name' => 'Q4',
            'year' => 2024,
            'start_month' => 10,
            'end_month' => 12,
            'start_date' => '2024-10-01',
            'end_date' => '2024-12-31',
            'is_active' => true
        ]);
        
        $productCategory = ProductCategory::create([
            'name' => 'Tube Test',
            'products' => ['VO', 'COT', 'COTO'],
            'description' => 'Test category'
        ]);

        $this->product = Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Inside Diameter',
                        'name_id' => 'inside_diameter',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => [
                        'per_sample_setting' => [
                            'is_raw_data' => true
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => 'mm',
                        'value' => 14.4,
                        'tolerance_minus' => 0.3,
                        'tolerance_plus' => 0.3
                    ]
                ]
            ]
        ]);
    }

    public function test_can_submit_measurement_results()
    {
        $measurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 5,
            'status' => 'PENDING'
        ]);

        $measurementData = [
            'measurement_results' => [
                [
                    'measurement_item_name_id' => 'inside_diameter',
                    'variable_values' => [],
                    'samples' => [
                        [
                            'sample_index' => 1,
                            'single_value' => 14.2,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 2,
                            'single_value' => 14.3,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 3,
                            'single_value' => 14.1,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 4,
                            'single_value' => 14.5,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 5,
                            'single_value' => 14.4,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/product-measurement/' . $measurement->measurement_id . '/submit', $measurementData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'status',
                        'samples'
                    ]
                ]);

        // Check that measurement was updated
        $measurement->refresh();
        $this->assertEquals('COMPLETED', $measurement->status);
        $this->assertNotNull($measurement->measurement_results);
    }

    public function test_can_get_measurement_by_id()
    {
        $measurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 5,
            'status' => 'COMPLETED',
            'overall_result' => true,
            'measurement_results' => [
                'test_results' => 'sample_data'
            ],
            'measured_by' => $this->adminUser->id,
            'measured_at' => now()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/product-measurement/' . $measurement->measurement_id);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'measurement_id',
                        'product_id',
                        'batch_number',
                        'sample_count',
                        'measurement_type',
                        'product_status',
                        'measurement_status',
                        'sample_status',
                        'overall_result',
                        'measurement_results',
                        'measured_by',
                        'measured_at',
                        'notes',
                        'created_at'
                    ]
                ]);
    }

    public function test_measurement_not_found()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/product-measurement/INVALID-ID');

        $response->assertStatus(404)
                ->assertJson([
                    'http_code' => 404,
                    'message' => 'Product measurement tidak ditemukan'
                ]);
    }

    public function test_validation_error_on_invalid_measurement_data()
    {
        $measurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 5,
            'status' => 'PENDING'
        ]);

        $invalidData = [
            'measurement_results' => [] // Empty array
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/product-measurement/' . $measurement->measurement_id . '/submit', $invalidData);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data'
                ]);
    }

    public function test_auto_generate_measurement_id()
    {
        $measurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 5,
            'status' => 'PENDING'
        ]);

        $this->assertNotEmpty($measurement->measurement_id);
        $this->assertStringStartsWith('MSR-', $measurement->measurement_id);
        $this->assertEquals(12, strlen($measurement->measurement_id)); // MSR- + 8 chars
    }

    public function test_product_measurement_relationship()
    {
        $measurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 5,
            'status' => 'PENDING'
        ]);

        $this->assertEquals($this->product->id, $measurement->product->id);
        $this->assertEquals($this->product->product_name, $measurement->product->product_name);
    }

    public function test_measurement_with_joint_evaluation()
    {
        // Create product with JOINT evaluation
        $productWithJoint = Product::create([
            'quarter_id' => $this->product->quarter_id,
            'product_category_id' => $this->product->product_category_id,
            'product_name' => 'COT',
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Weight Measurement',
                        'name_id' => 'weight_measurement',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'JOINT',
                    'evaluation_setting' => [
                        'joint_setting' => [
                            'formulas' => [
                                [
                                    'name' => 'AVG_WEIGHT',
                                    'formula' => 'AVG(WEIGHT)',
                                    'is_final_value' => false
                                ],
                                [
                                    'name' => 'FORCE',
                                    'formula' => 'AVG_WEIGHT * 9.80665',
                                    'is_final_value' => true
                                ]
                            ]
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => 'N',
                        'value' => 100.0,
                        'tolerance_minus' => 5.0,
                        'tolerance_plus' => 5.0
                    ]
                ]
            ]
        ]);

        $measurement = ProductMeasurement::create([
            'product_id' => $productWithJoint->id,
            'sample_count' => 3,
            'status' => 'PENDING'
        ]);

        $measurementData = [
            'measurement_results' => [
                [
                    'measurement_item_name_id' => 'weight_measurement',
                    'variable_values' => [],
                    'samples' => [
                        [
                            'sample_index' => 1,
                            'single_value' => 10.2,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 2,
                            'single_value' => 10.1,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 3,
                            'single_value' => 10.3,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ]
                    ],
                    'joint_setting_formula_values' => [
                        [
                            'name' => 'AVG_WEIGHT',
                            'value' => 10.2,
                            'is_final_value' => false
                        ],
                        [
                            'name' => 'FORCE',
                            'value' => 100.0,
                            'is_final_value' => true
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/product-measurement/' . $measurement->measurement_id . '/submit', $measurementData);

        $response->assertStatus(200);

        // Check measurement was processed
        $measurement->refresh();
        $this->assertEquals('COMPLETED', $measurement->status);
        $this->assertNotNull($measurement->measurement_results);
    }
}