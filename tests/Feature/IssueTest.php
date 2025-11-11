<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Enums\IssueStatus;
use Carbon\Carbon;

class IssueTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $superadminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = LoginUser::factory()->create([
            'username' => 'testadmin',
            'role' => 'admin',
            'password' => bcrypt('testpassword')
        ]);
        
        // Create superadmin user
        $this->superadminUser = LoginUser::factory()->create([
            'username' => 'testsuperadmin',
            'role' => 'superadmin',
            'password' => bcrypt('testpassword')
        ]);
        
        // Create regular user (operator)
        $this->regularUser = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'operator',
            'password' => bcrypt('testpassword')
        ]);
    }

    /** @test */
    public function test_can_get_all_issues_with_pagination()
    {
        // Create test issues
        Issue::factory()->count(3)->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?page=1&limit=10');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'data' => [
                'docs' => [
                    '*' => [
                        'id',
                        'issue_name',
                        'description',
                        'status',
                        'status_description',
                        'status_color',
                        'due_date',
                        'created_by' => [
                            'id',
                            'username',
                            'role'
                        ],
                        'comments_count',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'metadata' => [
                    'current_page',
                    'total_page',
                    'limit',
                    'total_docs'
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertCount(3, $data['docs']);
        $this->assertEquals(1, $data['metadata']['current_page']);
        $this->assertEquals(3, $data['metadata']['total_docs']);
    }

    /** @test */
    public function test_can_filter_issues_by_status()
    {
        Issue::factory()->create([
            'issue_name' => 'Pending Issue',
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);
        
        Issue::factory()->create([
            'issue_name' => 'Solved Issue',
            'status' => IssueStatus::SOLVED,
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?status=PENDING');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(1, $data['docs']);
        $this->assertEquals('PENDING', $data['docs'][0]['status']);
    }

    /** @test */
    public function test_can_search_issues()
    {
        Issue::factory()->create([
            'issue_name' => 'Calibration error on Machine A',
            'description' => 'Machine A shows inconsistent readings',
            'created_by' => $this->adminUser->id
        ]);
        
        Issue::factory()->create([
            'issue_name' => 'Equipment malfunction',
            'description' => 'Equipment shows error',
            'created_by' => $this->adminUser->id
        ]);

        // Search by issue name
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?search=Calibration');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(1, $data['docs']);
        $this->assertStringContainsString('Calibration', $data['docs'][0]['issue_name']);
    }

    /** @test */
    public function test_can_get_single_issue_with_comments()
    {
        $issue = Issue::factory()->create([
            'issue_name' => 'Test Issue',
            'created_by' => $this->adminUser->id
        ]);

        // Add some comments
        IssueComment::factory()->count(2)->create([
            'issue_id' => $issue->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson("/api/v1/issues/{$issue->id}");

        $this->assertApiSuccess($response);
        $response->assertJson([
            'data' => [
                'id' => $issue->id,
                'issue_name' => 'Test Issue',
            ]
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('comments', $data);
        $this->assertCount(2, $data['comments']);
    }

    /** @test */
    public function test_returns_404_when_issue_not_found()
    {
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues/999');

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Issue not found']);
    }

    /** @test */
    public function test_admin_can_create_issue()
    {
        $issueData = [
            'issue_name' => 'New Issue',
            'description' => 'This is a test issue',
            'status' => 'PENDING',
            'due_date' => Carbon::now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/issues', $issueData);

        $response->assertStatus(201);
        $this->assertApiResponseStructure($response, 201);
        
        $response->assertJson([
            'message' => 'Issue created successfully',
            'data' => [
                'issue_name' => 'New Issue',
                'description' => 'This is a test issue',
                'status' => 'PENDING'
            ]
        ]);

        $this->assertDatabaseHas('issues', [
            'issue_name' => 'New Issue',
            'created_by' => $this->adminUser->id
        ]);
    }

    /** @test */
    public function test_superadmin_can_create_issue()
    {
        $issueData = [
            'issue_name' => 'Superadmin Issue',
            'description' => 'This is created by superadmin',
            'status' => 'ON_GOING',
        ];

        $response = $this->actingAsUser($this->superadminUser)
            ->postJson('/api/v1/issues', $issueData);

        $response->assertStatus(201);
        $this->assertApiResponseStructure($response, 201);
        
        $this->assertDatabaseHas('issues', [
            'issue_name' => 'Superadmin Issue',
            'created_by' => $this->superadminUser->id
        ]);
    }

    /** @test */
    public function test_regular_user_cannot_create_issue()
    {
        $issueData = [
            'issue_name' => 'User Issue',
            'description' => 'This should fail',
            'status' => 'PENDING'
        ];

        $response = $this->actingAsUser($this->regularUser)
            ->postJson('/api/v1/issues', $issueData);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_validates_required_fields_on_create()
    {
        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/issues', []);

        $this->assertApiError($response, 400);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('issue_name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
    }

    /** @test */
    public function test_validates_status_enum()
    {
        $issueData = [
            'issue_name' => 'Test Issue',
            'description' => 'Test description',
            'status' => 'INVALID_STATUS'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/issues', $issueData);

        $this->assertApiError($response, 400);
    }

    /** @test */
    public function test_validates_due_date_is_not_in_past()
    {
        $issueData = [
            'issue_name' => 'Test Issue',
            'description' => 'Test description',
            'status' => 'PENDING',
            'due_date' => Carbon::now()->subDays(1)->format('Y-m-d')
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->postJson('/api/v1/issues', $issueData);

        $this->assertApiError($response, 400);
        $data = $response->json('data');
        $this->assertArrayHasKey('due_date', $data);
    }

    /** @test */
    public function test_any_authenticated_user_can_add_comment()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $commentData = [
            'comment' => 'This is a test comment'
        ];

        $response = $this->actingAsUser($this->regularUser)
            ->postJson("/api/v1/issues/{$issue->id}/comments", $commentData);

        $response->assertStatus(201);
        $this->assertApiResponseStructure($response, 201);
        
        $response->assertJson([
            'message' => 'Comment added successfully',
            'data' => [
                'comment' => 'This is a test comment',
                'user' => [
                    'username' => 'testuser'
                ]
            ]
        ]);

        $this->assertDatabaseHas('issue_comments', [
            'issue_id' => $issue->id,
            'user_id' => $this->regularUser->id,
            'comment' => 'This is a test comment'
        ]);
    }

    /** @test */
    public function test_cannot_add_comment_to_non_existent_issue()
    {
        $commentData = [
            'comment' => 'Test comment'
        ];

        $response = $this->actingAsUser($this->regularUser)
            ->postJson('/api/v1/issues/999/comments', $commentData);

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Issue not found']);
    }

    /** @test */
    public function test_validates_comment_is_required()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->postJson("/api/v1/issues/{$issue->id}/comments", []);

        $this->assertApiError($response, 400);
        $data = $response->json('data');
        $this->assertArrayHasKey('comment', $data);
    }

    /** @test */
    public function test_can_get_comments_for_issue()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        // Create multiple comments
        IssueComment::factory()->count(3)->create([
            'issue_id' => $issue->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson("/api/v1/issues/{$issue->id}/comments");

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        
        // Check structure
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('comment', $data[0]);
        $this->assertArrayHasKey('user', $data[0]);
        $this->assertArrayHasKey('created_at', $data[0]);
    }

    /** @test */
    public function test_comment_owner_can_delete_their_comment()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $comment = IssueComment::factory()->create([
            'issue_id' => $issue->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->deleteJson("/api/v1/issues/{$issue->id}/comments/{$comment->id}");

        $this->assertApiSuccess($response);
        $response->assertJson([
            'message' => 'Comment deleted successfully',
            'data' => ['deleted' => true]
        ]);

        $this->assertDatabaseMissing('issue_comments', ['id' => $comment->id]);
    }

    /** @test */
    public function test_admin_can_delete_any_comment()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $comment = IssueComment::factory()->create([
            'issue_id' => $issue->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson("/api/v1/issues/{$issue->id}/comments/{$comment->id}");

        $this->assertApiSuccess($response);
        $this->assertDatabaseMissing('issue_comments', ['id' => $comment->id]);
    }

    /** @test */
    public function test_regular_user_cannot_delete_others_comment()
    {
        $otherUser = LoginUser::factory()->create([
            'username' => 'otheruser',
            'role' => 'operator'
        ]);

        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $comment = IssueComment::factory()->create([
            'issue_id' => $issue->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->deleteJson("/api/v1/issues/{$issue->id}/comments/{$comment->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You do not have permission to delete this comment'
        ]);
    }

    /** @test */
    public function test_cannot_delete_non_existent_comment()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson("/api/v1/issues/{$issue->id}/comments/999");

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Comment not found']);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_issues()
    {
        $response = $this->getJson('/api/v1/issues');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_pagination_parameters_work_correctly()
    {
        // Create 25 issues
        Issue::factory()->count(25)->create([
            'created_by' => $this->adminUser->id
        ]);

        // Test page 1 with limit 10
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?page=1&limit=10');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertCount(10, $data['docs']);
        $this->assertEquals(1, $data['metadata']['current_page']);
        $this->assertEquals(3, $data['metadata']['total_page']);
        $this->assertEquals(10, $data['metadata']['limit']);
        $this->assertEquals(25, $data['metadata']['total_docs']);

        // Test page 2
        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?page=2&limit=10');

        $data = $response->json('data');
        $this->assertEquals(2, $data['metadata']['current_page']);
        $this->assertCount(10, $data['docs']);
    }

    /** @test */
    public function test_can_combine_filters_and_search()
    {
        Issue::factory()->create([
            'issue_name' => 'Calibration error pending',
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);
        
        Issue::factory()->create([
            'issue_name' => 'Calibration error solved',
            'status' => IssueStatus::SOLVED,
            'created_by' => $this->adminUser->id
        ]);
        
        Issue::factory()->create([
            'issue_name' => 'Equipment malfunction',
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson('/api/v1/issues?status=PENDING&search=Calibration');

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['docs']);
        $this->assertEquals('Calibration error pending', $data['docs'][0]['issue_name']);
    }

    /** @test */
    public function test_issue_includes_status_color()
    {
        $issue = Issue::factory()->create([
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->getJson("/api/v1/issues/{$issue->id}");

        $this->assertApiSuccess($response);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('status_color', $data);
        $this->assertEquals('orange', $data['status_color']);
    }

    /** @test */
    public function test_admin_can_update_issue()
    {
        $issue = Issue::factory()->create([
            'issue_name' => 'Old Issue Name',
            'description' => 'Old Description',
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);

        $updateData = [
            'issue_name' => 'Updated Issue Name',
            'status' => 'ON_GOING'
        ];

        $response = $this->actingAsUser($this->adminUser)
            ->putJson("/api/v1/issues/{$issue->id}", $updateData);

        $this->assertApiSuccess($response);
        $response->assertJson([
            'data' => [
                'issue_name' => 'Updated Issue Name',
                'status' => 'ON_GOING'
            ]
        ]);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'issue_name' => 'Updated Issue Name',
            'status' => 'ON_GOING'
        ]);
    }

    /** @test */
    public function test_superadmin_can_update_issue()
    {
        $issue = Issue::factory()->create([
            'status' => IssueStatus::PENDING,
            'created_by' => $this->adminUser->id
        ]);

        $updateData = ['status' => 'SOLVED'];

        $response = $this->actingAsUser($this->superadminUser)
            ->putJson("/api/v1/issues/{$issue->id}", $updateData);

        $this->assertApiSuccess($response);
        $response->assertJson([
            'data' => ['status' => 'SOLVED']
        ]);
    }

    /** @test */
    public function test_regular_user_cannot_update_issue()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->putJson("/api/v1/issues/{$issue->id}", ['status' => 'SOLVED']);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_admin_can_delete_issue()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        // Add some comments
        IssueComment::factory()->create([
            'issue_id' => $issue->id,
            'user_id' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson("/api/v1/issues/{$issue->id}");

        $this->assertApiSuccess($response);
        $response->assertJson([
            'message' => 'Issue deleted successfully',
            'data' => ['deleted' => true]
        ]);

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
        $this->assertDatabaseMissing('issue_comments', ['issue_id' => $issue->id]);
    }

    /** @test */
    public function test_superadmin_can_delete_issue()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->superadminUser)
            ->deleteJson("/api/v1/issues/{$issue->id}");

        $this->assertApiSuccess($response);
        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    /** @test */
    public function test_regular_user_cannot_delete_issue()
    {
        $issue = Issue::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->actingAsUser($this->regularUser)
            ->deleteJson("/api/v1/issues/{$issue->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function test_cannot_update_non_existent_issue()
    {
        $response = $this->actingAsUser($this->adminUser)
            ->putJson('/api/v1/issues/999', ['status' => 'SOLVED']);

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Issue not found']);
    }

    /** @test */
    public function test_cannot_delete_non_existent_issue()
    {
        $response = $this->actingAsUser($this->adminUser)
            ->deleteJson('/api/v1/issues/999');

        $this->assertApiError($response, 404);
        $response->assertJson(['message' => 'Issue not found']);
    }
}

