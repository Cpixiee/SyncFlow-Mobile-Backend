<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Notification;
use App\Models\Tool;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\ProductMeasurement;
use App\Enums\IssueStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = LoginUser::factory()->create([
            'role' => 'operator',
            'username' => 'testuser',
            'employee_id' => 'EMP001'
        ]);

        $this->admin = LoginUser::factory()->create([
            'role' => 'admin',
            'username' => 'testadmin',
            'employee_id' => 'EMP002'
        ]);
    }

    /**
     * Test: Get notifications list
     */
    public function test_get_notifications_list()
    {
        // Create notifications for user
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_read' => false
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => true
        ]);

        // Get notifications
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'http_code',
                'message',
                'error_id',
                'data' => [
                    'docs' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'message',
                            'reference_type',
                            'reference_id',
                            'metadata',
                            'is_read',
                            'read_at',
                            'created_at'
                        ]
                    ],
                    'metadata' => [
                        'current_page',
                        'total_page',
                        'limit',
                        'total_docs'
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'total_docs' => 8
                    ]
                ]
            ]);
    }

    /**
     * Test: Filter notifications by read status
     */
    public function test_filter_notifications_by_read_status()
    {
        // Create notifications
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => false
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => true
        ]);

        // Get unread only
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?is_read=0');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'total_docs' => 3
                    ]
                ]
            ]);

        // Get read only
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?is_read=1');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'total_docs' => 2
                    ]
                ]
            ]);
    }

    /**
     * Test: Filter notifications by type
     */
    public function test_filter_notifications_by_type()
    {
        // Create different types
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'TOOL_CALIBRATION_DUE'
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'NEW_ISSUE'
        ]);

        // Filter by type
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?type=NEW_ISSUE');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'total_docs' => 3
                    ]
                ]
            ]);
    }

    /**
     * Test: Get unread count
     */
    public function test_get_unread_count()
    {
        // Create notifications
        Notification::factory()->count(7)->create([
            'user_id' => $this->user->id,
            'is_read' => false
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => true
        ]);

        // Get unread count
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'http_code' => 200,
                'data' => [
                    'unread_count' => 7
                ]
            ]);
    }

    /**
     * Test: Mark single notification as read
     */
    public function test_mark_notification_as_read()
    {
        // Create unread notification
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
            'read_at' => null
        ]);

        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);

        // Mark as read
        $response = $this->actingAsUser($this->user)
            ->postJson("/api/v1/notifications/{$notification->id}/mark-as-read");

        $response->assertStatus(200)
            ->assertJson([
                'http_code' => 200,
                'message' => 'Notification marked as read',
                'data' => [
                    'id' => $notification->id,
                    'is_read' => true
                ]
            ]);

        // Verify in database
        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test: Mark all notifications as read
     */
    public function test_mark_all_notifications_as_read()
    {
        // Create unread notifications
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_read' => false
        ]);

        // Verify all are unread
        $this->assertEquals(5, Notification::where('user_id', $this->user->id)->where('is_read', false)->count());

        // Mark all as read
        $response = $this->actingAsUser($this->user)
            ->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertStatus(200)
            ->assertJson([
                'http_code' => 200,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => 5
                ]
            ]);

        // Verify all are read
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->where('is_read', false)->count());
        $this->assertEquals(5, Notification::where('user_id', $this->user->id)->where('is_read', true)->count());
    }

    /**
     * Test: Delete single notification
     */
    public function test_delete_notification()
    {
        // Create notification
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id
        ]);

        $notificationId = $notification->id;

        // Delete
        $response = $this->actingAsUser($this->user)
            ->deleteJson("/api/v1/notifications/{$notificationId}");

        $response->assertStatus(200)
            ->assertJson([
                'http_code' => 200,
                'message' => 'Notification deleted successfully',
                'data' => [
                    'deleted' => true
                ]
            ]);

        // Verify deleted
        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId
        ]);
    }

    /**
     * Test: Delete all read notifications
     */
    public function test_delete_all_read_notifications()
    {
        // Create read and unread notifications
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => true
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => false
        ]);

        // Verify counts
        $this->assertEquals(5, Notification::where('user_id', $this->user->id)->count());

        // Delete all read
        $response = $this->actingAsUser($this->user)
            ->deleteJson('/api/v1/notifications/all-read');

        $response->assertStatus(200)
            ->assertJson([
                'http_code' => 200,
                'message' => 'All read notifications deleted',
                'data' => [
                    'deleted_count' => 3
                ]
            ]);

        // Verify only unread remain
        $this->assertEquals(2, Notification::where('user_id', $this->user->id)->count());
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->where('is_read', true)->count());
    }

    /**
     * Test: User can only access their own notifications
     */
    public function test_user_can_only_access_own_notifications()
    {
        // Create notification for another user
        $otherUser = LoginUser::factory()->create();
        $otherNotification = Notification::factory()->create([
            'user_id' => $otherUser->id
        ]);

        // Try to mark other user's notification as read
        $response = $this->actingAsUser($this->user)
            ->postJson("/api/v1/notifications/{$otherNotification->id}/mark-as-read");

        $response->assertStatus(404)
            ->assertJson([
                'http_code' => 404,
                'message' => 'Notification tidak ditemukan'
            ]);

        // Try to delete other user's notification
        $response = $this->actingAsUser($this->user)
            ->deleteJson("/api/v1/notifications/{$otherNotification->id}");

        $response->assertStatus(404);
    }

    /**
     * Test: Tool calibration notification creation
     */
    public function test_tool_calibration_notification_sent()
    {
        // Create tool that needs calibration in 3 days
        $tool = Tool::factory()->create([
            'tool_name' => 'Digital Caliper',
            'tool_model' => 'DC-100',
            'status' => 'ACTIVE',
            'next_calibration_at' => now()->addDays(3)
        ]);

        // Run command
        Artisan::call('notifications:check-tool-calibration');

        // Assert notification created for all users
        $this->assertDatabaseHas('notifications', [
            'type' => 'TOOL_CALIBRATION_DUE',
            'reference_type' => 'tool',
            'reference_id' => $tool->id,
            'user_id' => $this->user->id
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => 'TOOL_CALIBRATION_DUE',
            'reference_type' => 'tool',
            'reference_id' => $tool->id,
            'user_id' => $this->admin->id
        ]);
    }

    /**
     * Test: Issue overdue notification
     */
    public function test_issue_overdue_notification_sent()
    {
        // Create overdue issue
        $issue = Issue::factory()->create([
            'issue_name' => 'Test Issue',
            'description' => 'Test description',
            'status' => IssueStatus::PENDING,
            'due_date' => now()->subDays(2),
            'created_by' => $this->admin->id
        ]);

        // Run command
        Artisan::call('notifications:check-overdue-issues');

        // Assert notification created for all users
        $this->assertDatabaseHas('notifications', [
            'type' => 'ISSUE_OVERDUE',
            'reference_type' => 'issue',
            'reference_id' => $issue->id,
            'user_id' => $this->user->id
        ]);

        // Check metadata
        $notification = Notification::where('type', 'ISSUE_OVERDUE')
            ->where('reference_id', $issue->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertArrayHasKey('days_overdue', $notification->metadata);
        $this->assertGreaterThanOrEqual(2, $notification->metadata['days_overdue']);
    }

    /**
     * Test: New issue notification
     */
    public function test_new_issue_notification_sent()
    {
        // Create issue
        $response = $this->actingAsUser($this->admin)
            ->postJson('/api/v1/issues', [
                'issue_name' => 'New Test Issue',
                'description' => 'Test description for notification',
                'status' => 'PENDING',
                'due_date' => now()->addDays(7)->format('Y-m-d')
            ]);

        $response->assertStatus(201);

        $issueId = $response->json('data.id');

        // Assert notification sent to other users (not creator)
        $this->assertDatabaseHas('notifications', [
            'type' => 'NEW_ISSUE',
            'reference_type' => 'issue',
            'reference_id' => $issueId,
            'user_id' => $this->user->id // Regular user should receive
        ]);

        // Creator should NOT receive notification
        $this->assertDatabaseMissing('notifications', [
            'type' => 'NEW_ISSUE',
            'reference_type' => 'issue',
            'reference_id' => $issueId,
            'user_id' => $this->admin->id // Creator
        ]);
    }

    /**
     * Test: New comment notification
     */
    public function test_new_comment_notification_sent()
    {
        // Create issue
        $issue = Issue::factory()->create([
            'created_by' => $this->admin->id
        ]);

        // Add comment as regular user
        $response = $this->actingAsUser($this->user)
            ->postJson("/api/v1/issues/{$issue->id}/comments", [
                'comment' => 'This is a test comment for notification'
            ]);

        $response->assertStatus(201);

        $commentId = $response->json('data.id');

        // Assert notification sent to admin (issue creator)
        $this->assertDatabaseHas('notifications', [
            'type' => 'NEW_COMMENT',
            'reference_type' => 'issue_comment',
            'reference_id' => $commentId,
            'user_id' => $this->admin->id
        ]);

        // Commenter should NOT receive notification
        $this->assertDatabaseMissing('notifications', [
            'type' => 'NEW_COMMENT',
            'reference_type' => 'issue_comment',
            'reference_id' => $commentId,
            'user_id' => $this->user->id // Commenter
        ]);
    }

    /**
     * Test: No duplicate notifications sent on same day
     */
    public function test_no_duplicate_tool_calibration_notifications()
    {
        // Create tool
        $tool = Tool::factory()->create([
            'status' => 'ACTIVE',
            'next_calibration_at' => now()->addDays(5)
        ]);

        // Run command first time
        Artisan::call('notifications:check-tool-calibration');

        $firstCount = Notification::where('type', 'TOOL_CALIBRATION_DUE')
            ->where('reference_id', $tool->id)
            ->where('user_id', $this->user->id)
            ->count();

        $this->assertEquals(1, $firstCount);

        // Run command again (same day)
        Artisan::call('notifications:check-tool-calibration');

        $secondCount = Notification::where('type', 'TOOL_CALIBRATION_DUE')
            ->where('reference_id', $tool->id)
            ->where('user_id', $this->user->id)
            ->count();

        // Should still be 1 (no duplicate)
        $this->assertEquals(1, $secondCount);
    }

    /**
     * Test: Pagination works correctly
     */
    public function test_notifications_pagination()
    {
        // Create 25 notifications
        Notification::factory()->count(25)->create([
            'user_id' => $this->user->id
        ]);

        // Get first page (limit 10)
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'current_page' => 1,
                        'total_page' => 3,
                        'limit' => 10,
                        'total_docs' => 25
                    ]
                ]
            ]);

        $this->assertCount(10, $response->json('data.docs'));

        // Get second page
        $response = $this->actingAsUser($this->user)
            ->getJson('/api/v1/notifications?page=2&limit=10');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'metadata' => [
                        'current_page' => 2
                    ]
                ]
            ]);

        $this->assertCount(10, $response->json('data.docs'));
    }

    /**
     * Test: Notification metadata contains correct information
     */
    public function test_notification_metadata_structure()
    {
        $tool = Tool::factory()->create([
            'tool_name' => 'Test Tool',
            'tool_model' => 'TM-001',
            'status' => 'ACTIVE',
            'next_calibration_at' => now()->addDays(3)
        ]);

        // Create notification
        $notification = Notification::createToolCalibrationDue($this->user->id, $tool, 3);

        // Check metadata structure
        $this->assertIsArray($notification->metadata);
        $this->assertArrayHasKey('tool_id', $notification->metadata);
        $this->assertArrayHasKey('tool_name', $notification->metadata);
        $this->assertArrayHasKey('tool_model', $notification->metadata);
        $this->assertArrayHasKey('next_calibration_at', $notification->metadata);
        $this->assertArrayHasKey('days_remaining', $notification->metadata);

        $this->assertEquals($tool->id, $notification->metadata['tool_id']);
        $this->assertEquals(3, $notification->metadata['days_remaining']);
    }

    /**
     * Test: Unauthenticated user cannot access notifications
     */
    public function test_unauthenticated_user_cannot_access_notifications()
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/notifications/unread-count');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/notifications/1/mark-as-read');
        $response->assertStatus(401);
    }
}
