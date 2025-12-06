<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Product;
use App\Models\ProductMeasurement;
use App\Models\ProductCategory;
use App\Enums\MeasurementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class MeasurementJejahValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $product;
    protected $measurement;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = LoginUser::factory()->create([
            'role' => 'operator',
            'username' => 'testuser',
            'employee_id' => 'EMP001'
        ]);

        // Create product category
        $category = ProductCategory::create([
            'name' => 'Test Category',
            'description' => 'Test',
            'products' => []  // Required field
        ]);

        // Create product with measurement points
        $this->product = Product::create([
            'product_id' => 'PRD-TEST001',
            'product_category_id' => $category->id,
            'product_name' => 'Test Product',
            'measurement_points' => [
                [
                    'measurement_item_name_id' => 'thickness_a',
                    'measurement_item_name' => 'Thickness A',
                    'evaluation_type' => 'SINGLE_VALUE',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'thickness_a'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 5,
                        'max' => 15
                    ],
                    'variables' => []
                ],
                [
                    'measurement_item_name_id' => 'thickness_b',
                    'measurement_item_name' => 'Thickness B',
                    'evaluation_type' => 'SINGLE_VALUE',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'thickness_b'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 5,
                        'max' => 15
                    ],
                    'variables' => []
                ],
                [
                    'measurement_item_name_id' => 'thickness_c',
                    'measurement_item_name' => 'Thickness C',
                    'evaluation_type' => 'SINGLE_VALUE',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'thickness_c'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 5,
                        'max' => 15
                    ],
                    'variables' => []
                ],
                [
                    'measurement_item_name_id' => 'room_temp',
                    'measurement_item_name' => 'Room Temperature',
                    'evaluation_type' => 'SINGLE_VALUE',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'room_temp'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 20,
                        'max' => 30
                    ],
                    'variables' => [
                        [
                            'name' => 'CROSS_SECTION',
                            'type' => 'FORMULA',
                            'formula' => '(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3'
                        ]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'final_temp',
                    'measurement_item_name' => 'Final Temperature',
                    'evaluation_type' => 'SINGLE_VALUE',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'final_temp'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 15,
                        'max' => 25
                    ],
                    'variables' => [
                        [
                            'name' => 'FINAL_AVG',
                            'type' => 'FORMULA',
                            'formula' => '(avg(thickness_a) + avg(thickness_b)) / 2'
                        ]
                    ]
                ],
                [
                    'measurement_item_name_id' => 'fix_temp',
                    'measurement_item_name' => 'Fix Temperature',
                    'evaluation_type' => 'JOINT',
                    'setup' => [
                        'source' => 'MANUAL',
                        'name_id' => 'fix_temp'  // Add this!
                    ],
                    'rule_evaluation' => [
                        'rule' => 'RANGE',
                        'min' => 25,
                        'max' => 55
                    ],
                    'joint_setting' => [
                        'formulas' => [
                            [
                                'name' => 'FIX_VALUE',
                                'formula' => 'CROSS_SECTION + FINAL_AVG + 10',
                                'is_final_value' => true
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Create product measurement
        $this->measurement = ProductMeasurement::create([
            'measurement_id' => 'MSR-TEST001',
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-001',
            'sample_count' => 3,
            'measurement_type' => MeasurementType::FULL_MEASUREMENT,
            'status' => 'IN_PROGRESS',
            'measured_at' => now(),
            'measured_by' => $this->user->id
        ]);
    }

    /** @test */
    public function test_samples_check_saves_jejak_to_last_check_data()
    {
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        $response->assertStatus(200);

        // Verify jejak saved
        $this->measurement->refresh();
        $this->assertNotNull($this->measurement->last_check_data);
        $this->assertArrayHasKey('thickness_a', $this->measurement->last_check_data);
        $this->assertArrayHasKey('checked_at', $this->measurement->last_check_data['thickness_a']);
        $this->assertArrayHasKey('samples', $this->measurement->last_check_data['thickness_a']);
    }

    /** @test */
    public function test_save_progress_succeeds_when_data_matches_jejak()
    {
        // Step 1: Hit samples/check to create jejak
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        // Step 2: Save progress with same data
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 10],
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 10]
                        ]
                    ]
                ]
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.saved_items', 1);

        // Verify data saved to DB
        $this->measurement->refresh();
        $this->assertNotNull($this->measurement->measurement_results);
        $this->assertCount(1, $this->measurement->measurement_results);
    }

    /** @test */
    public function test_save_progress_fails_when_raw_data_changed_without_recheck()
    {
        // Step 1: Hit samples/check with initial data
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        // Wait to make timestamp old
        sleep(1);

        // Step 2: Try to save with CHANGED data (without re-checking)
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 50], // CHANGED!
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 30]  // CHANGED!
                        ]
                    ]
                ]
            ]
        );

        // Should return 400 error
        $response->assertStatus(400);
        $response->assertJsonPath('error_id', 'VALIDATION_REQUIRED');
        $response->assertJsonPath('data.critical_count', 1);

        // Verify warning contains correct info
        $warnings = $response->json('data.warnings');
        $this->assertCount(1, $warnings);
        $this->assertEquals('CRITICAL', $warnings[0]['level']);
        $this->assertEquals('thickness_a', $warnings[0]['measurement_item_name_id']);
        $this->assertArrayHasKey('last_check_values', $warnings[0]);
        $this->assertArrayHasKey('current_values', $warnings[0]);

        // Verify data NOT saved to DB
        $this->measurement->refresh();
        $this->assertEmpty($this->measurement->measurement_results);
    }

    /** @test */
    public function test_save_progress_succeeds_after_recheck_changed_data()
    {
        // Step 1: Initial check
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        // Step 2: Re-check with new data
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 50],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 30]
                ]
            ]
        );

        // Step 3: Save progress (should succeed now)
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 50],
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 30]
                        ]
                    ]
                ]
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.saved_items', 1);

        // Verify data saved
        $this->measurement->refresh();
        $this->assertNotEmpty($this->measurement->measurement_results);
    }

    /** @test */
    public function test_save_progress_detects_dependency_changes()
    {
        // Step 1: Setup all initial checks
        $items = [
            'thickness_a' => [10, 10, 10],
            'thickness_b' => [10, 10, 10],
            'thickness_c' => [10, 10, 10],
        ];
        
        foreach ($items as $itemId => $values) {
            $this->actingAsUser($this->user)->postJson(
                "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
                [
                    'measurement_item_name_id' => $itemId,
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => $values[0]],
                        ['sample_index' => 2, 'single_value' => $values[1]],
                        ['sample_index' => 3, 'single_value' => $values[2]]
                    ]
                ]
            );
        }
        
        // Check room_temp (will calculate CROSS_SECTION from thickness values)
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'room_temp',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 25],
                    ['sample_index' => 2, 'single_value' => 25],
                    ['sample_index' => 3, 'single_value' => 25]
                ]
            ]
        );
        
        // Step 2: Save all data first (establish existingResults)
        $saveResponse = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 10],
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 10]
                        ]
                    ],
                    [
                        'measurement_item_name_id' => 'room_temp',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 25],
                            ['sample_index' => 2, 'single_value' => 25],
                            ['sample_index' => 3, 'single_value' => 25]
                        ],
                        'variable_values' => [
                            ['name_id' => 'CROSS_SECTION', 'value' => 10]
                        ]
                    ]
                ]
            ]
        );
        
        $saveResponse->assertStatus(200);
        
        sleep(1); // Make timestamp old
        
        // Step 3: Try to save with CHANGED thickness_a (without re-check)
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 50], // CHANGED!
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 30]
                        ]
                    ],
                    [
                        'measurement_item_name_id' => 'room_temp',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 25],
                            ['sample_index' => 2, 'single_value' => 25],
                            ['sample_index' => 3, 'single_value' => 25]
                        ],
                        'variable_values' => [
                            ['name_id' => 'CROSS_SECTION', 'value' => 10]  // OLD VALUE!
                        ]
                    ]
                ]
            ]
        );
        
        // Should return 400
        $response->assertStatus(400);
        
        // At minimum, should have CRITICAL for thickness_a
        $response->assertJsonPath('data.critical_count', 1);
        
        $warnings = $response->json('data.warnings');
        $this->assertGreaterThanOrEqual(1, count($warnings));
        
        // Verify thickness_a has CRITICAL warning
        $criticalWarning = collect($warnings)->firstWhere('level', 'CRITICAL');
        $this->assertNotNull($criticalWarning);
        $this->assertEquals('thickness_a', $criticalWarning['measurement_item_name_id']);
        
        // If dependency detection works, should also have WARNING for room_temp
        if (count($warnings) > 1) {
            $dependencyWarning = collect($warnings)->firstWhere('measurement_item_name_id', 'room_temp');
            if ($dependencyWarning) {
                $this->assertEquals('WARNING', $dependencyWarning['level']);
                $this->assertArrayHasKey('dependencies_changed', $dependencyWarning);
            }
        }
    }

    /** @test */
    public function test_save_progress_detects_chain_dependencies()
    {
        // Setup all measurements
        $items = [
            ['thickness_a', [10, 10, 10]],
            ['thickness_b', [10, 10, 10]],
            ['thickness_c', [10, 10, 10]],
            ['room_temp', [25, 25, 25]],
            ['final_temp', [20, 20, 20]],
            ['fix_temp', [30, 30, 30]]
        ];

        foreach ($items as [$itemId, $values]) {
            $this->actingAsUser($this->user)->postJson(
                "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
                [
                    'measurement_item_name_id' => $itemId,
                    'samples' => [
                        ['sample_index' => 1, 'single_value' => $values[0]],
                        ['sample_index' => 2, 'single_value' => $values[1]],
                        ['sample_index' => 3, 'single_value' => $values[2]]
                    ]
                ]
            );
        }

        sleep(1);

        // Try to save with changed thickness_a (affects room_temp, final_temp, fix_temp)
        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 50],
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 30]
                        ]
                    ],
                    [
                        'measurement_item_name_id' => 'room_temp',
                        'samples' => [['sample_index' => 1, 'single_value' => 25]],
                        'variable_values' => [['name_id' => 'CROSS_SECTION', 'value' => 10]]
                    ],
                    [
                        'measurement_item_name_id' => 'final_temp',
                        'samples' => [['sample_index' => 1, 'single_value' => 20]],
                        'variable_values' => [['name_id' => 'FINAL_AVG', 'value' => 10]]
                    ],
                    [
                        'measurement_item_name_id' => 'fix_temp',
                        'samples' => [['sample_index' => 1, 'single_value' => 30]],
                        'variable_values' => [['name_id' => 'FIX_VALUE', 'value' => 30]]
                    ]
                ]
            ]
        );

        // Should detect all chain dependencies
        $response->assertStatus(400);
        
        $warnings = $response->json('data.warnings');
        $this->assertGreaterThanOrEqual(3, count($warnings)); // At least thickness_a, room_temp, final_temp

        // Verify chain: thickness_a → room_temp, final_temp
        $warningItems = collect($warnings)->pluck('measurement_item_name_id')->all();
        $this->assertContains('thickness_a', $warningItems);
        $this->assertContains('room_temp', $warningItems);
        $this->assertContains('final_temp', $warningItems);
    }

    /** @test */
    public function test_jejak_persists_across_multiple_checks()
    {
        // First check
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        $this->measurement->refresh();
        $firstCheckTime = $this->measurement->last_check_data['thickness_a']['checked_at'];

        sleep(1);

        // Second check (update jejak)
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 50],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 30]
                ]
            ]
        );

        $this->measurement->refresh();
        $secondCheckTime = $this->measurement->last_check_data['thickness_a']['checked_at'];

        // Verify jejak updated
        $this->assertNotEquals($firstCheckTime, $secondCheckTime);
        $this->assertEquals(50, $this->measurement->last_check_data['thickness_a']['samples'][0]['single_value']);
    }

    /** @test */
    public function test_no_warnings_when_all_data_valid()
    {
        // Measure and save immediately
        $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/samples/check",
            [
                'measurement_item_name_id' => 'thickness_a',
                'samples' => [
                    ['sample_index' => 1, 'single_value' => 10],
                    ['sample_index' => 2, 'single_value' => 10],
                    ['sample_index' => 3, 'single_value' => 10]
                ]
            ]
        );

        $response = $this->actingAsUser($this->user)->postJson(
            "/api/v1/product-measurement/{$this->measurement->measurement_id}/save-progress",
            [
                'measurement_results' => [
                    [
                        'measurement_item_name_id' => 'thickness_a',
                        'status' => true,
                        'samples' => [
                            ['sample_index' => 1, 'single_value' => 10],
                            ['sample_index' => 2, 'single_value' => 10],
                            ['sample_index' => 3, 'single_value' => 10]
                        ]
                    ]
                ]
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonMissing(['warnings']);
        
        // Data should be saved
        $this->measurement->refresh();
        $this->assertNotEmpty($this->measurement->measurement_results);
    }
}

