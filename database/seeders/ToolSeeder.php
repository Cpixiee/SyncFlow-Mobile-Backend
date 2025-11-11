<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tool;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Carbon\Carbon;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tools = [
            // Mechanical Tools - Mitutoyo CD-6 (3 units)
            [
                'tool_name' => 'Digital Caliper Lab 1',
                'tool_model' => 'Mitutoyo CD-6',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonths(2),
                'imei' => 'MIT-CD6-001',
                'status' => ToolStatus::ACTIVE,
            ],
            [
                'tool_name' => 'Digital Caliper Lab 2',
                'tool_model' => 'Mitutoyo CD-6',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonths(3),
                'imei' => 'MIT-CD6-002',
                'status' => ToolStatus::ACTIVE,
            ],
            [
                'tool_name' => 'Digital Caliper Lab 3',
                'tool_model' => 'Mitutoyo CD-6',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonths(1),
                'imei' => 'MIT-CD6-003',
                'status' => ToolStatus::INACTIVE, // Inactive for testing
            ],

            // Optical Tools - Keyence LK-G5001 (2 units)
            [
                'tool_name' => 'Optical Sensor A',
                'tool_model' => 'Keyence LK-G5001',
                'tool_type' => ToolType::OPTICAL,
                'last_calibration' => Carbon::now()->subMonths(4),
                'imei' => 'KEY-LK5001-A',
                'status' => ToolStatus::ACTIVE,
            ],
            [
                'tool_name' => 'Optical Sensor B',
                'tool_model' => 'Keyence LK-G5001',
                'tool_type' => ToolType::OPTICAL,
                'last_calibration' => Carbon::now()->subMonths(2),
                'imei' => 'KEY-LK5001-B',
                'status' => ToolStatus::ACTIVE,
            ],

            // Mechanical Tools - Mahr Micromar 40 EWR (2 units)
            [
                'tool_name' => 'Micrometer QC Room',
                'tool_model' => 'Mahr Micromar 40 EWR',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonths(5),
                'imei' => 'MAH-40EWR-001',
                'status' => ToolStatus::ACTIVE,
            ],
            [
                'tool_name' => 'Micrometer Production',
                'tool_model' => 'Mahr Micromar 40 EWR',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonths(1),
                'imei' => 'MAH-40EWR-002',
                'status' => ToolStatus::ACTIVE,
            ],

            // Optical Tools - Keyence LS-7000 (1 unit)
            [
                'tool_name' => 'Laser Scanner Production Line',
                'tool_model' => 'Keyence LS-7000',
                'tool_type' => ToolType::OPTICAL,
                'last_calibration' => Carbon::now()->subMonths(3),
                'imei' => 'KEY-LS7000-001',
                'status' => ToolStatus::ACTIVE,
            ],

            // Mechanical Tools - Mitutoyo 293-340 (1 unit)
            [
                'tool_name' => 'Digital Micrometer Lab Main',
                'tool_model' => 'Mitutoyo 293-340',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => Carbon::now()->subMonth(),
                'imei' => 'MIT-293340-001',
                'status' => ToolStatus::ACTIVE,
            ],

            // Tool without calibration data (for testing nullable fields)
            [
                'tool_name' => 'Height Gauge Lab',
                'tool_model' => 'Mitutoyo HDS-30C',
                'tool_type' => ToolType::MECHANICAL,
                'last_calibration' => null,
                'imei' => 'MIT-HDS30C-001',
                'status' => ToolStatus::ACTIVE,
            ],
        ];

        foreach ($tools as $toolData) {
            Tool::create($toolData);
        }

        $this->command->info('Successfully seeded ' . count($tools) . ' tools!');
        $this->command->info('');
        $this->command->info('Tool Models Summary:');
        $this->command->info('- Mitutoyo CD-6 (Mechanical): 3 units (2 active, 1 inactive)');
        $this->command->info('- Keyence LK-G5001 (Optical): 2 units (active)');
        $this->command->info('- Mahr Micromar 40 EWR (Mechanical): 2 units (active)');
        $this->command->info('- Keyence LS-7000 (Optical): 1 unit (active)');
        $this->command->info('- Mitutoyo 293-340 (Mechanical): 1 unit (active)');
        $this->command->info('- Mitutoyo HDS-30C (Mechanical): 1 unit (active, no calibration data)');
    }
}

