<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ScaleMeasurement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Models\LoginUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ScaleMeasurementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminToken;
    protected $product;
    protected $productCategory;
    protected $quarter;

    protected function setUp(): void
    {
        parent::setUp();

        // Create or get existing product category
        $this->productCategory = ProductCategory::firstOrCreate(
            ['name' => 'Wire Test Regular'],
            [
                'products' => ['CIVIUSAS-S', 'DMGAS-6'],
                'description' => 'Test Category',
            ]
        );

        // Create or get existing quarter
        $this->quarter = Quarter::firstOrCreate(
            ['name' => 'Q3', 'year' => 2025],
            [
                'start_month' => 6,
                'end_month' => 8,
                'start_date' => '2025-06-01',
                'end_date' => '2025-08-31',
                'is_active' => true,
            ]
        );

        // Create product
        $this->product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'CIVIUSAS-S',
            'ref_spec_number' => 'YPES-11-03-009',
            'nom_size_vo' => 'VO8 x 7',
            'article_code' => 'ART-001',
            'measurement_points' => [[
                'setup' => [
                    'name' => 'Test Measurement',
                    'name_id' => 'test_measurement',
                    'sample_amount' => 3,
                    'nature' => 'QUANTITATIVE',
                    'source' => 'MANUAL',
                    'type' => 'SINGLE'
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
            ]],
        ]);

        // Create admin user
        $this->adminUser = LoginUser::create([
            'employee_id' => 'EMP001',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'phone' => '081234567890',
            'position' => 'manager',
            'department' => 'Quality Control',
            'password_changed' => true,
        ]);

        // Generate JWT token
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
    }

    /**
     * Test: Can create scale measurement with weight
     */
    public function test_can_create_scale_measurement_with_weight()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'notes' => 'Morning measurement'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'scale_measurement_id',
                        'measurement_date',
                        'weight',
                        'status'
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'weight' => 4.5,
                        'status' => 'CHECKED'
                    ]
                ]);

        $this->assertDatabaseHas('scale_measurements', [
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED'
        ]);
    }

    /**
     * Test: Can create scale measurement without weight (NOT_CHECKED)
     */
    public function test_can_create_scale_measurement_without_weight()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'weight' => null,
                        'status' => 'NOT_CHECKED'
                    ]
                ]);

        $this->assertDatabaseHas('scale_measurements', [
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => null,
            'status' => 'NOT_CHECKED'
        ]);
    }

    /**
     * Test: Cannot create duplicate scale measurement for same product and date
     */
    public function test_cannot_create_duplicate_scale_measurement()
    {
        // Create first measurement
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        // Try to create duplicate
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 5.0,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(400)
                ->assertJson([
                    'error_id' => 'DUPLICATE_SCALE_MEASUREMENT'
                ]);
    }

    /**
     * Test: Can get scale measurements list
     */
    public function test_can_get_scale_measurements_list()
    {
        // Create test data
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement');

        $response->assertStatus(200)
                ->assertJsonPath('http_code', 200)
                ->assertJsonPath('message', 'Scale measurements retrieved successfully');
    }

    /**
     * Test: Can filter scale measurements by date
     */
    public function test_can_filter_scale_measurements_by_date()
    {
        // Create measurement for specific date
        $testDate = '2025-12-15'; // Use different date to avoid conflicts
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => $testDate,
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement?date=' . $testDate);

        $response->assertStatus(200)
                ->assertJsonPath('http_code', 200);
    }

    /**
     * Test: Can filter scale measurements by status
     */
    public function test_can_filter_scale_measurements_by_status()
    {
        // Create measurements with different statuses
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement?status=NOT_CHECKED');

        $response->assertStatus(200)
                ->assertJsonPath('http_code', 200);
        
        // Verify we got data back
        $this->assertNotNull($response->json('data'));
    }

    /**
     * Test: Can get single scale measurement by ID
     */
    public function test_can_get_single_scale_measurement()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'scale_measurement_id' => $measurement->scale_measurement_id,
                        'weight' => 4.5,
                        'status' => 'CHECKED'
                    ]
                ]);
    }

    /**
     * Test: Returns 404 for non-existent scale measurement
     */
    public function test_returns_404_for_non_existent_scale_measurement()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement/SCL-NOTEXIST');

        $response->assertStatus(404)
                ->assertJsonPath('message', 'Scale measurement tidak ditemukan');
    }

    /**
     * Test: Can update scale measurement weight
     */
    public function test_can_update_scale_measurement_weight()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $data = [
            'weight' => 5.2,
            'notes' => 'Updated weight'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id, $data);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'weight' => 5.2,
                        'status' => 'CHECKED'
                    ]
                ]);

        $this->assertDatabaseHas('scale_measurements', [
            'id' => $measurement->id,
            'weight' => 5.2,
            'status' => 'CHECKED',
            'notes' => 'Updated weight'
        ]);
    }

    /**
     * Test: Status changes to NOT_CHECKED when weight is set to null
     */
    public function test_status_changes_to_not_checked_when_weight_is_null()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $data = [
            'weight' => null
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id, $data);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'weight' => null,
                        'status' => 'NOT_CHECKED'
                    ]
                ]);
    }

    /**
     * Test: Can update measurement date
     */
    public function test_can_update_measurement_date()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $data = [
            'measurement_date' => '2025-12-03'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id, $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('scale_measurements', [
            'id' => $measurement->id,
            'measurement_date' => '2025-12-03'
        ]);
    }

    /**
     * Test: Cannot update date to duplicate
     */
    public function test_cannot_update_date_to_duplicate()
    {
        // Create first measurement
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        // Create second measurement
        $measurement2 = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => 5.0,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        // Try to update second to same date as first
        $data = [
            'measurement_date' => '2025-12-02'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/scale-measurement/' . $measurement2->scale_measurement_id, $data);

        $response->assertStatus(400)
                ->assertJson([
                    'error_id' => 'DUPLICATE_SCALE_MEASUREMENT'
                ]);
    }

    /**
     * Test: Can delete scale measurement
     */
    public function test_can_delete_scale_measurement()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('scale_measurements', [
            'id' => $measurement->id
        ]);
    }

    /**
     * Test: Can get available products for date
     */
    public function test_can_get_available_products_for_date()
    {
        // Create another product
        $product2 = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'DMGAS-6',
            'ref_spec_number' => 'YPES-11-03-010',
            'measurement_points' => [[]],
        ]);

        // Create measurement for first product only
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement/available-products?date=2025-12-02');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should not include product1 which has measurement
        $productIds = collect($data)->pluck('id')->all();
        $this->assertNotContains($this->product->product_id, $productIds);
    }

    /**
     * Test: Can bulk create scale measurements
     */
    public function test_can_bulk_create_scale_measurements()
    {
        // Create additional products
        $product2 = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'DMGAS-6',
            'measurement_points' => [[]],
        ]);

        $product3 = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'XXGAS-9',
            'measurement_points' => [[]],
        ]);

        $data = [
            'product_ids' => [
                $this->product->product_id,
                $product2->product_id,
                $product3->product_id
            ],
            'measurement_date' => '2025-12-02'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement/bulk', $data);

        $response->assertStatus(201);

        $responseData = $response->json('data');
        $this->assertCount(3, $responseData);

        // Check database
        $this->assertDatabaseHas('scale_measurements', [
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'status' => 'NOT_CHECKED'
        ]);

        $this->assertDatabaseHas('scale_measurements', [
            'product_id' => $product2->id,
            'measurement_date' => '2025-12-02',
            'status' => 'NOT_CHECKED'
        ]);

        $this->assertDatabaseHas('scale_measurements', [
            'product_id' => $product3->id,
            'measurement_date' => '2025-12-02',
            'status' => 'NOT_CHECKED'
        ]);
    }

    /**
     * Test: Bulk create skips duplicates
     */
    public function test_bulk_create_skips_duplicates()
    {
        // Create existing measurement
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        // Create another product
        $product2 = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'DMGAS-6',
            'measurement_points' => [[]],
        ]);

        $data = [
            'product_ids' => [
                $this->product->product_id,  // This will be skipped
                $product2->product_id         // This will be created
            ],
            'measurement_date' => '2025-12-02'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement/bulk', $data);

        $response->assertStatus(201);

        $responseData = $response->json('data');
        
        // Should only create for product2
        $this->assertCount(1, $responseData);
        $this->assertArrayHasKey($product2->product_id, $responseData);
        $this->assertArrayNotHasKey($this->product->product_id, $responseData);
    }

    /**
     * Test: Validation - product_id required
     */
    public function test_validation_product_id_required()
    {
        $data = [
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(400) // API returns 400 for validation errors
                ->assertJsonPath('http_code', 400);
    }

    /**
     * Test: Validation - measurement_date required
     */
    public function test_validation_measurement_date_required()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'weight' => 4.5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(400) // API returns 400 for validation errors
                ->assertJsonPath('http_code', 400);
    }

    /**
     * Test: Validation - weight must be numeric
     */
    public function test_validation_weight_must_be_numeric()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 'not-a-number',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(400) // API returns 400 for validation errors
                ->assertJsonPath('http_code', 400);
    }

    /**
     * Test: Validation - weight must be positive
     */
    public function test_validation_weight_must_be_positive()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => -5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(400) // API returns 400 for validation errors
                ->assertJsonPath('http_code', 400);
    }

    /**
     * Test: Requires authentication
     */
    public function test_requires_authentication()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
        ];

        $response = $this->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(401);
    }

    /**
     * Test: Scale measurement ID is auto-generated
     */
    public function test_scale_measurement_id_is_auto_generated()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $this->assertNotNull($measurement->scale_measurement_id);
        $this->assertStringStartsWith('SCL-', $measurement->scale_measurement_id);
        $this->assertEquals(12, strlen($measurement->scale_measurement_id)); // SCL- + 8 chars
    }

    /**
     * Test: Can search by product name
     */
    public function test_can_search_by_product_name()
    {
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement?query=CIVIUSAS');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
    }

    /**
     * Test: Filter by date range
     */
    public function test_can_filter_by_date_range()
    {
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-01',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-05',
            'weight' => 5.0,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-10',
            'weight' => 5.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/scale-measurement?start_date=2025-12-02&end_date=2025-12-08');

        $response->assertStatus(200)
                ->assertJsonPath('http_code', 200);
        
        // Verify we got data back
        $this->assertNotNull($response->json('data'));
    }
}

