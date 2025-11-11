<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Tool;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Carbon\Carbon;

class ToolTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = LoginUser::factory()->create([
            'username' => 'testadmin',
            'role' => 'admin',
            'password' => bcrypt('testpassword')
        ]);
        
        // Create regular user
        $this->regularUser = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'operator',
            'password' => bcrypt('testpassword')
        ]);
    }

    /** @test */
    public function test_can_get_all_tools_with_pagination()
    {
        // Create test tools
        Tool::factory()->create([
            'tool_name' => 'Caliper 1',
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => ToolType::MECHANICAL,
            'imei' => 'MIT-001',
            'status' => ToolStatus::ACTIVE
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Caliper 2',
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => ToolType::MECHANICAL,
            'imei' => 'MIT-002',
            'status' => ToolStatus::ACTIVE
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?page=1&limit=10');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'data' => [
                'docs' => [
                    '*' => [
                        'id',
                        'tool_name',
                        'tool_model',
                        'tool_type',
                        'tool_type_description',
                        'last_calibration',
                        'next_calibration',
                        'imei',
                        'status',
                        'status_description',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'metadata' => [
                    'current_page',
                    'total_page',
                    'limit',
                    'total_docs'
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['docs']);
        $this->assertEquals(1, $data['metadata']['current_page']);
        $this->assertEquals(2, $data['metadata']['total_docs']);
    }

    /** @test */
    public function test_can_filter_tools_by_status()
    {
        Tool::factory()->create([
            'tool_name' => 'Active Tool',
            'imei' => 'ACT-001',
            'status' => ToolStatus::ACTIVE
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Inactive Tool',
            'imei' => 'INACT-001',
            'status' => ToolStatus::INACTIVE
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?status=ACTIVE');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(1, $data['docs']);
        $this->assertEquals('ACTIVE', $data['docs'][0]['status']);
    }

    /** @test */
    public function test_can_filter_tools_by_type()
    {
        Tool::factory()->create([
            'tool_type' => ToolType::OPTICAL,
            'imei' => 'OPT-001'
        ]);
        
        Tool::factory()->create([
            'tool_type' => ToolType::MECHANICAL,
            'imei' => 'MECH-001'
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?tool_type=OPTICAL');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(1, $data['docs']);
        $this->assertEquals('OPTICAL', $data['docs'][0]['tool_type']);
    }

    /** @test */
    public function test_can_search_tools()
    {
        Tool::factory()->create([
            'tool_name' => 'Digital Caliper Lab 1',
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-CD6-001'
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Optical Sensor',
            'tool_model' => 'Keyence LK-G5001',
            'imei' => 'KEY-001'
        ]);

        // Search by tool name
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?search=Caliper');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(1, $data['docs']);
        $this->assertStringContainsString('Caliper', $data['docs'][0]['tool_name']);
    }

    /** @test */
    public function test_can_get_single_tool()
    {
        $tool = Tool::factory()->create([
            'tool_name' => 'Test Tool',
            'tool_model' => 'Test Model',
            'imei' => 'TEST-001'
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson("/api/v1/tools/{$tool->id}");

        $this->assertApiSuccess($response);
        $response->assertJson([
            'data' => [
                'id' => $tool->id,
                'tool_name' => 'Test Tool',
                'tool_model' => 'Test Model',
                'imei' => 'TEST-001'
            ]
        ]);
    }

    /** @test */
    public function test_returns_404_when_tool_not_found()
    {
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools/999');

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Tool not found']);
    }

    /** @test */
    public function test_can_get_tool_models()
    {
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => ToolType::MECHANICAL,
            'imei' => 'MIT-001',
            'status' => ToolStatus::ACTIVE
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => ToolType::MECHANICAL,
            'imei' => 'MIT-002',
            'status' => ToolStatus::ACTIVE
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Keyence LK-G5001',
            'tool_type' => ToolType::OPTICAL,
            'imei' => 'KEY-001',
            'status' => ToolStatus::ACTIVE
        ]);

        // Create inactive tool - should not appear
        Tool::factory()->create([
            'tool_model' => 'Inactive Model',
            'imei' => 'INACT-001',
            'status' => ToolStatus::INACTIVE
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools/models');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        // Should only have 2 models (Mitutoyo and Keyence, not Inactive)
        $this->assertCount(2, $data);
        
        // Find Mitutoyo model
        $mitutoyoModel = collect($data)->firstWhere('tool_model', 'Mitutoyo CD-6');
        $this->assertNotNull($mitutoyoModel);
        $this->assertEquals('MECHANICAL', $mitutoyoModel['tool_type']);
        $this->assertEquals(2, $mitutoyoModel['imei_count']);
    }

    /** @test */
    public function test_can_get_tools_by_model()
    {
        Tool::factory()->create([
            'tool_name' => 'Caliper 1',
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-001',
            'status' => ToolStatus::ACTIVE
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Caliper 2',
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-002',
            'status' => ToolStatus::ACTIVE
        ]);
        
        // Inactive tool - should not appear
        Tool::factory()->create([
            'tool_name' => 'Caliper 3',
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-003',
            'status' => ToolStatus::INACTIVE
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools/by-model?tool_model=Mitutoyo CD-6');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertEquals('Mitutoyo CD-6', $data['tool_model']);
        $this->assertCount(2, $data['tools']); // Only active tools
    }

    /** @test */
    public function test_returns_404_when_no_active_tools_found_for_model()
    {
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools/by-model?tool_model=NonExistent');

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'No active tools found for this model']);
    }

    /** @test */
    public function test_admin_can_create_tool()
    {
        $toolData = [
            'tool_name' => 'New Caliper',
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => 'MECHANICAL',
            'last_calibration' => '2025-01-15',
            'imei' => 'NEW-001',
            'status' => 'ACTIVE'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/tools', $toolData);

        $response->assertStatus(201);
        $this->assertApiResponseStructure($response, 201);
        
        $response->assertJson([
            'message' => 'Tool created successfully',
            'data' => [
                'tool_name' => 'New Caliper',
                'tool_model' => 'Mitutoyo CD-6',
                'tool_type' => 'MECHANICAL',
                'imei' => 'NEW-001',
                'status' => 'ACTIVE'
            ]
        ]);

        $this->assertDatabaseHas('tools', [
            'tool_name' => 'New Caliper',
            'imei' => 'NEW-001'
        ]);
    }

    /** @test */
    public function test_auto_calculates_next_calibration_on_create()
    {
        $toolData = [
            'tool_name' => 'Test Tool',
            'tool_model' => 'Test Model',
            'tool_type' => 'MECHANICAL',
            'last_calibration' => '2025-01-15',
            'imei' => 'TEST-001'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/tools', $toolData);

        $response->assertStatus(201);
        
        $data = $response->json('data');
        $this->assertEquals('2025-01-15', $data['last_calibration']);
        $this->assertEquals('2026-01-15', $data['next_calibration']);
    }

    /** @test */
    public function test_regular_user_cannot_create_tool()
    {
        $toolData = [
            'tool_name' => 'New Tool',
            'tool_model' => 'Test Model',
            'tool_type' => 'MECHANICAL',
            'imei' => 'TEST-001'
        ];

        $response = $this->actingAsUser($this->regularUser)
            ->postJson('/api/v1/tools', $toolData);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_validates_required_fields_on_create()
    {
        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/tools', []);

        $this->assertApiError($response, 400);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('tool_name', $data);
        $this->assertArrayHasKey('tool_model', $data);
        $this->assertArrayHasKey('tool_type', $data);
        $this->assertArrayHasKey('imei', $data);
    }

    /** @test */
    public function test_validates_imei_uniqueness()
    {
        Tool::factory()->create(['imei' => 'DUPLICATE-001']);

        $toolData = [
            'tool_name' => 'New Tool',
            'tool_model' => 'Test Model',
            'tool_type' => 'MECHANICAL',
            'imei' => 'DUPLICATE-001' // Duplicate IMEI
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/tools', $toolData);

        $this->assertApiError($response, 400);
        $data = $response->json('data');
        $this->assertArrayHasKey('imei', $data);
    }

    /** @test */
    public function test_validates_tool_type_enum()
    {
        $toolData = [
            'tool_name' => 'New Tool',
            'tool_model' => 'Test Model',
            'tool_type' => 'INVALID_TYPE', // Invalid type
            'imei' => 'TEST-001'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/tools', $toolData);

        $this->assertApiError($response, 400);
    }

    /** @test */
    public function test_admin_can_update_tool()
    {
        $tool = Tool::factory()->create([
            'tool_name' => 'Old Name',
            'tool_model' => 'Old Model',
            'imei' => 'OLD-001',
            'status' => ToolStatus::ACTIVE
        ]);

        $updateData = [
            'tool_name' => 'Updated Name',
            'status' => 'INACTIVE'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->putJson("/api/v1/tools/{$tool->id}", $updateData);

        $this->assertApiSuccess($response);
        $response->assertJson([
            'data' => [
                'tool_name' => 'Updated Name',
                'status' => 'INACTIVE'
            ]
        ]);

        $this->assertDatabaseHas('tools', [
            'id' => $tool->id,
            'tool_name' => 'Updated Name',
            'status' => 'INACTIVE'
        ]);
    }

    /** @test */
    public function test_auto_updates_next_calibration_on_update()
    {
        $tool = Tool::factory()->create([
            'last_calibration' => Carbon::parse('2024-01-01'),
            'next_calibration' => Carbon::parse('2025-01-01'),
            'imei' => 'TEST-001'
        ]);

        $updateData = [
            'last_calibration' => '2025-06-15'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->putJson("/api/v1/tools/{$tool->id}", $updateData);

        $this->assertApiSuccess($response);
        
        $data = $response->json('data');
        $this->assertEquals('2025-06-15', $data['last_calibration']);
        $this->assertEquals('2026-06-15', $data['next_calibration']);
    }

    /** @test */
    public function test_regular_user_cannot_update_tool()
    {
        $tool = Tool::factory()->create(['imei' => 'TEST-001']);

        $response = $this->actingAsUser($this->regularUser)
            ->putJson("/api/v1/tools/{$tool->id}", ['tool_name' => 'Updated']);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_admin_can_delete_tool()
    {
        $tool = Tool::factory()->create(['imei' => 'DELETE-001']);

        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson("/api/v1/tools/{$tool->id}");

        $this->assertApiSuccess($response);
        $response->assertJson([
            'message' => 'Tool deleted successfully',
            'data' => ['deleted' => true]
        ]);

        $this->assertDatabaseMissing('tools', ['id' => $tool->id]);
    }

    /** @test */
    public function test_regular_user_cannot_delete_tool()
    {
        $tool = Tool::factory()->create(['imei' => 'TEST-001']);

        $response = $this->actingAsUser($this->regularUser)
            ->deleteJson("/api/v1/tools/{$tool->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function test_cannot_delete_non_existent_tool()
    {
        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson('/api/v1/tools/999');

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Tool not found']);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_tools()
    {
        $response = $this->getJson('/api/v1/tools');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_pagination_parameters_work_correctly()
    {
        // Create 25 tools
        for ($i = 1; $i <= 25; $i++) {
            Tool::factory()->create(['imei' => "TOOL-{$i}"]);
        }

        // Test page 1 with limit 10
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?page=1&limit=10');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertCount(10, $data['docs']);
        $this->assertEquals(1, $data['metadata']['current_page']);
        $this->assertEquals(3, $data['metadata']['total_page']);
        $this->assertEquals(10, $data['metadata']['limit']);
        $this->assertEquals(25, $data['metadata']['total_docs']);

        // Test page 2
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?page=2&limit=10');

        $data = $response->json('data');
        $this->assertEquals(2, $data['metadata']['current_page']);
        $this->assertCount(10, $data['docs']);
    }

    /** @test */
    public function test_can_combine_filters_and_search()
    {
        Tool::factory()->create([
            'tool_name' => 'Optical Sensor 1',
            'tool_type' => ToolType::OPTICAL,
            'status' => ToolStatus::ACTIVE,
            'imei' => 'OPT-001'
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Optical Sensor 2',
            'tool_type' => ToolType::OPTICAL,
            'status' => ToolStatus::INACTIVE,
            'imei' => 'OPT-002'
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Mechanical Caliper',
            'tool_type' => ToolType::MECHANICAL,
            'status' => ToolStatus::ACTIVE,
            'imei' => 'MECH-001'
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/tools?status=ACTIVE&tool_type=OPTICAL&search=Sensor');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['docs']);
        $this->assertEquals('Optical Sensor 1', $data['docs'][0]['tool_name']);
    }
}

