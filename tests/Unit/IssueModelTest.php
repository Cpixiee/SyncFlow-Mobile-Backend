<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\LoginUser;
use App\Enums\IssueStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IssueModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_issue_has_creator_relationship()
    {
        $user = LoginUser::factory()->create();
        $issue = Issue::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(LoginUser::class, $issue->creator);
        $this->assertEquals($user->id, $issue->creator->id);
    }

    /** @test */
    public function test_issue_has_comments_relationship()
    {
        $issue = Issue::factory()->create();
        $comment1 = IssueComment::factory()->create(['issue_id' => $issue->id]);
        $comment2 = IssueComment::factory()->create(['issue_id' => $issue->id]);

        $this->assertCount(2, $issue->comments);
        $this->assertInstanceOf(IssueComment::class, $issue->comments->first());
    }

    /** @test */
    public function test_issue_status_is_cast_to_enum()
    {
        $issue = Issue::factory()->create(['status' => IssueStatus::PENDING]);

        $this->assertInstanceOf(IssueStatus::class, $issue->status);
        $this->assertEquals(IssueStatus::PENDING, $issue->status);
    }

    /** @test */
    public function test_issue_due_date_is_cast_to_date()
    {
        $issue = Issue::factory()->create(['due_date' => '2025-12-31']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $issue->due_date);
        $this->assertEquals('2025-12-31', $issue->due_date->format('Y-m-d'));
    }

    /** @test */
    public function test_scope_by_status_filters_correctly()
    {
        Issue::factory()->create(['status' => IssueStatus::PENDING]);
        Issue::factory()->create(['status' => IssueStatus::PENDING]);
        Issue::factory()->create(['status' => IssueStatus::SOLVED]);

        $pendingIssues = Issue::byStatus('PENDING')->get();

        $this->assertCount(2, $pendingIssues);
        $this->assertTrue($pendingIssues->every(fn($issue) => $issue->status === IssueStatus::PENDING));
    }

    /** @test */
    public function test_scope_by_creator_filters_correctly()
    {
        $user1 = LoginUser::factory()->create();
        $user2 = LoginUser::factory()->create();

        Issue::factory()->count(2)->create(['created_by' => $user1->id]);
        Issue::factory()->create(['created_by' => $user2->id]);

        $user1Issues = Issue::byCreator($user1->id)->get();

        $this->assertCount(2, $user1Issues);
        $this->assertTrue($user1Issues->every(fn($issue) => $issue->created_by === $user1->id));
    }

    /** @test */
    public function test_scope_pending_returns_only_pending_issues()
    {
        Issue::factory()->pending()->count(2)->create();
        Issue::factory()->solved()->create();

        $pendingIssues = Issue::pending()->get();

        $this->assertCount(2, $pendingIssues);
        $this->assertTrue($pendingIssues->every(fn($issue) => $issue->status === IssueStatus::PENDING));
    }

    /** @test */
    public function test_scope_on_going_returns_only_on_going_issues()
    {
        Issue::factory()->onGoing()->count(2)->create();
        Issue::factory()->pending()->create();

        $onGoingIssues = Issue::onGoing()->get();

        $this->assertCount(2, $onGoingIssues);
        $this->assertTrue($onGoingIssues->every(fn($issue) => $issue->status === IssueStatus::ON_GOING));
    }

    /** @test */
    public function test_scope_solved_returns_only_solved_issues()
    {
        Issue::factory()->solved()->count(2)->create();
        Issue::factory()->pending()->create();

        $solvedIssues = Issue::solved()->get();

        $this->assertCount(2, $solvedIssues);
        $this->assertTrue($solvedIssues->every(fn($issue) => $issue->status === IssueStatus::SOLVED));
    }

    /** @test */
    public function test_is_pending_method_returns_correct_boolean()
    {
        $pendingIssue = Issue::factory()->pending()->create();
        $solvedIssue = Issue::factory()->solved()->create();

        $this->assertTrue($pendingIssue->isPending());
        $this->assertFalse($solvedIssue->isPending());
    }

    /** @test */
    public function test_is_on_going_method_returns_correct_boolean()
    {
        $onGoingIssue = Issue::factory()->onGoing()->create();
        $pendingIssue = Issue::factory()->pending()->create();

        $this->assertTrue($onGoingIssue->isOnGoing());
        $this->assertFalse($pendingIssue->isOnGoing());
    }

    /** @test */
    public function test_is_solved_method_returns_correct_boolean()
    {
        $solvedIssue = Issue::factory()->solved()->create();
        $pendingIssue = Issue::factory()->pending()->create();

        $this->assertTrue($solvedIssue->isSolved());
        $this->assertFalse($pendingIssue->isSolved());
    }

    /** @test */
    public function test_issue_comment_has_issue_relationship()
    {
        $issue = Issue::factory()->create();
        $comment = IssueComment::factory()->create(['issue_id' => $issue->id]);

        $this->assertInstanceOf(Issue::class, $comment->issue);
        $this->assertEquals($issue->id, $comment->issue->id);
    }

    /** @test */
    public function test_issue_comment_has_user_relationship()
    {
        $user = LoginUser::factory()->create();
        $comment = IssueComment::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(LoginUser::class, $comment->user);
        $this->assertEquals($user->id, $comment->user->id);
    }
}

