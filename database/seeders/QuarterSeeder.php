<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quarter;

class QuarterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate quarters untuk 2024 dan 2025
        Quarter::generateQuartersForYear(2024);
        Quarter::generateQuartersForYear(2025);
        
        // Set Q4 2024 sebagai active quarter
        $currentQuarter = Quarter::where('year', 2024)->where('name', 'Q4')->first();
        if ($currentQuarter) {
            $currentQuarter->setAsActive();
            $this->command->info('Q4 2024 set as active quarter');
        } else {
            $this->command->error('Q4 2024 not found!');
        }
        
        $this->command->info('Quarters berhasil dibuat untuk tahun 2024 dan 2025');
    }
}
