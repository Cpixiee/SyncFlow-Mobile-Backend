<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoginUser;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware will be handled in routes
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse(
                $validator->errors(),
                'Request invalid'
            );
        }

        $credentials = $request->only('username', 'password');

        try {
            // Attempt to verify the credentials and create a token for the user
            $user = LoginUser::where('username', $credentials['username'])->first();

            if (!$user) {
                return $this->authErrorResponse('user_not_found');
            }
            
            if (!Hash::check($credentials['password'], $user->password)) {
                return $this->authErrorResponse('invalid_credentials');
            }

            // Create token
            $token = JWTAuth::fromUser($user);

            // Prepare user data for response (excluding password)
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'email' => $user->email,
                'position' => $user->position,
                'department' => $user->department,
                'must_change_password' => $user->mustChangePassword(),
                'password_changed_at' => $user->password_changed_at ? $user->password_changed_at->format('Y-m-d H:i:s') : null,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ];

            return $this->successResponse(
                $userData,
                'Login successful'
            );

        } catch (JWTException $e) {
            return $this->errorResponse(
                'Could not create token',
                'JWT_ERROR',
                500
            );
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not found');
            }

            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'email' => $user->email,
                'position' => $user->position,
                'department' => $user->department,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse($userData);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve user data',
                'USER_DATA_ERROR',
                500
            );
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return $this->successResponse(
                null,
                'Successfully logged out'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not logout user',
                'LOGOUT_ERROR',
                500
            );
        }
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return $this->successResponse([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not refresh token',
                'TOKEN_REFRESH_ERROR',
                500
            );
        }
    }

    /**
     * Create a new user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request): JsonResponse
    {
        // Normalize role and position to lowercase for case-insensitive validation
        $normalizedData = $request->all();
        if ($request->filled('role')) {
            $normalizedData['role'] = strtolower($request->role);
        }
        if ($request->filled('position')) {
            $normalizedData['position'] = strtolower($request->position);
        }

        // Validation
        $validator = Validator::make($normalizedData, [
            'username' => 'required|string|unique:login_users,username',
            'password' => 'nullable|string|min:6', // Optional, akan default ke admin#1234
            'role' => 'required|in:operator,admin,superadmin',
            'photo_url' => 'nullable|string', // Accept URL or relative path like /storage/filename.jpg
            'employee_id' => 'required|string|unique:login_users,employee_id',
            'phone' => 'required|string',
            'email' => 'required|email|unique:login_users,email',
            'position' => 'required|in:manager,staff,supervisor',
            'department' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse(
                $validator->errors(),
                'Request invalid'
            );
        }

        try {
            // Use default password if not provided or use provided password
            $password = $request->filled('password') ? $request->password : 'admin#1234';
            
            // Create new user (use normalized values)
            $user = LoginUser::create([
                'username' => $normalizedData['username'],
                'password' => Hash::make($password),
                'role' => $normalizedData['role'],
                'photo_url' => $normalizedData['photo_url'] ?? null,
                'employee_id' => $normalizedData['employee_id'],
                'phone' => $normalizedData['phone'],
                'email' => $normalizedData['email'],
                'position' => $normalizedData['position'],
                'department' => $normalizedData['department'],
                'password_changed' => false, // Default belum pernah ganti password
                'password_changed_at' => null,
            ]);

            // Prepare user data for response (excluding password)
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'photo_url' => $user->photo_url,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'email' => $user->email,
                'position' => $user->position,
                'department' => $user->department,
                'must_change_password' => $user->mustChangePassword(),
                'default_password' => $password === 'admin#1234' ? 'admin#1234' : null,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse(
                $userData,
                'User created successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create user',
                'USER_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Change user password (enhanced with admin reset and force change logic).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $authUser = JWTAuth::parseToken()->authenticate();
            
            if (!$authUser) {
                return $this->unauthorizedResponse('User not authenticated. Please login again.');
            }

            // Check if this is admin changing another user's password
            $isAdminReset = $request->filled('target_user_id') && $authUser->isSuperAdmin();
            $targetUser = $isAdminReset ? LoginUser::find($request->target_user_id) : $authUser;

            if ($isAdminReset && !$targetUser) {
                return $this->notFoundResponse('Target user not found');
            }

            // Dynamic validation based on context
            $validationRules = [
                'new_password' => 'required|string|min:6|confirmed',
                'new_password_confirmation' => 'required|string|min:6',
            ];

            // Only require current password if user is changing their own password
            if (!$isAdminReset) {
                $validationRules['current_password'] = 'required|string';
            }

            // Add target_user_id validation if admin reset
            if ($isAdminReset) {
                $validationRules['target_user_id'] = 'required|integer|exists:login_users,id';
                $validationRules['force_change'] = 'nullable|boolean';
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Verify current password only if not admin reset
            if (!$isAdminReset) {
                if (!Hash::check($request->current_password, $targetUser->password)) {
                    return $this->validationErrorResponse(
                        ['current_password' => ['Current password is incorrect']],
                        'Current password is incorrect',
                        'PASSWORD_INCORRECT_' . strtoupper(uniqid())
                    );
                }
            }

            // Check if new password is different from current
            if (Hash::check($request->new_password, $targetUser->password)) {
                return $this->validationErrorResponse(
                    ['new_password' => ['New password must be different from current password']],
                    'New password must be different from current password',
                    'PASSWORD_SAME_AS_CURRENT_' . strtoupper(uniqid())
                );
            }

            // Update password
            $targetUser->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Handle password change tracking
            if ($isAdminReset && $request->boolean('force_change', false)) {
                // Admin forcing password change - user must change on next login
                $targetUser->update([
                    'password_changed' => false,
                    'password_changed_at' => now(),
                ]);
            } else {
                // Normal password change or admin reset without force
                $targetUser->markPasswordChanged();
            }

            // ✅ FIX: Refresh model to get latest data
            $targetUser->refresh();

            // Prepare response data
            $userData = [
                'id' => $targetUser->id,
                'user_id' => $targetUser->id,
                'username' => $targetUser->username,
                'role' => $targetUser->role,
                'must_change_password' => $targetUser->mustChangePassword(),
                'password_changed_at' => $targetUser->password_changed_at ? $targetUser->password_changed_at->format('Y-m-d H:i:s') : null, // ✅ FIX: Add null check
                'is_force_change' => $isAdminReset && $request->boolean('force_change', false),
                'changed_by_admin' => $isAdminReset
            ];

            return $this->successResponse(
                $userData,
                'Password changed successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not change password',
                'PASSWORD_CHANGE_ERROR',
                500
            );
        }
    }

    /**
     * Get list of all users (Superadmin only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserList(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $authUser = JWTAuth::parseToken()->authenticate();
            
            if (!$authUser || !$authUser->isSuperAdmin()) {
                return $this->forbiddenResponse('Access denied. Superadmin role required.');
            }

            // Validation for query parameters
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'search' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $limit = $request->get('limit', 10);
            $search = $request->get('search');

            // Build query
            $query = LoginUser::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('employee_id', 'like', "%{$search}%");
                });
            }

            // Get paginated results
            $users = $query->orderBy('created_at', 'desc')->paginate($limit);

            // Format user data
            $formattedUsers = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'photo_url' => $user->photo_url,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'position' => $user->position,
                    'department' => $user->department,
                    'must_change_password' => $user->mustChangePassword(),
                    'password_changed_at' => $user->password_changed_at ? $user->password_changed_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return $this->paginationResponse(
                $formattedUsers,
                [
                    'current_page' => $users->currentPage(),
                    'total_page' => $users->lastPage(),
                    'limit' => $users->perPage(),
                    'total_docs' => $users->total(),
                ],
                'Users retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve users',
                'USER_LIST_ERROR',
                500
            );
        }
    }

    /**
     * Upload profile image.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadProfileImage(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $authUser = JWTAuth::parseToken()->authenticate();
            
            if (!$authUser) {
                return $this->unauthorizedResponse('User not found');
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // Max 5MB
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Delete old image if exists
            if ($authUser->photo_url) {
                $oldImagePath = str_replace('/storage/', '', parse_url($authUser->photo_url, PHP_URL_PATH));
                if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }

            // Store uploaded file
            $image = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('', $filename, 'public');

            // Generate URL
            $photoUrl = '/storage/' . $filename;

            // Update user photo_url
            $authUser->update(['photo_url' => $photoUrl]);

            // Prepare response data
            $userData = [
                'id' => $authUser->id,
                'username' => $authUser->username,
                'photo_url' => $photoUrl,
                'updated_at' => $authUser->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse(
                $userData,
                'Profile image uploaded successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not upload profile image: ' . $e->getMessage(),
                'IMAGE_UPLOAD_ERROR',
                500
            );
        }
    }

    /**
     * Update user information.
     * - Regular users can only update their own data
     * - Superadmin can update role of other users by providing user_id and role
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUser(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $authUser = JWTAuth::parseToken()->authenticate();
            
            if (!$authUser) {
                return $this->unauthorizedResponse('User not found');
            }

            // If user_id is provided, this is superadmin updating another user's role
            if ($request->filled('user_id')) {
                // Only superadmin can update other users
                if (!$authUser->isSuperAdmin()) {
                    return $this->forbiddenResponse('Only superadmin can update other users. Remove user_id from request.');
                }

                // Superadmin must provide role when updating other users
                if (!$request->filled('role')) {
                    return $this->validationErrorResponse(
                        ['role' => ['Role is required when updating another user']],
                        'Request invalid'
                    );
                }

                // Find target user
                $targetUser = LoginUser::find($request->user_id);
                if (!$targetUser) {
                    return $this->notFoundResponse('Target user not found');
                }

                // Validate only user_id and role (ignore other fields)
                $validator = Validator::make($request->all(), [
                    'user_id' => 'required|integer|exists:login_users,id',
                    'role' => 'required|in:operator,admin,superadmin',
                ]);

                if ($validator->fails()) {
                    return $this->validationErrorResponse(
                        $validator->errors(),
                        'Request invalid'
                    );
                }

                // Update only role
                $targetUser->update([
                    'role' => strtolower($request->role),
                ]);

                // Prepare response data
                $userData = [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username,
                    'role' => $targetUser->role,
                    'photo_url' => $targetUser->photo_url,
                    'employee_id' => $targetUser->employee_id,
                    'phone' => $targetUser->phone,
                    'email' => $targetUser->email,
                    'position' => $targetUser->position,
                    'department' => $targetUser->department,
                    'updated_at' => $targetUser->updated_at->format('Y-m-d H:i:s'),
                ];

                return $this->successResponse(
                    $userData,
                    'User role updated successfully'
                );
            }

            // Regular update (own data or superadmin updating other fields)
            $targetUser = $authUser; // Default: update own data

            // Validation rules - use target user ID for unique validation
            $validationRules = [
                'username' => "nullable|string|unique:login_users,username,{$targetUser->id},id",
                'photo_url' => 'nullable|string',
                'phone' => 'nullable|string',
                'email' => "nullable|email|unique:login_users,email,{$targetUser->id},id",
                'employee_id' => "nullable|string|unique:login_users,employee_id,{$targetUser->id},id",
            ];

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // ✅ Support uploading profile image via this endpoint too (multipart/form-data)
            // Accept "image" field same as uploadProfileImage()
            if ($request->hasFile('image')) {
                $imageValidator = Validator::make($request->all(), [
                    'image' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                ]);

                if ($imageValidator->fails()) {
                    return $this->validationErrorResponse(
                        $imageValidator->errors(),
                        'Request invalid'
                    );
                }

                // Delete old image if exists
                if ($authUser->photo_url) {
                    $oldImagePath = str_replace('/storage/', '', parse_url($authUser->photo_url, PHP_URL_PATH));
                    if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                // Store uploaded file
                $image = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('', $filename, 'public');

                // Set photo_url for update
                $request->merge(['photo_url' => '/storage/' . $filename]);
            }

            // Prepare update data
            $updateData = array_filter([
                'username' => $request->username,
                'photo_url' => $request->photo_url,
                'phone' => $request->phone,
                'email' => $request->email,
                'employee_id' => $request->employee_id,
            ], function($value) {
                return $value !== null;
            });

            if (empty($updateData)) {
                return $this->validationErrorResponse(
                    ['fields' => ['At least one field must be provided for update']],
                    'No fields to update'
                );
            }

            // Update user
            $targetUser->update($updateData);

            // Prepare response data
            $userData = [
                'id' => $targetUser->id,
                'username' => $targetUser->username,
                'role' => $targetUser->role,
                'photo_url' => $targetUser->photo_url,
                'employee_id' => $targetUser->employee_id,
                'phone' => $targetUser->phone,
                'email' => $targetUser->email,
                'position' => $targetUser->position,
                'department' => $targetUser->department,
                'updated_at' => $targetUser->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse(
                $userData,
                'User information updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not update user information',
                'USER_UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Update user role (Admin/Superadmin only).
     *
     * Hanya mengubah field role user lain.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        try {
            // Auth sudah dicek oleh middleware + role:admin,superadmin
            $authUser = JWTAuth::parseToken()->authenticate();
            if (!$authUser) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Normalize role to lowercase for case-insensitive validation
            $normalizedData = $request->all();
            if ($request->filled('role')) {
                $normalizedData['role'] = strtolower($request->role);
            }

            // Validasi input role
            $validator = Validator::make($normalizedData, [
                'role' => 'required|in:operator,admin,superadmin',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Cari target user
            $targetUser = LoginUser::find($id);
            if (!$targetUser) {
                return $this->notFoundResponse('Target user not found');
            }

            // Optional: cegah user menurunkan / mengubah rolenya sendiri via endpoint ini
            if ($authUser->id === $targetUser->id) {
                return $this->forbiddenResponse('You cannot change your own role using this endpoint');
            }

            // Update hanya field role (use normalized value)
            $targetUser->update([
                'role' => $normalizedData['role'],
            ]);

            $userData = [
                'id' => $targetUser->id,
                'username' => $targetUser->username,
                'role' => $targetUser->role,
                'photo_url' => $targetUser->photo_url,
                'employee_id' => $targetUser->employee_id,
                'phone' => $targetUser->phone,
                'email' => $targetUser->email,
                'position' => $targetUser->position,
                'department' => $targetUser->department,
                'updated_at' => $targetUser->updated_at->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse(
                $userData,
                'User role updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not update user role',
                'USER_ROLE_UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Delete users (Superadmin only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteUsers(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $authUser = JWTAuth::parseToken()->authenticate();
            
            if (!$authUser || !$authUser->isSuperAdmin()) {
                return $this->forbiddenResponse('Access denied. Superadmin role required.');
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:login_users,id',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $userIds = $request->user_ids;
            $reason = $request->reason;

            // Prevent admin from deleting themselves
            if (in_array($authUser->id, $userIds)) {
                return $this->validationErrorResponse(
                    ['user_ids' => ['You cannot delete your own account']],
                    'Cannot delete own account'
                );
            }

            // Get users to be deleted for response
            $usersToDelete = LoginUser::whereIn('id', $userIds)->get();

            if ($usersToDelete->count() !== count($userIds)) {
                return $this->validationErrorResponse(
                    ['user_ids' => ['Some user IDs do not exist']],
                    'Invalid user IDs provided'
                );
            }

            // Prepare deleted users data for response
            $deletedUsers = $usersToDelete->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'employee_id' => $user->employee_id,
                ];
            });

            // Delete users
            LoginUser::whereIn('id', $userIds)->delete();

            $responseData = [
                'deleted_count' => count($userIds),
                'deleted_users' => $deletedUsers,
                'reason' => $reason,
            ];

            return $this->successResponse(
                $responseData,
                'Users deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not delete users',
                'USER_DELETE_ERROR',
                500
            );
        }
    }
}
