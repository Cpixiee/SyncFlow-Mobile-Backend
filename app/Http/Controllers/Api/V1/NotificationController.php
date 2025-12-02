<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get notifications list for authenticated user
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate query parameters
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
                'type' => 'nullable|in:TOOL_CALIBRATION_DUE,PRODUCT_OUT_OF_SPEC,NEW_ISSUE,ISSUE_OVERDUE,NEW_COMMENT,MONTHLY_TARGET_WARNING',
                'is_read' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $type = $request->get('type');
            $isRead = $request->get('is_read');

            // Build query
            $query = Notification::forUser($user->id)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($type) {
                $query->ofType($type);
            }

            if ($isRead !== null) {
                if ($isRead) {
                    $query->read();
                } else {
                    $query->unread();
                }
            }

            // Paginate
            $notifications = $query->paginate($limit, ['*'], 'page', $page);

            // Transform data
            $transformedNotifications = collect($notifications->items())
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'reference_type' => $notification->reference_type,
                        'reference_id' => $notification->reference_id,
                        'metadata' => $notification->metadata,
                        'is_read' => $notification->is_read,
                        'read_at' => $notification->read_at?->toISOString(),
                        'created_at' => $notification->created_at->toISOString(),
                    ];
                })->values()->all();

            return $this->paginationResponse(
                $transformedNotifications,
                [
                    'current_page' => $notifications->currentPage(),
                    'total_page' => $notifications->lastPage(),
                    'limit' => $notifications->perPage(),
                    'total_docs' => $notifications->total(),
                ],
                'Notifications retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving notifications: ' . $e->getMessage(),
                'NOTIFICATION_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount()
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $unreadCount = Notification::forUser($user->id)
                ->unread()
                ->count();

            return $this->successResponse([
                'unread_count' => $unreadCount
            ], 'Unread count retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error getting unread count: ' . $e->getMessage(),
                'UNREAD_COUNT_ERROR',
                500
            );
        }
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $id)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Find notification
            $notification = Notification::forUser($user->id)
                ->find($id);

            if (!$notification) {
                return $this->notFoundResponse('Notification tidak ditemukan');
            }

            // Mark as read
            $notification->markAsRead();

            return $this->successResponse([
                'id' => $notification->id,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at->toISOString()
            ], 'Notification marked as read');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error marking notification as read: ' . $e->getMessage(),
                'MARK_READ_ERROR',
                500
            );
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Mark all unread notifications as read
            $updatedCount = Notification::forUser($user->id)
                ->unread()
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return $this->successResponse([
                'marked_count' => $updatedCount
            ], 'All notifications marked as read');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error marking all as read: ' . $e->getMessage(),
                'MARK_ALL_READ_ERROR',
                500
            );
        }
    }

    /**
     * Delete notification
     */
    public function destroy(int $id)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Find notification
            $notification = Notification::forUser($user->id)
                ->find($id);

            if (!$notification) {
                return $this->notFoundResponse('Notification tidak ditemukan');
            }

            // Delete
            $notification->delete();

            return $this->successResponse([
                'deleted' => true
            ], 'Notification deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting notification: ' . $e->getMessage(),
                'DELETE_NOTIFICATION_ERROR',
                500
            );
        }
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead()
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Delete all read notifications
            $deletedCount = Notification::forUser($user->id)
                ->read()
                ->delete();

            return $this->successResponse([
                'deleted_count' => $deletedCount
            ], 'All read notifications deleted');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting notifications: ' . $e->getMessage(),
                'DELETE_ALL_ERROR',
                500
            );
        }
    }
}

