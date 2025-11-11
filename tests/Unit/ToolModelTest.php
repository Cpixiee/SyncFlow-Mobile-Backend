<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Tool;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ToolModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_tool_has_correct_fillable_attributes()
    {
        $tool = new Tool();
        
        $fillable = $tool->getFillable();
        
        $this->assertContains('tool_name', $fillable);
        $this->assertContains('tool_model', $fillable);
        $this->assertContains('tool_type', $fillable);
        $this->assertContains('last_calibration', $fillable);
        $this->assertContains('next_calibration', $fillable);
        $this->assertContains('imei', $fillable);
        $this->assertContains('status', $fillable);
    }

    /** @test */
    public function test_tool_casts_attributes_correctly()
    {
        $tool = Tool::factory()->create([
            'tool_type' => ToolType::MECHANICAL,
            'status' => ToolStatus::ACTIVE,
            'last_calibration' => '2025-01-15',
            'next_calibration' => '2026-01-15',
            'imei' => 'TEST-001'
        ]);

        $this->assertInstanceOf(ToolType::class, $tool->tool_type);
        $this->assertInstanceOf(ToolStatus::class, $tool->status);
        $this->assertInstanceOf(Carbon::class, $tool->last_calibration);
        $this->assertInstanceOf(Carbon::class, $tool->next_calibration);
    }

    /** @test */
    public function test_auto_calculates_next_calibration_on_create()
    {
        $tool = Tool::create([
            'tool_name' => 'Test Tool',
            'tool_model' => 'Test Model',
            'tool_type' => ToolType::MECHANICAL,
            'last_calibration' => Carbon::parse('2025-01-15'),
            'imei' => 'TEST-001',
            'status' => ToolStatus::ACTIVE
        ]);

        $this->assertNotNull($tool->next_calibration);
        $this->assertEquals(
            '2026-01-15',
            $tool->next_calibration->format('Y-m-d')
        );
    }

    /** @test */
    public function test_auto_updates_next_calibration_on_update()
    {
        $tool = Tool::factory()->create([
            'last_calibration' => Carbon::parse('2024-01-01'),
            'next_calibration' => Carbon::parse('2025-01-01'),
            'imei' => 'TEST-001'
        ]);

        $tool->update([
            'last_calibration' => Carbon::parse('2025-06-15')
        ]);

        $tool->refresh();
        
        $this->assertEquals(
            '2025-06-15',
            $tool->last_calibration->format('Y-m-d')
        );
        $this->assertEquals(
            '2026-06-15',
            $tool->next_calibration->format('Y-m-d')
        );
    }

    /** @test */
    public function test_next_calibration_is_null_when_last_calibration_is_null()
    {
        $tool = Tool::create([
            'tool_name' => 'Test Tool',
            'tool_model' => 'Test Model',
            'tool_type' => ToolType::MECHANICAL,
            'last_calibration' => null,
            'imei' => 'TEST-001',
            'status' => ToolStatus::ACTIVE
        ]);

        $this->assertNull($tool->next_calibration);
    }

    /** @test */
    public function test_active_scope_returns_only_active_tools()
    {
        Tool::factory()->create([
            'status' => ToolStatus::ACTIVE,
            'imei' => 'ACTIVE-001'
        ]);
        
        Tool::factory()->create([
            'status' => ToolStatus::ACTIVE,
            'imei' => 'ACTIVE-002'
        ]);
        
        Tool::factory()->create([
            'status' => ToolStatus::INACTIVE,
            'imei' => 'INACTIVE-001'
        ]);

        $activeTools = Tool::active()->get();

        $this->assertCount(2, $activeTools);
        foreach ($activeTools as $tool) {
            $this->assertEquals(ToolStatus::ACTIVE, $tool->status);
        }
    }

    /** @test */
    public function test_by_model_scope_filters_by_tool_model()
    {
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-001'
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-002'
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Keyence LK-G5001',
            'imei' => 'KEY-001'
        ]);

        $mitutoyoTools = Tool::byModel('Mitutoyo CD-6')->get();

        $this->assertCount(2, $mitutoyoTools);
        foreach ($mitutoyoTools as $tool) {
            $this->assertEquals('Mitutoyo CD-6', $tool->tool_model);
        }
    }

    /** @test */
    public function test_get_active_models_returns_unique_active_models()
    {
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'status' => ToolStatus::ACTIVE,
            'imei' => 'MIT-001'
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Mitutoyo CD-6',
            'status' => ToolStatus::ACTIVE,
            'imei' => 'MIT-002'
        ]);
        
        Tool::factory()->create([
            'tool_model' => 'Keyence LK-G5001',
            'status' => ToolStatus::ACTIVE,
            'imei' => 'KEY-001'
        ]);
        
        // Inactive tool - should not be included
        Tool::factory()->create([
            'tool_model' => 'Inactive Model',
            'status' => ToolStatus::INACTIVE,
            'imei' => 'INACT-001'
        ]);

        $activeModels = Tool::getActiveModels();

        $this->assertIsArray($activeModels);
        $this->assertCount(2, $activeModels);
        $this->assertContains('Mitutoyo CD-6', $activeModels);
        $this->assertContains('Keyence LK-G5001', $activeModels);
        $this->assertNotContains('Inactive Model', $activeModels);
    }

    /** @test */
    public function test_get_tools_by_model_returns_active_tools_only()
    {
        Tool::factory()->create([
            'tool_name' => 'Caliper 1',
            'tool_model' => 'Mitutoyo CD-6',
            'status' => ToolStatus::ACTIVE,
            'imei' => 'MIT-001'
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Caliper 2',
            'tool_model' => 'Mitutoyo CD-6',
            'status' => ToolStatus::ACTIVE,
            'imei' => 'MIT-002'
        ]);
        
        Tool::factory()->create([
            'tool_name' => 'Caliper 3',
            'tool_model' => 'Mitutoyo CD-6',
            'status' => ToolStatus::INACTIVE,
            'imei' => 'MIT-003'
        ]);

        $tools = Tool::getToolsByModel('Mitutoyo CD-6');

        $this->assertCount(2, $tools);
        foreach ($tools as $tool) {
            $this->assertEquals('Mitutoyo CD-6', $tool->tool_model);
            $this->assertEquals(ToolStatus::ACTIVE, $tool->status);
        }
    }

    /** @test */
    public function test_tool_type_enum_has_correct_values()
    {
        $this->assertEquals('OPTICAL', ToolType::OPTICAL->value);
        $this->assertEquals('MECHANICAL', ToolType::MECHANICAL->value);
    }

    /** @test */
    public function test_tool_status_enum_has_correct_values()
    {
        $this->assertEquals('ACTIVE', ToolStatus::ACTIVE->value);
        $this->assertEquals('INACTIVE', ToolStatus::INACTIVE->value);
    }

    /** @test */
    public function test_tool_type_enum_descriptions()
    {
        $this->assertEquals('Optical', ToolType::OPTICAL->getDescription());
        $this->assertEquals('Mechanical', ToolType::MECHANICAL->getDescription());
    }

    /** @test */
    public function test_tool_status_enum_descriptions()
    {
        $this->assertEquals('Active', ToolStatus::ACTIVE->getDescription());
        $this->assertEquals('Inactive', ToolStatus::INACTIVE->getDescription());
    }

    /** @test */
    public function test_can_create_tool_with_all_fields()
    {
        $tool = Tool::create([
            'tool_name' => 'Digital Caliper Lab 1',
            'tool_model' => 'Mitutoyo CD-6',
            'tool_type' => ToolType::MECHANICAL,
            'last_calibration' => Carbon::parse('2025-01-15'),
            'imei' => 'MIT-CD6-001',
            'status' => ToolStatus::ACTIVE
        ]);

        $this->assertDatabaseHas('tools', [
            'tool_name' => 'Digital Caliper Lab 1',
            'tool_model' => 'Mitutoyo CD-6',
            'imei' => 'MIT-CD6-001'
        ]);

        $this->assertEquals('Digital Caliper Lab 1', $tool->tool_name);
        $this->assertEquals('Mitutoyo CD-6', $tool->tool_model);
        $this->assertEquals(ToolType::MECHANICAL, $tool->tool_type);
        $this->assertEquals(ToolStatus::ACTIVE, $tool->status);
    }

    /** @test */
    public function test_imei_must_be_unique()
    {
        Tool::factory()->create(['imei' => 'UNIQUE-001']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Tool::factory()->create(['imei' => 'UNIQUE-001']);
    }

    /** @test */
    public function test_default_status_is_active()
    {
        $tool = Tool::factory()->create([
            'imei' => 'TEST-001'
        ]);

        $this->assertEquals(ToolStatus::ACTIVE, $tool->status);
    }
}

