<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quarter;

class QuarterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generate quarters dari 2025 sampai 2030
     * Current date: 2025-04-12 (Q2 2025)
     */
    public function run(): void
    {
        // Generate quarters dari 2025 sampai 2030
        for ($year = 2025; $year <= 2030; $year++) {
            Quarter::generateQuartersForYear($year);
            $this->command->info("Quarters berhasil dibuat untuk tahun {$year}");
        }
        
        // Set Q2 2025 sebagai active quarter (current: April 2025)
        $currentQuarter = Quarter::where('year', 2025)->where('name', 'Q2')->first();
        if ($currentQuarter) {
            $currentQuarter->setAsActive();
            $this->command->info('Q2 2025 set as active quarter');
        } else {
            $this->command->error('Q2 2025 not found!');
        }
        
        $this->command->info('Quarters berhasil dibuat untuk tahun 2025 sampai 2030');
    }
}
