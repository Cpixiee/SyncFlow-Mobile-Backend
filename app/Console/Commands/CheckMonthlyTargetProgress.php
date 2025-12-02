<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductMeasurement;
use App\Models\Notification;
use App\Models\LoginUser;
use Carbon\Carbon;

class CheckMonthlyTargetProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-monthly-target';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check monthly target progress and send warning if behind schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking monthly target progress...');

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        // Calculate which week we're in (1-4 or 5)
        $currentWeek = ceil($now->day / 7);
        $totalWeeks = ceil($endOfMonth->day / 7);
        
        // Get monthly target (total measurements expected this month)
        // For this example, let's assume target is based on active products count
        $totalProducts = \App\Models\Product::count();
        $monthlyTarget = $totalProducts; // 1 measurement per product per month
        
        // Get actual completed measurements this month
        $actualInspections = ProductMeasurement::where('status', 'COMPLETED')
            ->whereBetween('measured_at', [$startOfMonth, $now])
            ->count();
        
        // Calculate expected progress based on current week
        $expectedPercentage = round(($currentWeek / $totalWeeks) * 100, 2);
        $actualPercentage = $monthlyTarget > 0 
            ? round(($actualInspections / $monthlyTarget) * 100, 2) 
            : 0;
        
        $this->info("Month: {$now->format('F Y')}");
        $this->info("Current Week: {$currentWeek} of {$totalWeeks}");
        $this->info("Monthly Target: {$monthlyTarget} inspections");
        $this->info("Actual Completed: {$actualInspections} inspections");
        $this->info("Expected Progress: {$expectedPercentage}%");
        $this->info("Actual Progress: {$actualPercentage}%");
        
        // Send notification if actual < expected
        if ($actualPercentage < $expectedPercentage) {
            $gap = round($expectedPercentage - $actualPercentage, 2);
            
            $this->warn("Behind schedule by {$gap}%! Sending notifications...");
            
            // Get ALL users to notify (not just admin/superadmin)
            $recipients = LoginUser::all();
            
            $targetData = [
                'month' => $now->format('F Y'),
                'current_week' => $currentWeek,
                'total_weeks' => $totalWeeks,
                'monthly_target' => $monthlyTarget,
                'actual_inspections' => $actualInspections,
                'expected_percentage' => $expectedPercentage,
                'actual_percentage' => $actualPercentage,
                'gap_percentage' => $gap
            ];
            
            $notificationCount = 0;
            
            foreach ($recipients as $user) {
                // Check if notification already sent this week
                $weekStart = $now->copy()->startOfWeek();
                $existingNotification = Notification::where('user_id', $user->id)
                    ->where('type', 'MONTHLY_TARGET_WARNING')
                    ->where('created_at', '>=', $weekStart)
                    ->exists();
                
                if (!$existingNotification) {
                    Notification::createMonthlyTargetWarning($user->id, $targetData);
                    $notificationCount++;
                }
            }
            
            $this->info("Sent {$notificationCount} monthly target warning notifications.");
        } else {
            $this->info("On track! No notifications needed.");
        }
        
        return 0;
    }
}

