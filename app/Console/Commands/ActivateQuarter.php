<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Quarter;

class ActivateQuarter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quarter:activate {year?} {quarter?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate a quarter for product creation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year');
        $quarterName = $this->argument('quarter');

        // If no arguments provided, show current status and prompt
        if (!$year || !$quarterName) {
            return $this->showStatusAndPrompt();
        }

        // Validate quarter name
        if (!in_array($quarterName, ['Q1', 'Q2', 'Q3', 'Q4'])) {
            $this->error('Invalid quarter name. Must be Q1, Q2, Q3, or Q4');
            return 1;
        }

        // Find the quarter
        $quarter = Quarter::where('year', $year)
            ->where('name', $quarterName)
            ->first();

        if (!$quarter) {
            $this->error("Quarter {$quarterName} {$year} not found in database.");
            
            if ($this->confirm("Would you like to generate quarters for year {$year}?", true)) {
                $this->info("Generating quarters for {$year}...");
                Quarter::generateQuartersForYear($year);
                
                $quarter = Quarter::where('year', $year)
                    ->where('name', $quarterName)
                    ->first();
                
                if (!$quarter) {
                    $this->error("Failed to create quarter {$quarterName} {$year}");
                    return 1;
                }
                
                $this->info("Quarters for {$year} generated successfully!");
            } else {
                return 1;
            }
        }

        // Activate the quarter
        $quarter->setAsActive();
        $this->info("✓ Quarter {$quarterName} {$year} has been activated successfully!");
        
        // Show updated status
        $this->showCurrentActiveQuarter();

        return 0;
    }

    /**
     * Show current status and prompt for quarter to activate
     */
    private function showStatusAndPrompt()
    {
        $this->info('=== Quarter Management ===');
        $this->newLine();

        // Show current active quarter
        $activeQuarter = Quarter::getActiveQuarter();
        
        if ($activeQuarter) {
            $this->info("Current Active Quarter: {$activeQuarter->name} {$activeQuarter->year}");
            $this->line("  - Start: {$activeQuarter->start_date->format('Y-m-d')}");
            $this->line("  - End: {$activeQuarter->end_date->format('Y-m-d')}");
        } else {
            $this->warn('⚠ No active quarter found!');
            $this->warn('  Products cannot be created without an active quarter.');
        }
        
        $this->newLine();

        // Show all available quarters
        $quarters = Quarter::orderBy('year', 'desc')
            ->orderByRaw("FIELD(name, 'Q1', 'Q2', 'Q3', 'Q4')")
            ->get();

        if ($quarters->isEmpty()) {
            $this->warn('No quarters found in database.');
            
            if ($this->confirm('Would you like to generate quarters for the current year?', true)) {
                $currentYear = date('Y');
                $this->info("Generating quarters for {$currentYear}...");
                Quarter::generateQuartersForYear($currentYear);
                
                $this->info("Quarters generated successfully!");
                $this->newLine();
                
                // Reload quarters
                $quarters = Quarter::orderBy('year', 'desc')
                    ->orderByRaw("FIELD(name, 'Q1', 'Q2', 'Q3', 'Q4')")
                    ->get();
            } else {
                return 1;
            }
        }

        $this->info('Available Quarters:');
        $this->table(
            ['ID', 'Quarter', 'Year', 'Period', 'Status'],
            $quarters->map(function ($q) {
                return [
                    $q->id,
                    $q->name,
                    $q->year,
                    $q->start_date->format('M d') . ' - ' . $q->end_date->format('M d'),
                    $q->is_active ? '✓ ACTIVE' : 'Inactive'
                ];
            })
        );
        
        $this->newLine();

        // Prompt for quarter to activate
        if ($this->confirm('Would you like to activate a quarter?', !$activeQuarter)) {
            $year = $this->ask('Enter year (e.g., 2024, 2025)');
            $quarterName = strtoupper($this->ask('Enter quarter (Q1, Q2, Q3, or Q4)'));
            
            $this->newLine();
            $this->call('quarter:activate', [
                'year' => $year,
                'quarter' => $quarterName
            ]);
        }

        return 0;
    }

    /**
     * Show current active quarter
     */
    private function showCurrentActiveQuarter()
    {
        $this->newLine();
        $activeQuarter = Quarter::getActiveQuarter();
        
        if ($activeQuarter) {
            $this->info('Current Active Quarter:');
            $this->line("  Quarter: {$activeQuarter->name} {$activeQuarter->year}");
            $this->line("  Period: {$activeQuarter->start_date->format('Y-m-d')} to {$activeQuarter->end_date->format('Y-m-d')}");
        }
    }
}

