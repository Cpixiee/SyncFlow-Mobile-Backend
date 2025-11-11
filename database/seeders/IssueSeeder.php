<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\LoginUser;
use App\Enums\IssueStatus;

class IssueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin and operator user
        $admin = LoginUser::where('role', 'admin')->first();
        $superadmin = LoginUser::where('role', 'superadmin')->first();
        $operator = LoginUser::where('role', 'operator')->first();

        if (!$admin || !$superadmin || !$operator) {
            $this->command->warn('Please run LoginUserSeeder first to create users.');
            return;
        }

        // Create sample issues
        $issue1 = Issue::create([
            'issue_name' => 'Calibration error on Machine A',
            'description' => 'Machine A shows inconsistent readings during morning shift. Needs immediate attention.',
            'status' => IssueStatus::PENDING,
            'created_by' => $admin->id,
            'due_date' => now()->addDays(3),
        ]);

        $issue2 = Issue::create([
            'issue_name' => 'Equipment malfunction on Line 3',
            'description' => 'Machine shows inconsistent readings during morning shift. Technical team is working on it.',
            'status' => IssueStatus::ON_GOING,
            'created_by' => $admin->id,
            'due_date' => now()->addDays(7),
        ]);

        $issue3 = Issue::create([
            'issue_name' => 'Calibration error on Machine A',
            'description' => 'Machine A shows inconsistent readings during morning shift. Issue has been resolved.',
            'status' => IssueStatus::SOLVED,
            'created_by' => $superadmin->id,
            'due_date' => now()->addDays(1),
        ]);

        $issue4 = Issue::create([
            'issue_name' => 'Quality Control deviation in batch 127',
            'description' => 'Batch 127 shows quality control parameters outside acceptable range.',
            'status' => IssueStatus::SOLVED,
            'created_by' => $admin->id,
            'due_date' => now()->subDays(2),
        ]);

        // Add comments to issues
        IssueComment::create([
            'issue_id' => $issue2->id,
            'user_id' => $operator->id,
            'comment' => 'Working on it. Bringing in tech team to look into this with others on standby.',
        ]);

        IssueComment::create([
            'issue_id' => $issue3->id,
            'user_id' => $admin->id,
            'comment' => 'Issue resolved. Machine is back to normal operation.',
        ]);

        $this->command->info('Sample issues and comments created successfully!');
    }
}

