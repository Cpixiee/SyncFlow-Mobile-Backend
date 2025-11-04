<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\ProductCategory;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'operator',
            'password' => bcrypt('testpass')
        ]);
        
        $this->token = JWTAuth::fromUser($this->user);
        
        // Seed default categories
        ProductCategory::seedDefaultCategories();
    }

    /** @test */
    public function test_can_get_all_product_categories()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/v1/product-categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'categories' => [
                            '*' => [
                                'id',
                                'name',
                                'products',
                                'description'
                            ]
                        ]
                    ]
                ]);

        $data = $response->json('data.categories');
        $this->assertCount(3, $data); // Tube Test, Wire Test Reguler, Shield Wire Test
    }

    /** @test */
    public function test_can_get_categories_with_structure()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/v1/product-categories?include_structure=true');

        $response->assertStatus(200);
        
        $categories = $response->json('data.categories');
        
        // Check that structure is included
        foreach ($categories as $category) {
            $this->assertArrayHasKey('structure', $category);
            $this->assertIsArray($category['structure']);
        }
    }

    /** @test */
    public function test_can_get_products_by_category_id()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/{$tubeTest->id}/products");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'category_id',
                        'category_name',
                        'products'
                    ]
                ]);

        $products = $response->json('data.products');
        $this->assertIsArray($products);
        $this->assertGreaterThan(0, count($products));
        
        // Verify contains expected products
        $this->assertContains('VO', $products);
        $this->assertContains('COT', $products);
    }

    /** @test */
    public function test_search_products_with_empty_query_returns_all_products()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        // Search dengan query kosong (atau tanpa parameter q)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?category_id={$tubeTest->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'All products retrieved successfully'
                ]);

        $products = $response->json('data.products');
        $this->assertGreaterThan(0, count($products));
        $this->assertEquals($tubeTest->products, $products);
    }

    /** @test */
    public function test_search_products_with_query_filters_correctly()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        // Search dengan query "CO"
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?q=CO&category_id={$tubeTest->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Products search completed'
                ]);

        $products = $response->json('data.products');
        
        // All results should contain "CO"
        foreach ($products as $product) {
            $this->assertStringContainsStringIgnoringCase('CO', $product);
        }
        
        // Should include COTO, COT, COTO-FR, COT-FR, CORUTUBE variants
        $this->assertContains('COTO', $products);
        $this->assertContains('COT', $products);
    }

    /** @test */
    public function test_search_products_case_insensitive()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        // Search dengan lowercase
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?q=co&category_id={$tubeTest->id}");

        // Search dengan uppercase
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?q=CO&category_id={$tubeTest->id}");

        $products1 = $response1->json('data.products');
        $products2 = $response2->json('data.products');

        // Should return same results regardless of case
        $this->assertEquals($products1, $products2);
    }

    /** @test */
    public function test_search_across_all_categories()
    {
        // Search tanpa category_id
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?q=AV");

        $response->assertStatus(200);

        $results = $response->json('data.results');
        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        // Should find AV in multiple categories (Wire Test Reguler dan Shield Wire Test)
        $categoryNames = array_column($results, 'category_name');
        $this->assertContains('Wire Test Reguler', $categoryNames);
    }

    /** @test */
    public function test_search_without_category_and_empty_query_returns_error()
    {
        // Search tanpa category_id dan tanpa query
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products");

        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Please provide either category_id or search query (q)',
                    'error_id' => 'VALIDATION_ERROR'
                ]);
    }

    /** @test */
    public function test_search_with_invalid_category_id_returns_404()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/search-products?category_id=999");

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Product category not found'
                ]);
    }

    /** @test */
    public function test_can_get_category_structure()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/structure?category_id={$tubeTest->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'category_id',
                        'category_name',
                        'structure',
                        'description'
                    ]
                ]);

        $structure = $response->json('data.structure');
        $this->assertIsArray($structure);
        
        // Check for expected subcategories
        $this->assertArrayHasKey('VO', $structure);
        $this->assertArrayHasKey('COT', $structure);
        $this->assertArrayHasKey('RFCOT', $structure);
        $this->assertArrayHasKey('HCOT', $structure);
    }

    /** @test */
    public function test_can_get_all_category_structures()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/v1/product-categories/structure");

        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertCount(3, $categories);

        foreach ($categories as $category) {
            $this->assertArrayHasKey('category_id', $category);
            $this->assertArrayHasKey('category_name', $category);
            $this->assertArrayHasKey('structure', $category);
            $this->assertIsArray($category['structure']);
        }
    }

    /** @test */
    public function test_product_category_model_methods()
    {
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();

        // Test getProductNames()
        $productNames = $tubeTest->getProductNames();
        $this->assertIsArray($productNames);
        $this->assertGreaterThan(0, count($productNames));

        // Test getFlatProductList()
        $flatList = $tubeTest->getFlatProductList();
        $this->assertIsArray($flatList);
        $this->assertContains('VO', $flatList);
        $this->assertContains('COT', $flatList);

        // Test isValidProductName()
        $this->assertTrue($tubeTest->isValidProductName('VO'));
        $this->assertTrue($tubeTest->isValidProductName('COT'));
        $this->assertFalse($tubeTest->isValidProductName('INVALID_PRODUCT'));
    }

    /** @test */
    public function test_default_categories_are_seeded_correctly()
    {
        $categories = ProductCategory::all();
        
        $this->assertCount(3, $categories);

        $names = $categories->pluck('name')->toArray();
        $this->assertContains('Tube Test', $names);
        $this->assertContains('Wire Test Reguler', $names);
        $this->assertContains('Shield Wire Test', $names);

        // Check Tube Test products
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();
        $this->assertContains('VO', $tubeTest->products);
        $this->assertContains('COT', $tubeTest->products);
        $this->assertContains('RFCOT', $tubeTest->products);

        // Check Wire Test Reguler products
        $wireTest = ProductCategory::where('name', 'Wire Test Reguler')->first();
        $this->assertContains('CAVS', $wireTest->products);
        $this->assertContains('AVSS', $wireTest->products);

        // Check Shield Wire Test products
        $shieldWire = ProductCategory::where('name', 'Shield Wire Test')->first();
        $this->assertContains('CIVUSAS', $shieldWire->products);
    }

    /** @test */
    public function test_unauthorized_access_without_token()
    {
        $response = $this->getJson('/api/v1/product-categories');
        $response->assertStatus(401);
    }
}

