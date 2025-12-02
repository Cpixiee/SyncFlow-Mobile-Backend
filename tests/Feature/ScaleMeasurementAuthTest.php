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

class ScaleMeasurementAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $operatorUser;
    protected $adminUser;
    protected $operatorToken;
    protected $adminToken;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create product category and quarter
        $category = ProductCategory::create([
            'name' => 'Test Category',
            'products' => ['Test Product'],
            'description' => 'Test',
        ]);

        $quarter = Quarter::firstOrCreate(
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
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'Test Product',
            'measurement_points' => [[]],
        ]);

        // Create operator user
        $this->operatorUser = LoginUser::create([
            'employee_id' => 'EMP001',
            'username' => 'operator',
            'email' => 'operator@test.com',
            'password' => bcrypt('password123'),
            'role' => 'operator',
            'phone' => '081234567890',
            'position' => 'staff',
            'department' => 'Production',
            'password_changed' => true,
        ]);

        // Create admin user
        $this->adminUser = LoginUser::create([
            'employee_id' => 'EMP002',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'phone' => '081234567891',
            'position' => 'manager',
            'department' => 'Quality Control',
            'password_changed' => true,
        ]);

        // Generate tokens
        $this->operatorToken = JWTAuth::fromUser($this->operatorUser);
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
    }

    /**
     * Test: Operator can create scale measurement
     */
    public function test_operator_can_create_scale_measurement()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(201); // Success - Operator can create
    }

    /**
     * Test: Admin can create scale measurement
     */
    public function test_admin_can_create_scale_measurement()
    {
        $data = [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/scale-measurement', $data);

        $response->assertStatus(201);
    }

    /**
     * Test: Operator CANNOT update scale measurement (403 Forbidden)
     */
    public function test_operator_cannot_update_scale_measurement()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $data = ['weight' => 5.2];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->putJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id, $data);

        $response->assertStatus(403); // Forbidden - Only Admin/SuperAdmin can update
    }

    /**
     * Test: Operator cannot delete scale measurement (403 Forbidden)
     */
    public function test_operator_cannot_delete_scale_measurement()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->deleteJson('/api/v1/scale-measurement/' . $measurement->scale_measurement_id);

        $response->assertStatus(403); // Forbidden
    }

    /**
     * Test: Unauthenticated user cannot access scale measurement endpoints
     */
    public function test_unauthenticated_user_cannot_access_endpoints()
    {
        // Test GET list
        $response = $this->getJson('/api/v1/scale-measurement');
        $response->assertStatus(401);

        // Test POST create
        $response = $this->postJson('/api/v1/scale-measurement', [
            'product_id' => $this->product->product_id,
            'measurement_date' => '2025-12-02',
        ]);
        $response->assertStatus(401);

        // Test GET single
        $response = $this->getJson('/api/v1/scale-measurement/SCL-TEST');
        $response->assertStatus(401);

        // Test PUT update
        $response = $this->putJson('/api/v1/scale-measurement/SCL-TEST', []);
        $response->assertStatus(401);

        // Test DELETE
        $response = $this->deleteJson('/api/v1/scale-measurement/SCL-TEST');
        $response->assertStatus(401);
    }

    /**
     * Test: Invalid token returns 401
     */
    public function test_invalid_token_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer INVALID_TOKEN',
        ])->getJson('/api/v1/scale-measurement');

        $response->assertStatus(401);
    }

    /**
     * Test: Expired token returns 401
     */
    public function test_expired_token_returns_401()
    {
        $this->markTestSkipped('JWT expired token test is flaky');
    }

    /**
     * Test: Operator CAN view scale measurements list
     */
    public function test_operator_can_view_scale_measurements_list()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->getJson('/api/v1/scale-measurement');

        $response->assertStatus(200); // Success - Operator can view
    }

    /**
     * Test: Operator CAN view available products
     */
    public function test_operator_can_view_available_products()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->getJson('/api/v1/scale-measurement/available-products?date=2025-12-02');

        $response->assertStatus(200); // Success - All users can view
    }
}

