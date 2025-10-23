<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MeasurementInstrument;

class MeasurementInstrumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $instruments = [
            [
                'name' => 'Digital Caliper',
                'model' => 'DC-150',
                'serial_number' => 'DC001-2024',
                'manufacturer' => 'Mitutoyo',
                'status' => 'ACTIVE',
                'description' => 'High precision digital caliper for thickness measurements',
                'specifications' => [
                    'accuracy' => '±0.02mm',
                    'resolution' => '0.01mm',
                    'measuring_range' => '0-150mm'
                ],
                'last_calibration' => '2024-01-15',
                'next_calibration' => '2025-01-15'
            ],
            [
                'name' => 'Micrometer',
                'model' => 'MC-25',
                'serial_number' => 'MC002-2024',
                'manufacturer' => 'Mitutoyo',
                'status' => 'ACTIVE',
                'description' => 'Precision micrometer for small dimension measurements',
                'specifications' => [
                    'accuracy' => '±0.002mm',
                    'resolution' => '0.001mm',
                    'measuring_range' => '0-25mm'
                ],
                'last_calibration' => '2024-02-10',
                'next_calibration' => '2025-02-10'
            ],
            [
                'name' => 'Dial Gauge',
                'model' => 'DG-10',
                'serial_number' => 'DG003-2024',
                'manufacturer' => 'Mitutoyo',
                'status' => 'ACTIVE',
                'description' => 'Dial gauge for deviation measurements',
                'specifications' => [
                    'accuracy' => '±0.01mm',
                    'resolution' => '0.01mm',
                    'measuring_range' => '±10mm'
                ],
                'last_calibration' => '2024-03-05',
                'next_calibration' => '2025-03-05'
            ],
            [
                'name' => 'Tensile Testing Machine',
                'model' => 'TTM-5000',
                'serial_number' => 'TTM004-2024',
                'manufacturer' => 'Instron',
                'status' => 'ACTIVE',
                'description' => 'Universal testing machine for tensile strength measurements',
                'specifications' => [
                    'max_load' => '5000N',
                    'accuracy' => '±0.5%',
                    'load_resolution' => '0.1N'
                ],
                'last_calibration' => '2024-01-20',
                'next_calibration' => '2025-01-20'
            ],
            [
                'name' => 'Temperature Sensor',
                'model' => 'TS-100',
                'serial_number' => 'TS005-2024',
                'manufacturer' => 'Omega',
                'status' => 'MAINTENANCE',
                'description' => 'Digital temperature sensor for environmental measurements',
                'specifications' => [
                    'accuracy' => '±0.1°C',
                    'resolution' => '0.1°C',
                    'measuring_range' => '-50 to +200°C'
                ],
                'last_calibration' => '2023-12-15',
                'next_calibration' => '2024-12-15'
            ]
        ];

        foreach ($instruments as $instrument) {
            MeasurementInstrument::updateOrCreate(
                ['serial_number' => $instrument['serial_number']],
                $instrument
            );
        }
    }
}