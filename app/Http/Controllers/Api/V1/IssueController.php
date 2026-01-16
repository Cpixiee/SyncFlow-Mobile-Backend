<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\Notification;
use App\Models\LoginUser;
use App\Enums\IssueStatus;
use App\Enums\IssueCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Tymon\JWTAuth\Facades\JWTAuth;

class IssueController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get list of issues
     * Query params:
     *   - page (optional): page number, default 1
     *   - limit (optional): items per page, default 10
     *   - status (optional): filter by PENDING/ON_GOING/SOLVED
     *   - search (optional): search by issue_name or description
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $query = Issue::with(['creator:id,username,role', 'comments.user:id,username,role']);

            // Filter by archived status (default: exclude archived)
            $includeArchived = $request->input('include_archived', false);
            if (!$includeArchived) {
                $query->notArchived();
            }

            // Filter by status
            if ($request->has('status')) {
                $status = strtoupper($request->input('status'));
                if (in_array($status, ['PENDING', 'ON_GOING', 'SOLVED'])) {
                    $query->where('status', $status);
                }
            }

            // Filter by category
            if ($request->has('category')) {
                $category = strtoupper($request->input('category'));
                if (in_array($category, ['CUSTOMER_CLAIM', 'INTERNAL_DEFECT', 'NON_CONFORMITY', 'QUALITY_INFORMATION', 'OTHER'])) {
                    $query->where('category', $category);
                }
            }

            // Search
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('issue_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $issues = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $transformedIssues = collect($issues->items())
                ->map(function ($issue) {
                    return [
                        'id' => $issue->id,
                        'issue_name' => $issue->issue_name,
                        'description' => $issue->description,
                        'status' => $issue->status->value,
                        'status_description' => $issue->status->getDescription(),
                        'status_color' => $issue->status->getColor(),
                        'category' => $issue->category->value,
                        'category_label' => $issue->category->getLabel(),
                        'is_archived' => $issue->is_archived,
                        'due_date' => $issue->due_date?->format('Y-m-d'),
                        'created_by' => [
                            'id' => $issue->creator->id,
                            'username' => $issue->creator->username,
                            'role' => $issue->creator->role,
                        ],
                        'comments_count' => $issue->comments->count(),
                        'created_at' => $issue->created_at->toISOString(),
                        'updated_at' => $issue->updated_at->toISOString(),
                    ];
                })->values()->all();

            return $this->paginationResponse(
                $transformedIssues,
                [
                    'current_page' => $issues->currentPage(),
                    'total_page' => $issues->lastPage(),
                    'limit' => $issues->perPage(),
                    'total_docs' => $issues->total(),
                ],
                'Issues retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving issues: ' . $e->getMessage(),
                'ISSUE_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Get single issue by ID with comments
     */
    public function show(int $id)
    {
        try {
            $issue = Issue::with(['creator:id,username,role,employee_id', 'comments.user:id,username,role'])
                ->find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            return $this->successResponse(
                [
                    'id' => $issue->id,
                    'issue_name' => $issue->issue_name,
                    'description' => $issue->description,
                    'status' => $issue->status->value,
                    'status_description' => $issue->status->getDescription(),
                    'status_color' => $issue->status->getColor(),
                    'category' => $issue->category->value,
                    'category_label' => $issue->category->getLabel(),
                    'is_archived' => $issue->is_archived,
                    'due_date' => $issue->due_date?->format('Y-m-d'),
                    'created_by' => [
                        'id' => $issue->creator->id,
                        'username' => $issue->creator->username,
                        'role' => $issue->creator->role,
                        'employee_id' => $issue->creator->employee_id,
                    ],
                    'comments' => $issue->comments->map(function ($comment) {
                        return [
                            'id' => $comment->id,
                            'comment' => $comment->comment,
                            'user' => [
                                'id' => $comment->user->id,
                                'username' => $comment->user->username,
                                'role' => $comment->user->role,
                            ],
                            'created_at' => $comment->created_at->toISOString(),
                        ];
                    })->values()->all(),
                    'created_at' => $issue->created_at->toISOString(),
                    'updated_at' => $issue->updated_at->toISOString(),
                ],
                'Issue retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving issue: ' . $e->getMessage(),
                'ISSUE_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Create new issue (Only admin and superadmin)
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'issue_name' => 'required|string|max:255',
                'description' => 'required|string',
                'status' => ['required', new Enum(IssueStatus::class)],
                'category' => ['nullable', new Enum(IssueCategory::class)],
                'due_date' => 'nullable|date|after_or_equal:today',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Create issue
            $issue = Issue::create([
                'issue_name' => $request->input('issue_name'),
                'description' => $request->input('description'),
                'status' => $request->input('status'),
                'category' => $request->input('category', IssueCategory::OTHER->value),
                'created_by' => $user->id,
                'due_date' => $request->input('due_date'),
            ]);

            // Load creator relationship
            $issue->load('creator:id,username,role');

            // Send notification for new issue
            $this->sendNewIssueNotification($issue);

            /** @var \Illuminate\Support\Carbon|null $dueDate */
            $dueDate = $issue->due_date;

            return $this->successResponse(
                [
                    'id' => $issue->id,
                    'issue_name' => $issue->issue_name,
                    'description' => $issue->description,
                    'status' => $issue->status->value,
                    'status_description' => $issue->status->getDescription(),
                    'status_color' => $issue->status->getColor(),
                    'due_date' => $dueDate?->format('Y-m-d'),
                    'created_by' => [
                        'id' => $issue->creator->id,
                        'username' => $issue->creator->username,
                        'role' => $issue->creator->role,
                    ],
                    'created_at' => $issue->created_at->toISOString(),
                    'updated_at' => $issue->updated_at->toISOString(),
                ],
                'Issue created successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error creating issue: ' . $e->getMessage(),
                'ISSUE_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Update existing issue (Only admin and superadmin)
     */
    public function update(Request $request, int $id)
    {
        try {
            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            // Build validation rules
            // Note: due_date validation removed 'after_or_equal:today' to allow status updates
            // without requiring due_date to be today or later (e.g., updating status to SOLVED for past due dates)
            $validator = Validator::make($request->all(), [
                'issue_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status' => ['nullable', new Enum(IssueStatus::class)],
                'category' => ['nullable', new Enum(IssueCategory::class)],
                'due_date' => 'nullable|date', // Removed 'after_or_equal:today' to allow status updates
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Update issue - hanya update field yang dikirim
            if ($request->has('issue_name')) {
                $issue->issue_name = $request->input('issue_name');
            }
            if ($request->has('description')) {
                $issue->description = $request->input('description');
            }
            if ($request->has('status')) {
                $issue->status = $request->input('status');
            }
            if ($request->has('category')) {
                $issue->category = $request->input('category');
            }
            if ($request->has('due_date')) {
                $issue->due_date = $request->input('due_date');
            }

            $issue->save();

            // Load creator relationship
            $issue->load('creator:id,username,role');

            /** @var \Illuminate\Support\Carbon|null $dueDate */
            $dueDate = $issue->due_date;

            return $this->successResponse(
                [
                    'id' => $issue->id,
                    'issue_name' => $issue->issue_name,
                    'description' => $issue->description,
                    'status' => $issue->status->value,
                    'status_description' => $issue->status->getDescription(),
                    'status_color' => $issue->status->getColor(),
                    'due_date' => $dueDate?->format('Y-m-d'),
                    'created_by' => [
                        'id' => $issue->creator->id,
                        'username' => $issue->creator->username,
                        'role' => $issue->creator->role,
                    ],
                    'created_at' => $issue->created_at->toISOString(),
                    'updated_at' => $issue->updated_at->toISOString(),
                ],
                'Issue updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error updating issue: ' . $e->getMessage(),
                'ISSUE_UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Delete issue (Only admin and superadmin)
     */
    public function destroy(int $id)
    {
        try {
            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            // Delete all comments first
            $issue->comments()->delete();

            // Delete issue
            $issue->delete();

            return $this->successResponse(
                ['deleted' => true],
                'Issue deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting issue: ' . $e->getMessage(),
                'ISSUE_DELETE_ERROR',
                500
            );
        }
    }

    /**
     * Add comment to issue (All authenticated users)
     */
    public function addComment(Request $request, int $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            $validator = Validator::make($request->all(), [
                'comment' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Create comment
            $comment = IssueComment::create([
                'issue_id' => $id,
                'user_id' => $user->id,
                'comment' => $request->input('comment'),
            ]);

            // Load user relationship
            $comment->load('user:id,username,role');

            // Send notification to issue followers (NEW_COMMENT trigger)
            $this->sendNewCommentNotification($comment, $issue, $user);

            return $this->successResponse(
                [
                    'id' => $comment->id,
                    'issue_id' => $comment->issue_id,
                    'comment' => $comment->comment,
                    'user' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'role' => $comment->user->role,
                    ],
                    'created_at' => $comment->created_at->toISOString(),
                ],
                'Comment added successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error adding comment: ' . $e->getMessage(),
                'COMMENT_ADD_ERROR',
                500
            );
        }
    }

    /**
     * Get comments for an issue
     */
    public function getComments(int $id)
    {
        try {
            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            $comments = IssueComment::where('issue_id', $id)
                ->with('user:id,username,role')
                ->orderBy('created_at', 'desc')
                ->get();

            $transformedComments = $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'role' => $comment->user->role,
                    ],
                    'created_at' => $comment->created_at->toISOString(),
                ];
            })->values()->all();

            return $this->successResponse(
                $transformedComments,
                'Comments retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving comments: ' . $e->getMessage(),
                'COMMENT_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Delete comment (Only comment owner, admin, or superadmin)
     */
    public function deleteComment(int $issueId, int $commentId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $comment = IssueComment::where('id', $commentId)
                ->where('issue_id', $issueId)
                ->first();

            if (!$comment) {
                return $this->notFoundResponse('Comment not found');
            }

            // Check permission: only owner, admin, or superadmin can delete
            if ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'superadmin'])) {
                return $this->forbiddenResponse('You do not have permission to delete this comment');
            }

            $comment->delete();

            return $this->successResponse(
                ['deleted' => true],
                'Comment deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting comment: ' . $e->getMessage(),
                'COMMENT_DELETE_ERROR',
                500
            );
        }
    }

    /**
     * Send notification when new issue is created
     */
    private function sendNewIssueNotification(Issue $issue): void
    {
        // Get ALL users (not just admin/superadmin)
        $recipients = LoginUser::all();

        foreach ($recipients as $user) {
            // Don't send to the creator
            if ($user->id !== $issue->created_by) {
                Notification::createNewIssue($user->id, $issue);
            }
        }
    }

    /**
     * Send notification when new comment is added
     */
    private function sendNewCommentNotification(IssueComment $comment, Issue $issue, $commenter): void
    {
        // Get ALL users (issue followers include everyone)
        $followers = LoginUser::all();

        // Send notification to all users except the commenter
        foreach ($followers as $user) {
            if ($user->id !== $commenter->id) {
                Notification::createNewComment($user->id, $comment);
            }
        }
    }

    /**
     * Get issue tracking progress for dashboard
     * GET /api/v1/issue-tracking/progress?quarter=3&year=2025
     * 
     * Response format:
     * {
     *   "solved": 10,
     *   "in_progress": 5,
     *   "pending": 3
     * }
     */
    public function getProgress(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Calculate quarter date range
            $quarterRanges = [
                1 => ['01-01', '03-31'], // Q1: Jan-Mar
                2 => ['04-01', '06-30'], // Q2: Apr-Jun
                3 => ['07-01', '09-30'], // Q3: Jul-Sep
                4 => ['10-01', '12-31'], // Q4: Oct-Dec
            ];

            $startDate = $year . '-' . $quarterRanges[$quarter][0];
            $endDate = $year . '-' . $quarterRanges[$quarter][1];

            // Get issues created in this quarter (based on due_date or created_at)
            // Using due_date if available, otherwise created_at
            $issues = Issue::where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('due_date', [$startDate, $endDate])
                      ->orWhereBetween('created_at', [$startDate, $endDate]);
            })->get();

            // Count by status
            $solved = $issues->where('status', IssueStatus::SOLVED)->count();
            $inProgress = $issues->where('status', IssueStatus::ON_GOING)->count();
            $pending = $issues->where('status', IssueStatus::PENDING)->count();

            return $this->successResponse([
                'solved' => $solved,
                'in_progress' => $inProgress,
                'pending' => $pending,
            ], 'Issue tracking progress retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error getting issue tracking progress: ' . $e->getMessage(),
                'ISSUE_PROGRESS_ERROR',
                500
            );
        }
    }

    /**
     * Get overdue issues
     * Endpoint: GET /issue-tracking/overdue
     * Query params: date (required) - comparison date
     * 
     * Issue is overdue if:
     * - due_date < comparison date
     * - status NOT IN (SOLVED) - which means status IN (PENDING, ON_GOING)
     */
    public function getOverdue(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $comparisonDate = $request->input('date');
            
            // Get issues where due_date < comparison date and status NOT SOLVED
            $issues = Issue::with(['creator:id,username,role', 'comments'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', $comparisonDate)
                ->whereIn('status', [IssueStatus::PENDING, IssueStatus::ON_GOING])
                ->orderBy('due_date', 'asc') // Oldest overdue first
                ->get();

            $transformedIssues = $issues->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'title' => $issue->issue_name, // Map issue_name to title
                    'description' => $issue->description,
                    'status' => $issue->status->value,
                    'createdAt' => $issue->created_at->toISOString(),
                    'priority' => null, // Field not exists in current schema
                    'assignedTo' => null, // Field not exists in current schema
                    'reportedBy' => $issue->creator ? $issue->creator->username : null,
                    'dueDate' => $issue->due_date?->toISOString(),
                    'commentCount' => $issue->comments->count(),
                ];
            })->values()->all();

            return $this->successResponse(
                $transformedIssues,
                'Overdue issues retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving overdue issues: ' . $e->getMessage(),
                'OVERDUE_ISSUES_ERROR',
                500
            );
        }
    }

    /**
     * Archive an issue
     * POST /api/v1/issues/{id}/archive
     */
    public function archive(int $id)
    {
        try {
            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            $issue->update(['is_archived' => true]);

            return $this->successResponse([
                'id' => $issue->id,
                'issue_name' => $issue->issue_name,
                'is_archived' => $issue->is_archived,
            ], 'Issue archived successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error archiving issue: ' . $e->getMessage(),
                'ISSUE_ARCHIVE_ERROR',
                500
            );
        }
    }

    /**
     * Unarchive an issue
     * POST /api/v1/issues/{id}/unarchive
     */
    public function unarchive(int $id)
    {
        try {
            $issue = Issue::find($id);

            if (!$issue) {
                return $this->notFoundResponse('Issue not found');
            }

            $issue->update(['is_archived' => false]);

            return $this->successResponse([
                'id' => $issue->id,
                'issue_name' => $issue->issue_name,
                'is_archived' => $issue->is_archived,
            ], 'Issue unarchived successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error unarchiving issue: ' . $e->getMessage(),
                'ISSUE_UNARCHIVE_ERROR',
                500
            );
        }
    }
}

