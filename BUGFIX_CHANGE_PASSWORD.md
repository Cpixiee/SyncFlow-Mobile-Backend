# Bug Fix: Change Password - "User not found" Error

## Issue
Saat user ganti password, muncul error "User not found" atau error terkait `password_changed_at`.

## Root Cause
1. **Null Pointer Exception**: Di line 360, `password_changed_at` di-format tanpa null check:
   ```php
   'password_changed_at' => $targetUser->password_changed_at->format('Y-m-d H:i:s'), // ❌ Error jika null
   ```

2. **Model tidak di-refresh**: Setelah update, model tidak di-refresh sehingga data mungkin tidak terbaru.

## Fix

### File Modified
`app/Http/Controllers/Api/V1/AuthController.php` - `changePassword()` method

### Changes

**Before:**
```php
$targetUser->markPasswordChanged();

// Prepare response data
$userData = [
    // ...
    'password_changed_at' => $targetUser->password_changed_at->format('Y-m-d H:i:s'), // ❌ No null check
    // ...
];
```

**After:**
```php
$targetUser->markPasswordChanged();

// ✅ FIX: Refresh model to get latest data
$targetUser->refresh();

// Prepare response data
$userData = [
    // ...
    'password_changed_at' => $targetUser->password_changed_at ? $targetUser->password_changed_at->format('Y-m-d H:i:s') : null, // ✅ Null check added
    // ...
];
```

**Error Message Improvement:**
```php
// Before
if (!$authUser) {
    return $this->unauthorizedResponse('User not found');
}

// After
if (!$authUser) {
    return $this->unauthorizedResponse('User not authenticated. Please login again.'); // ✅ More descriptive
}
```

## Testing

### Test Case: Normal Password Change
1. User login dengan valid token
2. Call `POST /api/v1/change-password` dengan:
   - `current_password`: password lama yang benar
   - `new_password`: password baru
   - `new_password_confirmation`: password baru (sama)
3. Expected: Password berhasil diubah, response include `password_changed_at`

### Test Case: Invalid Token
1. User dengan expired/invalid token
2. Call `POST /api/v1/change-password`
3. Expected: Error "User not authenticated. Please login again."

---

**Status:** ✅ Fixed  
**Date:** 2026-01-13  
**Impact:** Medium - Fixes potential crash saat change password
