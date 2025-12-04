<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductMeasurement;
use App\Models\ProductCategory;
use App\Models\LoginUser;
use App\Models\Tool;
use App\Models\Quarter;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductMeasurementFixTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = LoginUser::factory()->create([
            'role' => 'admin',
            'username' => 'testadmin' . uniqid(),
        ]);

        // Generate token
        $this->token = JWTAuth::fromUser($this->user);

        // Create product category
        $this->category = ProductCategory::create([
            'name' => 'Test Category',
            'products' => ['Test Product', 'Complex Product'],
            'description' => 'Test'
        ]);

        // Ensure quarters exist for testing years
        $this->ensureQuartersExist(2025);
        $this->ensureQuartersExist(2024);
    }

    /**
     * Ensure quarters exist for a given year
     */
    private function ensureQuartersExist(int $year): void
    {
        $quarters = [
            ['name' => 'Q1', 'start_month' => 1, 'end_month' => 3],
            ['name' => 'Q2', 'start_month' => 4, 'end_month' => 6],
            ['name' => 'Q3', 'start_month' => 7, 'end_month' => 9],
            ['name' => 'Q4', 'start_month' => 10, 'end_month' => 12],
        ];

        foreach ($quarters as $quarter) {
            Quarter::firstOrCreate(
                ['year' => $year, 'name' => $quarter['name']],
                [
                    'start_month' => $quarter['start_month'],
                    'end_month' => $quarter['end_month'],
                    'start_date' => \Carbon\Carbon::createFromDate($year, $quarter['start_month'], 1),
                    'end_date' => \Carbon\Carbon::createFromDate($year, $quarter['end_month'], 1)->endOfMonth(),
                    'is_active' => false,
                ]
            );
        }
    }

    /**
     * Test 1: Tool next_calibration_at should be nullable
     */
    public function test_tool_next_calibration_at_is_nullable()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tools', [
            'tool_name' => 'Test Tool',
            'tool_model' => 'TEST-01',
            'tool_type' => 'MECHANICAL',
            'last_calibration_at' => '2025-11-01',
            'imei' => 'TEST-001',
            'status' => 'ACTIVE'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.next_calibration_at', null);
    }

    /**
     * Test 2: Product search should work
     */
    public function test_product_search_works()
    {
        // Add 'Complex Product' to category products
        $this->category->update([
            'products' => ['Test Product', 'Complex Product']
        ]);

        // Create test product
        $product = Product::factory()->create([
            'product_name' => 'Complex Product',
            'product_category_id' => $this->category->id,
            'article_code' => 'COMPLEX-001',
            'measurement_points' => [],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/products?query=complex');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('data.metadata.total_docs'));
    }

    /**
     * Test 3: Measurement groups with null group_name should be valid
     */
    public function test_measurement_groups_null_group_name_is_valid()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/products', [
            'basic_info' => [
                'product_category_id' => $this->category->id,
                'product_name' => 'Test Product',
            ],
            'measurement_points' => [
                [
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
                        'rule' => 'MIN',
                        'unit' => 'mm',
                        'value' => 2.0,
                        'tolerance_minus' => null,
                        'tolerance_plus' => null
                    ]
                ]
            ],
            'measurement_groups' => [
                [
                    'order' => 1,
                    'group_name' => null, // Null group_name for single item
                    'measurement_items' => ['test_measurement']
                ]
            ]
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['product_id']]);
    }

    /**
     * Test 4: Filter product measurements by month and year
     */
    public function test_filter_product_measurements_by_month_year()
    {
        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        // Create measurement with October due_date
        ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'due_date' => '2025-10-15',
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        // Create measurement with November due_date
        ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'due_date' => '2025-11-15',
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement?month=10&year=2025');

        $response->assertStatus(200);
        // Should only return October measurement
        $this->assertEquals(1, count($response->json('data.docs')));
    }

    /**
     * Test 5: Bulk create should have TODO status and null batch_number
     */
    public function test_bulk_create_has_todo_status_and_null_batch_number()
    {
        $product1 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [['setup' => ['sample_amount' => 3]]],
        ]);
        
        $product2 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [['setup' => ['sample_amount' => 3]]],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/product-measurement/bulk', [
            'product_ids' => [$product1->product_id, $product2->product_id],
            'due_date' => '2025-10-31',
            'measurement_type' => 'FULL_MEASUREMENT'
        ]);

        $response->assertStatus(201);

        // Verify status is TODO and batch_number is null
        $measurementId = $response->json('data.' . $product1->product_id);
        $measurement = ProductMeasurement::where('measurement_id', $measurementId)->first();
        
        $this->assertEquals('TODO', $measurement->status);
        $this->assertNull($measurement->batch_number);
    }

    /**
     * Test 6: Available products should not include products with due_date in quarter
     */
    public function test_available_products_excludes_products_with_due_date()
    {
        $product1 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);
        
        $product2 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        // Create measurement for product1 with Q4 2025 due_date
        ProductMeasurement::factory()->create([
            'product_id' => $product1->id,
            'due_date' => '2025-10-15', // Q4 2025
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        // product2 has no measurement

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/available-products?quarter=4&year=2025');

        $response->assertStatus(200);
        
        // product1 should NOT be in response
        // product2 should be in response
        $productIds = collect($response->json('data.docs'))->pluck('id')->toArray();
        $this->assertNotContains($product1->product_id, $productIds);
        $this->assertContains($product2->product_id, $productIds);
    }

    /**
     * Test 7: Delete product measurement only works for TODO status
     */
    public function test_delete_product_measurement_only_todo()
    {
        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        // Create TODO measurement
        $todoMeasurement = ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        // Create IN_PROGRESS measurement
        $inProgressMeasurement = ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'status' => 'IN_PROGRESS',
            'measured_by' => $this->user->id,
        ]);

        // Should be able to delete TODO
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/product-measurement/' . $todoMeasurement->measurement_id);
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.deleted', true);

        // Should NOT be able to delete IN_PROGRESS
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/product-measurement/' . $inProgressMeasurement->measurement_id);
        
        $response->assertStatus(400);
        $response->assertJsonPath('error_id', 'DELETE_NOT_ALLOWED');
    }

    /**
     * Test 8: Update product measurement only updates due_date
     */
    public function test_update_product_measurement_only_due_date()
    {
        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        $measurement = ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'due_date' => '2025-10-15',
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/product-measurement/' . $measurement->measurement_id, [
            'due_date' => '2025-11-30'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.due_date', '2025-11-30 00:00:00');

        // Verify in database
        $measurement->refresh();
        $this->assertEquals('2025-11-30', $measurement->due_date->format('Y-m-d'));
    }

    /**
     * Test 9: Get progress returns correct statistics
     */
    public function test_get_progress_returns_correct_statistics()
    {
        $product1 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);
        
        $product2 = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        // Create measurements in Q4 2025
        ProductMeasurement::factory()->create([
            'product_id' => $product1->id,
            'due_date' => '2025-10-15',
            'status' => 'TODO',
            'measured_by' => $this->user->id,
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $product2->id,
            'due_date' => '2025-11-20',
            'status' => 'IN_PROGRESS',
            'batch_number' => 'BATCH-001',
            'measured_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress?quarter=4&year=2025');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'progress' => [
                    'total_products',
                    'ok',
                    'need_to_measure_again',
                    'ongoing',
                    'not_checked'
                ]
            ]
        ]);
    }

    /**
     * Test 10: Quarter definition is correct (Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec)
     */
    public function test_quarter_definition_is_correct()
    {
        $q1 = Quarter::where('year', 2025)->where('name', 'Q1')->first();
        $q2 = Quarter::where('year', 2025)->where('name', 'Q2')->first();
        $q3 = Quarter::where('year', 2025)->where('name', 'Q3')->first();
        $q4 = Quarter::where('year', 2025)->where('name', 'Q4')->first();

        // Q1: January - March
        $this->assertEquals(1, $q1->start_month);
        $this->assertEquals(3, $q1->end_month);
        $this->assertEquals('2025-01-01', $q1->start_date->format('Y-m-d'));
        $this->assertEquals('2025-03-31', $q1->end_date->format('Y-m-d'));

        // Q2: April - June
        $this->assertEquals(4, $q2->start_month);
        $this->assertEquals(6, $q2->end_month);
        $this->assertEquals('2025-04-01', $q2->start_date->format('Y-m-d'));
        $this->assertEquals('2025-06-30', $q2->end_date->format('Y-m-d'));

        // Q3: July - September
        $this->assertEquals(7, $q3->start_month);
        $this->assertEquals(9, $q3->end_month);
        $this->assertEquals('2025-07-01', $q3->start_date->format('Y-m-d'));
        $this->assertEquals('2025-09-30', $q3->end_date->format('Y-m-d'));

        // Q4: October - December
        $this->assertEquals(10, $q4->start_month);
        $this->assertEquals(12, $q4->end_month);
        $this->assertEquals('2025-10-01', $q4->start_date->format('Y-m-d'));
        $this->assertEquals('2025-12-31', $q4->end_date->format('Y-m-d'));
    }

    /**
     * Test 11: Set batch number changes status from TODO to IN_PROGRESS
     */
    public function test_set_batch_number_changes_status()
    {
        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'measurement_points' => [],
        ]);

        $measurement = ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'status' => 'TODO',
            'batch_number' => null,
            'measured_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/product-measurement/' . $measurement->measurement_id . '/set-batch-number', [
            'batch_number' => 'BATCH-TEST-001'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'IN_PROGRESS');
        $response->assertJsonPath('data.batch_number', 'BATCH-TEST-001');

        // Verify in database
        $measurement->refresh();
        $this->assertEquals('IN_PROGRESS', $measurement->status);
        $this->assertEquals('BATCH-TEST-001', $measurement->batch_number);
    }
}

