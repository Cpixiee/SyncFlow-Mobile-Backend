<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMeasurement;
use App\Models\Issue;
use App\Models\LoginUser;
use App\Models\Quarter;
use App\Enums\IssueStatus;
use App\Enums\MeasurementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected $token;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and get token
        $this->user = LoginUser::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        $this->token = $response->json('data.token');

        // Generate quarters for testing
        for ($year = 2024; $year <= 2026; $year++) {
            Quarter::generateQuartersForYear($year);
        }
    }

    /** @test */
    public function test_progress_category_returns_correct_structure()
    {
        // Create categories
        $tubeCategory = ProductCategory::factory()->create(['name' => 'Tube Test']);
        $wireCategory = ProductCategory::factory()->create(['name' => 'Wire Test Reguler']);

        // Create products for each category
        $tubeProduct1 = Product::factory()->create(['product_category_id' => $tubeCategory->id]);
        $tubeProduct2 = Product::factory()->create(['product_category_id' => $tubeCategory->id]);
        $wireProduct1 = Product::factory()->create(['product_category_id' => $wireCategory->id]);

        // Create measurements for Q3 2025 (Jul-Sep)
        ProductMeasurement::factory()->create([
            'product_id' => $tubeProduct1->id,
            'due_date' => '2025-07-15',
            'status' => 'COMPLETED',
            'overall_result' => true,
            'batch_number' => 'BATCH-001',
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $tubeProduct2->id,
            'due_date' => '2025-08-15',
            'status' => 'TODO',
            'overall_result' => null,
            'batch_number' => null,
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $wireProduct1->id,
            'due_date' => '2025-09-15',
            'status' => 'IN_PROGRESS',
            'overall_result' => null,
            'batch_number' => 'BATCH-002',
        ]);

        // Make request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-category?quarter=3&year=2025');

        // Assert response structure
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'http_code',
            'message',
            'data' => [
                'perCategory' => [
                    '*' => [
                        'category_id',
                        'category_name',
                        'product_result' => ['ok', 'ng', 'total'],
                        'product_checking' => ['todo', 'checked', 'done', 'total'],
                    ]
                ]
            ]
        ]);

        // Assert data
        $data = $response->json('data.perCategory');
        $this->assertCount(2, $data); // 2 categories

        // Find Tube Test category
        $tubeData = collect($data)->firstWhere('category_name', 'Tube Test');
        $this->assertNotNull($tubeData);
        $this->assertEquals(1, $tubeData['product_result']['ok']);
        $this->assertEquals(0, $tubeData['product_result']['ng']);
        $this->assertEquals(1, $tubeData['product_checking']['todo']);
        $this->assertEquals(1, $tubeData['product_checking']['done']);

        // Find Wire Test category
        $wireData = collect($data)->firstWhere('category_name', 'Wire Test Reguler');
        $this->assertNotNull($wireData);
        $this->assertEquals(0, $wireData['product_result']['ok']);
        $this->assertEquals(0, $wireData['product_result']['ng']);
        $this->assertEquals(1, $wireData['product_checking']['checked']);
    }

    /** @test */
    public function test_progress_category_filters_by_quarter()
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['product_category_id' => $category->id]);

        // Create measurements in different quarters
        ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'due_date' => '2025-02-15', // Q1
            'status' => 'COMPLETED',
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $product->id,
            'due_date' => '2025-08-15', // Q3
            'status' => 'COMPLETED',
        ]);

        // Request Q3 only
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-category?quarter=3&year=2025');

        $response->assertStatus(200);
        $data = $response->json('data.perCategory');
        
        // Should only return Q3 data
        $categoryData = $data[0];
        $this->assertEquals(1, $categoryData['product_result']['total']); // Only 1 measurement in Q3
    }

    /** @test */
    public function test_progress_all_returns_correct_counts()
    {
        $category = ProductCategory::factory()->create();
        $product1 = Product::factory()->create(['product_category_id' => $category->id]);
        $product2 = Product::factory()->create(['product_category_id' => $category->id]);
        $product3 = Product::factory()->create(['product_category_id' => $category->id]);

        // Create measurements with different statuses for Q3 2025
        ProductMeasurement::factory()->create([
            'product_id' => $product1->id,
            'due_date' => '2025-07-15',
            'status' => 'COMPLETED', // DONE
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $product2->id,
            'due_date' => '2025-08-15',
            'status' => 'IN_PROGRESS', // ONGOING
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $product3->id,
            'due_date' => '2025-09-15',
            'status' => 'TODO', // BACKLOG
        ]);

        // Make request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-all?quarter=3&year=2025');

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'http_code' => 200,
            'message' => 'Overall progress retrieved successfully',
            'data' => [
                'done' => 1,
                'ongoing' => 1,
                'backlog' => 1,
            ]
        ]);
    }

    /** @test */
    public function test_progress_all_counts_pending_as_ongoing()
    {
        $category = ProductCategory::factory()->create();
        $product1 = Product::factory()->create(['product_category_id' => $category->id]);
        $product2 = Product::factory()->create(['product_category_id' => $category->id]);

        // Create PENDING and IN_PROGRESS measurements
        ProductMeasurement::factory()->create([
            'product_id' => $product1->id,
            'due_date' => '2025-07-15',
            'status' => 'PENDING', // Should count as ONGOING
        ]);

        ProductMeasurement::factory()->create([
            'product_id' => $product2->id,
            'due_date' => '2025-08-15',
            'status' => 'IN_PROGRESS', // Should count as ONGOING
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-all?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJsonPath('data.ongoing', 2); // Both should count as ongoing
    }

    /** @test */
    public function test_issue_tracking_progress_returns_correct_counts()
    {
        // Create issues for Q3 2025 with different statuses
        Issue::factory()->create([
            'status' => IssueStatus::SOLVED,
            'due_date' => '2025-07-15',
            'created_by' => $this->user->id,
        ]);

        Issue::factory()->create([
            'status' => IssueStatus::ON_GOING,
            'due_date' => '2025-08-15',
            'created_by' => $this->user->id,
        ]);

        Issue::factory()->create([
            'status' => IssueStatus::PENDING,
            'due_date' => '2025-09-15',
            'created_by' => $this->user->id,
        ]);

        // Make request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/issue-tracking/progress?quarter=3&year=2025');

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'http_code' => 200,
            'message' => 'Issue tracking progress retrieved successfully',
            'data' => [
                'solved' => 1,
                'in_progress' => 1,
                'pending' => 1,
            ]
        ]);
    }

    /** @test */
    public function test_issue_tracking_progress_filters_by_quarter()
    {
        // Create issues in different quarters
        Issue::factory()->create([
            'status' => IssueStatus::SOLVED,
            'due_date' => '2025-02-15', // Q1
            'created_by' => $this->user->id,
        ]);

        Issue::factory()->create([
            'status' => IssueStatus::SOLVED,
            'due_date' => '2025-08-15', // Q3
            'created_by' => $this->user->id,
        ]);

        // Request Q3 only
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/issue-tracking/progress?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJsonPath('data.solved', 1); // Only Q3 issue
    }

    /** @test */
    public function test_issue_tracking_uses_created_at_fallback()
    {
        // Create issue without due_date, should use created_at
        Issue::factory()->create([
            'status' => IssueStatus::PENDING,
            'due_date' => null, // No due_date
            'created_at' => '2025-08-15', // In Q3
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/issue-tracking/progress?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJsonPath('data.pending', 1); // Should count issue without due_date
    }

    /** @test */
    public function test_progress_category_requires_authentication()
    {
        $response = $this->getJson('/api/v1/product-measurement/progress-category?quarter=3&year=2025');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_progress_all_requires_authentication()
    {
        $response = $this->getJson('/api/v1/product-measurement/progress-all?quarter=3&year=2025');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_issue_tracking_progress_requires_authentication()
    {
        $response = $this->getJson('/api/v1/issue-tracking/progress?quarter=3&year=2025');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_progress_category_validates_quarter_parameter()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-category?quarter=5&year=2025'); // Invalid quarter

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'http_code',
            'message',
            'error_id',
            'data' => ['quarter'] // Validation errors are in 'data' field
        ]);
    }

    /** @test */
    public function test_progress_all_validates_year_parameter()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-all?quarter=3&year=1999'); // Invalid year

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'http_code',
            'message',
            'error_id',
            'data' => ['year'] // Validation errors are in 'data' field
        ]);
    }

    /** @test */
    public function test_progress_category_handles_empty_results()
    {
        // No measurements in database
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-category?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'perCategory' => [] // Empty array when no data
            ]
        ]);
    }

    /** @test */
    public function test_progress_all_handles_empty_results()
    {
        // No measurements in database
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/product-measurement/progress-all?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'done' => 0,
                'ongoing' => 0,
                'backlog' => 0,
            ]
        ]);
    }

    /** @test */
    public function test_issue_tracking_handles_empty_results()
    {
        // No issues in database
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/issue-tracking/progress?quarter=3&year=2025');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'solved' => 0,
                'in_progress' => 0,
                'pending' => 0,
            ]
        ]);
    }
}

