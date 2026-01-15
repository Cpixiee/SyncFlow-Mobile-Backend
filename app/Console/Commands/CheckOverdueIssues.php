<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\LoginUser;
use App\Enums\IssueStatus;

class CheckOverdueIssues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-overdue-issues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue issues and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue issues...');

        // Get issues that are PENDING or ON_GOING and past due date
        // Note: Status enum uses PENDING and ON_GOING (not OPEN/IN_PROGRESS)
        $overdueIssues = Issue::whereIn('status', [IssueStatus::PENDING, IssueStatus::ON_GOING])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->get();

        if ($overdueIssues->isEmpty()) {
            $this->info('No overdue issues found.');
            return 0;
        }

        $notificationCount = 0;

        foreach ($overdueIssues as $issue) {
            $daysOverdue = abs(now()->diffInDays($issue->due_date, false));
            $daysOverdue = ceil($daysOverdue);

            // Get recipients: Semua user (semua role)
            $recipients = LoginUser::all();

            foreach ($recipients as $user) {
                // Check if notification already sent today for this issue and user
                $existingNotification = Notification::where('user_id', $user->id)
                    ->where('reference_type', 'issue')
                    ->where('reference_id', $issue->id)
                    ->where('type', 'ISSUE_OVERDUE')
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (!$existingNotification) {
                    Notification::createIssueOverdue($user->id, $issue, $daysOverdue);
                    $notificationCount++;
                }
            }
        }

        $this->info("Sent {$notificationCount} overdue notifications for {$overdueIssues->count()} issues.");
        
        return 0;
    }
}

