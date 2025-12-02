<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tool;
use App\Models\Notification;
use App\Models\LoginUser;

class CheckToolCalibrationDue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-tool-calibration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for tools that need calibration within 7 days and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for tools requiring calibration...');

        // Get tools that are ACTIVE and calibration due in 0-7 days
        $today = now()->startOfDay();
        $sevenDaysLater = now()->addDays(7)->endOfDay();

        $tools = Tool::where('status', 'ACTIVE')
            ->whereNotNull('next_calibration_at')
            ->whereBetween('next_calibration_at', [$today, $sevenDaysLater])
            ->get();

        if ($tools->isEmpty()) {
            $this->info('No tools require calibration in the next 7 days.');
            return 0;
        }

        // Get ALL users to notify (not just admin/superadmin)
        $recipients = LoginUser::all();

        $notificationCount = 0;

        foreach ($tools as $tool) {
            $daysRemaining = now()->diffInDays($tool->next_calibration_at, false);
            $daysRemaining = max(0, ceil($daysRemaining)); // Ensure non-negative

            foreach ($recipients as $user) {
                // Check if notification already sent today for this tool and user
                $existingNotification = Notification::where('user_id', $user->id)
                    ->where('reference_type', 'tool')
                    ->where('reference_id', $tool->id)
                    ->where('type', 'TOOL_CALIBRATION_DUE')
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (!$existingNotification) {
                    Notification::createToolCalibrationDue($user->id, $tool, $daysRemaining);
                    $notificationCount++;
                }
            }
        }

        $this->info("Sent {$notificationCount} calibration due notifications for {$tools->count()} tools.");
        
        return 0;
    }
}

