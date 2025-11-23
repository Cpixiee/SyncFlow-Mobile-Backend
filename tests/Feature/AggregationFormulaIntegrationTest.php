<?php

namespace Tests\Feature;

use App\Models\LoginUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMeasurement;
use App\Models\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregationFormulaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminToken;
    protected $productCategory;
    protected $quarter;
    protected $product;

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
            'password' => 'password123'
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

        // Create product with measurement items for aggregation testing
        $this->product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => [
                // Base measurement: thickness_a
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 3,
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
                        'rule' => 'MIN',
                        'unit' => 'mm',
                        'value' => 0
                    ]
                ],
                // Derived measurement using AVG
                [
                    'setup' => [
                        'name' => 'Average Thickness',
                        'name_id' => 'average_thickness',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'source_derived_name_id' => 'thickness_a',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'avg_value',
                            'formula' => '=avg(thickness_a)',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => ['_skip' => true],
                    'rule_evaluation_setting' => [
                        'rule' => 'MIN',
                        'unit' => 'mm',
                        'value' => 0
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test AVG aggregation function with actual measurement data
     * 
     * Scenario:
     * - thickness_a has 3 samples: [30, 40, 10]
     * - avg(thickness_a) should calculate: (30 + 40 + 10) / 3 = 26.67
     */
    public function test_avg_aggregation_function_calculates_correctly()
    {
        // Create product measurement
        $productMeasurement = ProductMeasurement::create([
            'product_id' => $this->product->id,
            'sample_count' => 3,
            'status' => 'PENDING'
        ]);

        // Submit measurement data
        $measurementData = [
            'measurement_results' => [
                // First measurement item: thickness_a with 3 samples
                [
                    'measurement_item_name_id' => 'thickness_a',
                    'variable_values' => [],
                    'samples' => [
                        [
                            'sample_index' => 1,
                            'single_value' => 30,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 2,
                            'single_value' => 40,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ],
                        [
                            'sample_index' => 3,
                            'single_value' => 10,
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ]
                    ]
                ],
                // Second measurement item: average_thickness using avg(thickness_a)
                [
                    'measurement_item_name_id' => 'average_thickness',
                    'variable_values' => [],
                    'samples' => [
                        [
                            'sample_index' => 1,
                            'single_value' => 0, // Will be calculated
                            'before_after_value' => null,
                            'qualitative_value' => null
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/product-measurement/' . $productMeasurement->measurement_id . '/submit', $measurementData);

        $response->assertStatus(200);

        // Verify the calculation
        $productMeasurement->refresh();
        $results = $productMeasurement->measurement_results;

        // Check thickness_a samples were stored correctly
        $thicknessA = collect($results)->firstWhere('measurement_item_name_id', 'thickness_a');
        $this->assertNotNull($thicknessA);
        $this->assertCount(3, $thicknessA['samples']);

        // Check average_thickness calculated correctly
        // Expected: (30 + 40 + 10) / 3 = 26.666...
        $avgThickness = collect($results)->firstWhere('measurement_item_name_id', 'average_thickness');
        $this->assertNotNull($avgThickness);
        
        // The avg_value variable should be calculated
        $this->assertArrayHasKey('variables', $avgThickness['samples'][0]);
        $avgValue = $avgThickness['samples'][0]['variables']['avg_value'] ?? null;
        
        // Verify the calculation is correct (allowing small floating point difference)
        $expectedAvg = (30 + 40 + 10) / 3; // 26.666...
        $this->assertEqualsWithDelta($expectedAvg, $avgValue, 0.01);
    }

    /**
     * Test multiple aggregation functions with the same base data
     * 
     * Tests: AVG, SUM, MIN, MAX, COUNT on the same dataset
     */
    public function test_multiple_aggregation_functions_with_same_data()
    {
        // Create product with multiple aggregation formulas
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'CAVS',
            'measurement_points' => [
                // Base measurement
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 3,
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
                        'rule' => 'MIN',
                        'unit' => 'mm',
                        'value' => 0
                    ]
                ],
                // Statistics measurement with multiple aggregations
                [
                    'setup' => [
                        'name' => 'Statistics',
                        'name_id' => 'statistics',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'source_derived_name_id' => 'thickness_a',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'avg_val',
                            'formula' => '=avg(thickness_a)',
                            'is_show' => true
                        ],
                        [
                            'type' => 'FORMULA',
                            'name' => 'sum_val',
                            'formula' => '=sum(thickness_a)',
                            'is_show' => true
                        ],
                        [
                            'type' => 'FORMULA',
                            'name' => 'min_val',
                            'formula' => '=min(thickness_a)',
                            'is_show' => true
                        ],
                        [
                            'type' => 'FORMULA',
                            'name' => 'max_val',
                            'formula' => '=max(thickness_a)',
                            'is_show' => true
                        ],
                        [
                            'type' => 'FORMULA',
                            'name' => 'count_val',
                            'formula' => '=count(thickness_a)',
                            'is_show' => true
                        ],
                        [
                            'type' => 'FORMULA',
                            'name' => 'range_val',
                            'formula' => '=max(thickness_a) - min(thickness_a)',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => ['_skip' => true],
                    'rule_evaluation_setting' => [
                        'rule' => 'MIN',
                        'unit' => 'mm',
                        'value' => 0
                    ]
                ]
            ]
        ]);

        // Create product measurement
        $productMeasurement = ProductMeasurement::create([
            'product_id' => $product->id,
            'sample_count' => 3,
            'status' => 'PENDING'
        ]);

        // Submit measurement data
        // Test data: [30, 40, 10]
        // Expected results:
        // - AVG: 26.67
        // - SUM: 80
        // - MIN: 10
        // - MAX: 40
        // - COUNT: 3
        // - RANGE (MAX-MIN): 30
        $measurementData = [
            'measurement_results' => [
                [
                    'measurement_item_name_id' => 'thickness_a',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 30, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 2, 'single_value' => 40, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 3, 'single_value' => 10, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'statistics',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 0, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/product-measurement/' . $productMeasurement->measurement_id . '/submit', $measurementData);

        $response->assertStatus(200);

        // Verify all calculations
        $productMeasurement->refresh();
        $results = $productMeasurement->measurement_results;

        $statistics = collect($results)->firstWhere('measurement_item_name_id', 'statistics');
        $this->assertNotNull($statistics);

        $variables = $statistics['samples'][0]['variables'] ?? [];

        // Verify AVG: (30 + 40 + 10) / 3 = 26.666...
        $this->assertEqualsWithDelta(26.67, $variables['avg_val'], 0.01);

        // Verify SUM: 30 + 40 + 10 = 80
        $this->assertEquals(80, $variables['sum_val']);

        // Verify MIN: 10
        $this->assertEquals(10, $variables['min_val']);

        // Verify MAX: 40
        $this->assertEquals(40, $variables['max_val']);

        // Verify COUNT: 3
        $this->assertEquals(3, $variables['count_val']);

        // Verify RANGE: 40 - 10 = 30
        $this->assertEquals(30, $variables['range_val']);
    }

    /**
     * Test aggregation with multiple measurement items
     * 
     * Scenario: room_temp = (avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3
     */
    public function test_aggregation_with_multiple_measurement_items()
    {
        // Create product
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'COT',
            'measurement_points' => [
                // thickness_a
                [
                    'setup' => [
                        'name' => 'Thickness A',
                        'name_id' => 'thickness_a',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => ['per_sample_setting' => ['is_raw_data' => true]],
                    'rule_evaluation_setting' => ['rule' => 'MIN', 'unit' => 'mm', 'value' => 0]
                ],
                // thickness_b
                [
                    'setup' => [
                        'name' => 'Thickness B',
                        'name_id' => 'thickness_b',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => ['per_sample_setting' => ['is_raw_data' => true]],
                    'rule_evaluation_setting' => ['rule' => 'MIN', 'unit' => 'mm', 'value' => 0]
                ],
                // thickness_c
                [
                    'setup' => [
                        'name' => 'Thickness C',
                        'name_id' => 'thickness_c',
                        'sample_amount' => 3,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => ['per_sample_setting' => ['is_raw_data' => true]],
                    'rule_evaluation_setting' => ['rule' => 'MIN', 'unit' => 'mm', 'value' => 0]
                ],
                // room_temp - combines all three
                [
                    'setup' => [
                        'name' => 'Room Temp',
                        'name_id' => 'room_temp',
                        'sample_amount' => 1,
                        'source' => 'DERIVED',
                        'source_derived_name_id' => 'thickness_a',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'variables' => [
                        [
                            'type' => 'FORMULA',
                            'name' => 'combined_avg',
                            'formula' => '=(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3',
                            'is_show' => true
                        ]
                    ],
                    'evaluation_type' => 'SKIP_CHECK',
                    'evaluation_setting' => ['_skip' => true],
                    'rule_evaluation_setting' => ['rule' => 'MIN', 'unit' => 'mm', 'value' => 0]
                ]
            ]
        ]);

        // Create measurement
        $productMeasurement = ProductMeasurement::create([
            'product_id' => $product->id,
            'sample_count' => 3,
            'status' => 'PENDING'
        ]);

        // Submit measurement data
        // thickness_a: [30, 40, 10] → avg = 26.67
        // thickness_b: [25, 35, 15] → avg = 25
        // thickness_c: [28, 38, 12] → avg = 26
        // combined_avg: (26.67 + 25 + 26) / 3 = 25.89
        $measurementData = [
            'measurement_results' => [
                [
                    'measurement_item_name_id' => 'thickness_a',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 30, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 2, 'single_value' => 40, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 3, 'single_value' => 10, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'thickness_b',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 25, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 2, 'single_value' => 35, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 3, 'single_value' => 15, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'thickness_c',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 28, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 2, 'single_value' => 38, 'before_after_value' => null, 'qualitative_value' => null],
                        ['sample_index' => 3, 'single_value' => 12, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'room_temp',
                    'variable_values' => [],
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => 0, 'before_after_value' => null, 'qualitative_value' => null]
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/v1/product-measurement/' . $productMeasurement->measurement_id . '/submit', $measurementData);

        $response->assertStatus(200);

        // Verify calculation
        $productMeasurement->refresh();
        $results = $productMeasurement->measurement_results;

        $roomTemp = collect($results)->firstWhere('measurement_item_name_id', 'room_temp');
        $this->assertNotNull($roomTemp);

        $variables = $roomTemp['samples'][0]['variables'] ?? [];
        
        // Expected: (26.67 + 25 + 26) / 3 = 25.89
        $expectedValue = ((30+40+10)/3 + (25+35+15)/3 + (28+38+12)/3) / 3;
        $this->assertEqualsWithDelta($expectedValue, $variables['combined_avg'], 0.01);
    }
}

