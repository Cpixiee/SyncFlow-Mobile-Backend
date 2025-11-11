# Issue Tracking API Documentation

## Overview
Fitur Issue Tracking memungkinkan admin dan superadmin untuk membuat, mengelola, dan melacak issue produksi. Semua user yang terautentikasi dapat melihat issue dan menambahkan comment.

## Table of Contents
- [Endpoints](#endpoints)
- [Data Models](#data-models)
- [Permissions](#permissions)
- [Examples](#examples)

## Endpoints

### 1. Get All Issues (Paginated)
```
GET /api/v1/issues
```

**Authentication:** Required (All roles)

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `status` (optional): Filter by status (PENDING, ON_GOING, SOLVED)
- `search` (optional): Search by issue_name or description

**Response:**
```json
{
  "http_code": 200,
  "message": "Issues retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": 1,
        "issue_name": "Calibration error on Machine A",
        "description": "Machine A shows inconsistent readings",
        "status": "PENDING",
        "status_description": "Pending",
        "status_color": "orange",
        "due_date": "2025-11-15",
        "created_by": {
          "id": 1,
          "username": "admin",
          "role": "admin"
        },
        "comments_count": 3,
        "created_at": "2025-11-08T10:00:00.000000Z",
        "updated_at": "2025-11-08T10:00:00.000000Z"
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 5,
      "limit": 10,
      "total_docs": 50
    }
  }
}
```

### 2. Get Single Issue
```
GET /api/v1/issues/{id}
```

**Authentication:** Required (All roles)

**Response:**
```json
{
  "http_code": 200,
  "message": "Issue retrieved successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "issue_name": "Calibration error on Machine A",
    "description": "Machine A shows inconsistent readings during morning shift",
    "status": "PENDING",
    "status_description": "Pending",
    "status_color": "orange",
    "due_date": "2025-11-15",
    "created_by": {
      "id": 1,
      "username": "admin",
      "role": "admin",
      "employee_id": "EMP001"
    },
    "comments": [
      {
        "id": 1,
        "comment": "Working on it. Tech team is investigating.",
        "user": {
          "id": 2,
          "username": "operator",
          "role": "user"
        },
        "created_at": "2025-11-08T11:00:00.000000Z"
      }
    ],
    "created_at": "2025-11-08T10:00:00.000000Z",
    "updated_at": "2025-11-08T10:00:00.000000Z"
  }
}
```

### 3. Create Issue
```
POST /api/v1/issues
```

**Authentication:** Required (Admin, Superadmin only)

**Request Body:**
```json
{
  "issue_name": "Calibration error on Machine A",
  "description": "Machine A shows inconsistent readings during morning shift",
  "status": "PENDING",
  "due_date": "2025-11-15"
}
```

**Fields:**
- `issue_name` (required): String, max 255 characters
- `description` (required): String
- `status` (required): Enum (PENDING, ON_GOING, SOLVED)
- `due_date` (optional): Date (must be today or future date)

**Response:** (201 Created)
```json
{
  "http_code": 201,
  "message": "Issue created successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "issue_name": "Calibration error on Machine A",
    "description": "Machine A shows inconsistent readings during morning shift",
    "status": "PENDING",
    "status_description": "Pending",
    "status_color": "orange",
    "due_date": "2025-11-15",
    "created_by": {
      "id": 1,
      "username": "admin",
      "role": "admin"
    },
    "created_at": "2025-11-08T10:00:00.000000Z",
    "updated_at": "2025-11-08T10:00:00.000000Z"
  }
}
```

### 4. Update Issue
```
PUT /api/v1/issues/{id}
```

**Authentication:** Required (Admin, Superadmin only)

**Request Body:**
```json
{
  "issue_name": "Updated Issue Name",
  "description": "Updated description",
  "status": "ON_GOING",
  "due_date": "2025-11-20"
}
```

**Fields:** (All optional, only send fields to update)
- `issue_name`: String, max 255 characters
- `description`: String
- `status`: Enum (PENDING, ON_GOING, SOLVED)
- `due_date`: Date (must be today or future date)

**Response:** (200 OK)

### 5. Delete Issue
```
DELETE /api/v1/issues/{id}
```

**Authentication:** Required (Admin, Superadmin only)

**Response:**
```json
{
  "http_code": 200,
  "message": "Issue deleted successfully",
  "error_id": null,
  "data": {
    "deleted": true
  }
}
```

**Note:** Deleting an issue will cascade delete all associated comments.

### 6. Add Comment to Issue
```
POST /api/v1/issues/{id}/comments
```

**Authentication:** Required (All roles can comment)

**Request Body:**
```json
{
  "comment": "Working on it. Tech team is investigating the root cause."
}
```

**Fields:**
- `comment` (required): String

**Response:** (201 Created)
```json
{
  "http_code": 201,
  "message": "Comment added successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "issue_id": 1,
    "comment": "Working on it. Tech team is investigating the root cause.",
    "user": {
      "id": 2,
      "username": "operator",
      "role": "user"
    },
    "created_at": "2025-11-08T11:00:00.000000Z"
  }
}
```

### 7. Get Comments for Issue
```
GET /api/v1/issues/{id}/comments
```

**Authentication:** Required (All roles)

**Response:**
```json
{
  "http_code": 200,
  "message": "Comments retrieved successfully",
  "error_id": null,
  "data": [
    {
      "id": 1,
      "comment": "Working on it. Tech team is investigating.",
      "user": {
        "id": 2,
        "username": "operator",
        "role": "user"
      },
      "created_at": "2025-11-08T11:00:00.000000Z"
    }
  ]
}
```

### 8. Delete Comment
```
DELETE /api/v1/issues/{issueId}/comments/{commentId}
```

**Authentication:** Required
- Comment owner can delete their own comment
- Admin and Superadmin can delete any comment

**Response:**
```json
{
  "http_code": 200,
  "message": "Comment deleted successfully",
  "error_id": null,
  "data": {
    "deleted": true
  }
}
```

## Data Models

### Issue Status Enum
```php
enum IssueStatus: string
{
    case PENDING = 'PENDING';      // Orange color
    case ON_GOING = 'ON_GOING';    // Blue color
    case SOLVED = 'SOLVED';        // Green color
}
```

### Issue Model
```php
{
  id: integer
  issue_name: string
  description: text
  status: enum (PENDING, ON_GOING, SOLVED)
  created_by: integer (foreign key to login_users)
  due_date: date (nullable)
  created_at: timestamp
  updated_at: timestamp
}
```

### IssueComment Model
```php
{
  id: integer
  issue_id: integer (foreign key to issues)
  user_id: integer (foreign key to login_users)
  comment: text
  created_at: timestamp
  updated_at: timestamp
}
```

## Permissions

### Issue CRUD Operations
| Action | User | Admin | Superadmin |
|--------|------|-------|------------|
| View Issues | ✅ | ✅ | ✅ |
| Create Issue | ❌ | ✅ | ✅ |
| Update Issue | ❌ | ✅ | ✅ |
| Delete Issue | ❌ | ✅ | ✅ |
| Add Comment | ✅ | ✅ | ✅ |
| Delete Own Comment | ✅ | ✅ | ✅ |
| Delete Any Comment | ❌ | ✅ | ✅ |

## Examples

### Example 1: Filter Issues by Status
```bash
GET /api/v1/issues?status=PENDING
```

### Example 2: Search Issues
```bash
GET /api/v1/issues?search=Calibration
```

### Example 3: Combine Filters
```bash
GET /api/v1/issues?status=PENDING&search=Machine&page=1&limit=20
```

### Example 4: Create Issue with Due Date
```bash
POST /api/v1/issues
Content-Type: application/json
Authorization: Bearer {token}

{
  "issue_name": "Equipment malfunction on Line 3",
  "description": "Line 3 equipment shows error code E05",
  "status": "PENDING",
  "due_date": "2025-11-15"
}
```

### Example 5: Update Issue Status
```bash
PUT /api/v1/issues/1
Content-Type: application/json
Authorization: Bearer {token}

{
  "status": "ON_GOING"
}
```

## Error Responses

### 400 - Validation Error
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_ABC123",
  "data": {
    "issue_name": ["The issue name field is required."],
    "status": ["The selected status is invalid."]
  }
}
```

### 401 - Unauthorized
```json
{
  "http_code": 401,
  "message": "unauthorized",
  "error_id": "ERR_ABC123",
  "data": null
}
```

### 403 - Forbidden
```json
{
  "http_code": 403,
  "message": "Access denied. Required role: admin, superadmin. User role: user",
  "error_id": "ERR_ABC123",
  "data": null
}
```

### 404 - Not Found
```json
{
  "http_code": 404,
  "message": "Issue not found",
  "error_id": "ERR_ABC123",
  "data": null
}
```

### 500 - Server Error
```json
{
  "http_code": 500,
  "message": "Error creating issue: [error details]",
  "error_id": "ISSUE_CREATE_ERROR",
  "data": null
}
```

## Testing

### Run Migrations
```bash
php artisan migrate
```

### Run Tests
```bash
# Run all issue tests
php artisan test --filter IssueTest

# Run unit tests
php artisan test --filter IssueModelTest

# Run all tests
php artisan test
```

### Seed Sample Data
```bash
php artisan db:seed --class=IssueSeeder
```

## Database Schema

### issues table
```sql
CREATE TABLE issues (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    issue_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('PENDING', 'ON_GOING', 'SOLVED') DEFAULT 'PENDING',
    created_by BIGINT UNSIGNED NOT NULL,
    due_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES login_users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_due_date (due_date),
    INDEX idx_status_created_by (status, created_by)
);
```

### issue_comments table
```sql
CREATE TABLE issue_comments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES login_users(id) ON DELETE CASCADE,
    INDEX idx_issue_id (issue_id),
    INDEX idx_user_id (user_id),
    INDEX idx_issue_created (issue_id, created_at)
);
```

## Notes

1. **Due Date Validation**: Due date must be today or a future date. Past dates are not allowed.

2. **Cascade Delete**: When an issue is deleted, all its comments are automatically deleted.

3. **Status Colors**: 
   - PENDING: Orange (#FFA500)
   - ON_GOING: Blue (#0000FF)
   - SOLVED: Green (#008000)

4. **Comment Permissions**: 
   - All authenticated users can add comments
   - Users can only delete their own comments
   - Admins and superadmins can delete any comment

5. **Issue Creator**: The `created_by` field is automatically set to the authenticated user who creates the issue.

6. **Timestamps**: All timestamps are in ISO 8601 format (UTC).

