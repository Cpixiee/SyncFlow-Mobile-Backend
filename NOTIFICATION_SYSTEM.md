# 🔔 SyncFlow - Notification System

Dokumentasi sistem notifikasi SyncFlow untuk monitoring tools, quality issues, dan product measurements.

---

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Setup & Deployment](#setup--deployment)
3. [Jenis Notifikasi](#jenis-notifikasi)
4. [API Endpoints](#api-endpoints)
5. [Database Schema](#database-schema)
6. [Scheduled Jobs](#scheduled-jobs)
7. [Testing](#testing)

---

## 🎯 Overview

Sistem notifikasi SyncFlow memberikan **real-time alerts** kepada user terkait event-event penting dalam proses quality control dan measurement.

### Key Features

- ✅ **6 Jenis Notifikasi** dengan kategori berbeda
- ✅ **Real-time Triggers** saat event terjadi (insert issue, comment, measurement)
- ✅ **Scheduled Jobs** untuk monitoring (daily & weekly checks)
- ✅ **Multi-user Notifications** - Notifikasi dikirim ke **SEMUA USER** (operator, admin, superadmin)
- ✅ **Mark as Read/Unread** dengan timestamp
- ✅ **Delete Notifications** (single & bulk delete)
- ✅ **Filter & Pagination** untuk list notifications

### Recipients Policy

> **PENTING:** Notifikasi dikirim ke **SEMUA USER** (operator, admin, superadmin), bukan hanya role tertentu!

---

## 🚀 Setup & Deployment

### 1. Run Migration

```bash
php artisan migrate
```

Migration file: `database/migrations/2025_12_02_000002_create_notifications_table.php`

### 2. Test Scheduled Commands

```bash
# Test tool calibration check
php artisan notifications:check-tool-calibration

# Test overdue issues check
php artisan notifications:check-overdue-issues

# Test monthly target check
php artisan notifications:check-monthly-target
```

### 3. Setup Cron (Production)

Add to server crontab:

```bash
* * * * * cd /path-to-syncflow && php artisan schedule:run >> /dev/null 2>&1
```

Schedule konfigurasi di `app/Console/Kernel.php`:
- Tool Calibration Check: **Daily at 08:00 AM**
- Overdue Issues Check: **Daily at 09:00 AM**
- Monthly Target Check: **Weekly on Monday at 08:00 AM**

### 4. Run Tests

```bash
php artisan test --filter NotificationTest
```

**Test Result:** ✅ 17 passed (155 assertions)

---

## 📌 Jenis Notifikasi

### 1. **Kalibrasi Alat Akan Jatuh Tempo** 🔧

| Field | Value |
|-------|-------|
| **Type** | `TOOL_CALIBRATION_DUE` |
| **Trigger** | Daily job (08:00 AM) |
| **Condition** | Tool `status = 'ACTIVE'` dan `next_calibration_at` dalam 0-7 hari dari sekarang |
| **Recipients** | **SEMUA USER** (operator, admin, superadmin) |

**Contoh Response:**
```json
{
  "id": 1,
  "type": "TOOL_CALIBRATION_DUE",
  "title": "Kalibrasi Alat Akan Jatuh Tempo",
  "message": "Alat ukur 'Digital Caliper' (Model: DC-100, IMEI: ABC-123-XYZ) akan jatuh tempo kalibrasi dalam 3 hari pada 2025-12-15.",
  "reference_type": "tool",
  "reference_id": "15",
  "metadata": {
    "tool_id": 15,
    "tool_name": "Digital Caliper",
    "next_calibration_at": "2025-12-15",
    "days_left": 3
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-02T10:30:00.000000Z"
}
```

---

### 2. **Produk Out of Spec Terdeteksi** ⚠️

| Field | Value |
|-------|-------|
| **Type** | `PRODUCT_OUT_OF_SPEC` |
| **Trigger** | Saat submit measurement (insert/update measurement result dengan nilai NG) |
| **Condition** | Ada measurement item dengan nilai di luar toleransi (value < min OR value > max) |
| **Recipients** | **SEMUA USER** (operator, admin, superadmin) |

**Contoh Response:**
```json
{
  "id": 2,
  "type": "PRODUCT_OUT_OF_SPEC",
  "title": "Produk Out of Spec Terdeteksi",
  "message": "Produk 'CORUTUBE' (Batch: BATCH-20251201-ABC123) terdeteksi out of spec pada item 'Thickness'. Nilai terukur: 5.8mm, Spesifikasi: 5.0-5.5mm.",
  "reference_type": "product_measurement",
  "reference_id": "MSR-ABC123",
  "metadata": {
    "product_measurement_id": "MSR-ABC123",
    "product_name": "CORUTUBE",
    "batch_number": "BATCH-20251201-ABC123",
    "item_name": "Thickness",
    "measured_value": 5.8,
    "spec_min": 5.0,
    "spec_max": 5.5
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-02T14:20:00.000000Z"
}
```

---

### 3. **Issue Baru Dibuat** 🐛

| Field | Value |
|-------|-------|
| **Type** | `NEW_ISSUE` |
| **Trigger** | Saat create issue (`POST /api/v1/issues`) |
| **Condition** | Issue baru dibuat |
| **Recipients** | **SEMUA USER kecuali creator** |

**Contoh Response:**
```json
{
  "id": 3,
  "type": "NEW_ISSUE",
  "title": "Issue Baru Dibuat: Material Defect Found",
  "message": "Issue kualitas baru 'Material Defect Found' telah dibuat oleh admin_user. Ringkasan: Found cracks on 5 samples from batch XYZ...",
  "reference_type": "issue",
  "reference_id": "12",
  "metadata": {
    "issue_id": 12,
    "issue_name": "Material Defect Found",
    "status": "PENDING",
    "created_by": "admin_user",
    "due_date": "2025-12-15"
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-02T09:15:00.000000Z"
}
```

---

### 4. **Issue Overdue** ⏰

| Field | Value |
|-------|-------|
| **Type** | `ISSUE_OVERDUE` |
| **Trigger** | Daily job (09:00 AM) |
| **Condition** | Issue dengan status `PENDING` atau `ON_GOING` dan `due_date < today` |
| **Recipients** | **SEMUA USER** (operator, admin, superadmin) |

**Contoh Response:**
```json
{
  "id": 4,
  "type": "ISSUE_OVERDUE",
  "title": "Issue Overdue: Material Defect Found",
  "message": "Issue 'Material Defect Found' telah melewati batas waktu (2025-12-01) dan masih berstatus PENDING. Mohon segera ditindaklanjuti.",
  "reference_type": "issue",
  "reference_id": "12",
  "metadata": {
    "issue_id": 12,
    "issue_name": "Material Defect Found",
    "status": "PENDING",
    "due_date": "2025-12-01",
    "days_overdue": 3
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-04T09:00:00.000000Z"
}
```

---

### 5. **Komentar Baru pada Issue** 💬

| Field | Value |
|-------|-------|
| **Type** | `NEW_COMMENT` |
| **Trigger** | Saat add comment (`POST /api/v1/issues/{id}/comments`) |
| **Condition** | Comment baru ditambahkan pada issue |
| **Recipients** | **SEMUA USER kecuali commenter** |

**Contoh Response:**
```json
{
  "id": 5,
  "type": "NEW_COMMENT",
  "title": "Komentar Baru pada Issue: Material Defect Found",
  "message": "qa_user menambahkan komentar baru pada issue 'Material Defect Found': \"Root cause analysis completed. Found that supplier changed material composition...\"",
  "reference_type": "issue",
  "reference_id": "12",
  "metadata": {
    "issue_id": 12,
    "issue_name": "Material Defect Found",
    "comment_id": 45,
    "commenter": "qa_user"
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-02T15:30:00.000000Z"
}
```

---

### 6. **Target Bulanan Belum Tercapai** 📊

| Field | Value |
|-------|-------|
| **Type** | `MONTHLY_TARGET_WARNING` |
| **Trigger** | Weekly job (Monday 08:00 AM) |
| **Condition** | Actual inspection < expected inspection berdasarkan minggu berjalan |
| **Recipients** | **SEMUA USER** (operator, admin, superadmin) |

**Contoh Response:**
```json
{
  "id": 6,
  "type": "MONTHLY_TARGET_WARNING",
  "title": "Target Bulanan Belum Tercapai",
  "message": "Pencapaian target bulanan hingga minggu ini (Week 2) masih di bawah ekspektasi. Target: 100 unit, Aktual: 35 unit, Ekspektasi: 50 unit. Perlu percepatan penyelesaian inspeksi.",
  "reference_type": "monthly_target",
  "reference_id": null,
  "metadata": {
    "month": 12,
    "year": 2025,
    "monthly_target": 100,
    "actual_inspection": 35,
    "expected_inspection": 50,
    "current_week": 2,
    "total_weeks": 4
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-09T08:00:00.000000Z"
}
```

---

## 🔌 API Endpoints

Base URL: `/api/v1/notifications`

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/notifications` | Get list notifications dengan pagination & filter | Required |
| GET | `/notifications/unread-count` | Get jumlah unread notifications untuk badge | Required |
| POST | `/notifications/{id}/mark-as-read` | Mark single notification as read | Required |
| POST | `/notifications/mark-all-as-read` | Mark all notifications as read | Required |
| DELETE | `/notifications/{id}` | Delete single notification | Required |
| DELETE | `/notifications/read/all` | Delete all read notifications (bulk delete) | Required |

---

### 1. GET `/notifications` - Get Notifications List

Get daftar notifikasi dengan pagination dan filter.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `limit` | integer | No | 20 | Items per page (max: 100) |
| `type` | string | No | - | Filter by notification type |
| `is_read` | boolean | No | - | Filter by read status (0 = unread, 1 = read) |

**Valid Types:**
- `TOOL_CALIBRATION_DUE`
- `PRODUCT_OUT_OF_SPEC`
- `NEW_ISSUE`
- `ISSUE_OVERDUE`
- `NEW_COMMENT`
- `MONTHLY_TARGET_WARNING`

**Example Request:**
```http
GET /api/v1/notifications?page=1&limit=20&is_read=0
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Notifications retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": 1,
        "type": "TOOL_CALIBRATION_DUE",
        "title": "Kalibrasi Alat Akan Jatuh Tempo",
        "message": "Alat ukur 'Digital Caliper' (Model: DC-100, IMEI: ABC-123) akan jatuh tempo kalibrasi dalam 3 hari pada 2025-12-15.",
        "reference_type": "tool",
        "reference_id": "15",
        "metadata": {
          "tool_id": 15,
          "tool_name": "Digital Caliper",
          "next_calibration_at": "2025-12-15",
          "days_left": 3
        },
        "is_read": false,
        "read_at": null,
        "created_at": "2025-12-02T10:30:00.000000Z"
      },
      {
        "id": 2,
        "type": "NEW_ISSUE",
        "title": "Issue Baru Dibuat: Material Defect",
        "message": "Issue kualitas baru 'Material Defect' telah dibuat oleh admin_user.",
        "reference_type": "issue",
        "reference_id": "12",
        "metadata": {
          "issue_id": 12,
          "issue_name": "Material Defect",
          "created_by": "admin_user"
        },
        "is_read": false,
        "read_at": null,
        "created_at": "2025-12-02T09:15:00.000000Z"
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 5,
      "limit": 20,
      "total_docs": 95
    }
  }
}
```

---

### 2. GET `/notifications/unread-count` - Get Unread Count

Get jumlah notifikasi yang belum dibaca (untuk badge indicator).

**Example Request:**
```http
GET /api/v1/notifications/unread-count
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Unread notifications count retrieved successfully",
  "error_id": null,
  "data": {
    "unread_count": 12
  }
}
```

---

### 3. POST `/notifications/{id}/mark-as-read` - Mark as Read

Mark notifikasi tertentu sebagai sudah dibaca.

**Example Request:**
```http
POST /api/v1/notifications/15/mark-as-read
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Notification marked as read",
  "error_id": null,
  "data": {
    "id": 15,
    "is_read": true,
    "read_at": "2025-12-02T11:45:23.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "http_code": 404,
  "message": "Notification tidak ditemukan",
  "error_id": "NOTIFICATION_NOT_FOUND",
  "data": null
}
```

---

### 4. POST `/notifications/mark-all-as-read` - Mark All as Read

Mark semua notifikasi user sebagai sudah dibaca.

**Example Request:**
```http
POST /api/v1/notifications/mark-all-as-read
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "All notifications marked as read",
  "error_id": null,
  "data": {
    "marked_count": 12
  }
}
```

---

### 5. DELETE `/notifications/{id}` - Delete Notification

Hapus notifikasi tertentu.

**Example Request:**
```http
DELETE /api/v1/notifications/15
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Notification deleted successfully",
  "error_id": null,
  "data": null
}
```

**Error Response (404):**
```json
{
  "http_code": 404,
  "message": "Notification tidak ditemukan",
  "error_id": "NOTIFICATION_NOT_FOUND",
  "data": null
}
```

---

### 6. DELETE `/notifications/read/all` - Delete All Read

Hapus semua notifikasi yang sudah dibaca (bulk delete untuk cleanup).

**Example Request:**
```http
DELETE /api/v1/notifications/read/all
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "All read notifications deleted",
  "error_id": null,
  "data": {
    "deleted_count": 48
  }
}
```

---

## 🗄️ Database Schema

### Table: `notifications`

```sql
CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  reference_type VARCHAR(255) NULL,
  reference_id VARCHAR(255) NULL,
  metadata JSON NULL,
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  
  FOREIGN KEY (user_id) REFERENCES login_users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read),
  INDEX idx_type (type),
  INDEX idx_reference (reference_type, reference_id)
);
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `id` | BIGINT | Primary key |
| `user_id` | BIGINT | Foreign key ke `login_users.id` |
| `type` | VARCHAR | Jenis notifikasi (enum: 6 types) |
| `title` | VARCHAR | Judul notifikasi (short summary) |
| `message` | TEXT | Isi notifikasi lengkap |
| `reference_type` | VARCHAR | Tipe referensi (tool, issue, product_measurement, etc.) |
| `reference_id` | VARCHAR | ID referensi untuk deep linking |
| `metadata` | JSON | Data tambahan untuk context |
| `is_read` | BOOLEAN | Status sudah dibaca atau belum |
| `read_at` | TIMESTAMP | Waktu notifikasi dibaca |
| `created_at` | TIMESTAMP | Waktu notifikasi dibuat |
| `updated_at` | TIMESTAMP | Waktu notifikasi diupdate |

---

## ⏰ Scheduled Jobs

### 1. Tool Calibration Check

**Command:** `notifications:check-tool-calibration`  
**Schedule:** Daily at 08:00 AM  
**File:** `app/Console/Commands/CheckToolCalibrationDue.php`

**Logic:**
1. Query tools dengan `status = 'ACTIVE'`
2. Filter `next_calibration_at` dalam rentang 0-7 hari dari sekarang
3. Untuk setiap tool, kirim notifikasi ke **SEMUA USER**
4. Cek duplicate: hanya kirim 1x per hari per tool per user

---

### 2. Overdue Issues Check

**Command:** `notifications:check-overdue-issues`  
**Schedule:** Daily at 09:00 AM  
**File:** `app/Console/Commands/CheckOverdueIssues.php`

**Logic:**
1. Query issues dengan `status IN ('PENDING', 'ON_GOING')`
2. Filter `due_date < today`
3. Untuk setiap issue, kirim notifikasi ke **SEMUA USER**
4. Cek duplicate: hanya kirim 1x per hari per issue per user

---

### 3. Monthly Target Check

**Command:** `notifications:check-monthly-target`  
**Schedule:** Weekly on Monday at 08:00 AM  
**File:** `app/Console/Commands/CheckMonthlyTargetProgress.php`

**Logic:**
1. Hitung total inspections di bulan berjalan
2. Hitung expected inspections berdasarkan minggu ke-N
3. Jika actual < expected, kirim warning ke **SEMUA USER**
4. Metadata berisi: target, actual, expected, current_week

---

## 🧪 Testing

### Run Tests

```bash
# Run all notification tests
php artisan test --filter NotificationTest

# Run specific test
php artisan test --filter test_get_notifications_list
```

### Test Coverage

✅ **17 test cases** dengan **155 assertions**

**API Endpoint Tests:**
- ✅ Get notifications list
- ✅ Filter by read status
- ✅ Filter by type
- ✅ Get unread count
- ✅ Mark single notification as read
- ✅ Mark all notifications as read
- ✅ Delete notification
- ✅ Delete all read notifications
- ✅ User isolation (cannot access other user's notifications)
- ✅ Unauthenticated access prevention
- ✅ Pagination & metadata structure

**Scheduled Job Tests:**
- ✅ Tool calibration notification sent
- ✅ Issue overdue notification sent
- ✅ No duplicate notifications

**Event-Based Tests:**
- ✅ New issue notification sent
- ✅ New comment notification sent

### Test Results

```
PASS  Tests\Feature\NotificationTest
✓ get notifications list                                10.32s
✓ filter notifications by read status                    2.18s
✓ filter notifications by type                           2.13s
✓ get unread count                                       2.04s
✓ mark notification as read                              2.05s
✓ mark all notifications as read                         2.25s
✓ delete notification                                    3.22s
✓ delete all read notifications                          2.13s
✓ user can only access own notifications                 3.83s
✓ tool calibration notification sent                     3.64s
✓ issue overdue notification sent                        2.15s
✓ new issue notification sent                            2.08s
✓ new comment notification sent                          2.22s
✓ no duplicate tool calibration notifications            2.15s
✓ notifications pagination                               2.35s
✓ notification metadata structure                        2.33s
✓ unauthenticated user cannot access notifications       2.01s

Tests:  17 passed (155 assertions)
Duration: 49.94s
```

---

## 📝 Implementation Notes

### Backend Files

**Models:**
- `app/Models/Notification.php` - Model dengan helper methods untuk create notifications

**Controllers:**
- `app/Http/Controllers/Api/V1/NotificationController.php` - Handle API endpoints

**Commands (Scheduled Jobs):**
- `app/Console/Commands/CheckToolCalibrationDue.php`
- `app/Console/Commands/CheckOverdueIssues.php`
- `app/Console/Commands/CheckMonthlyTargetProgress.php`

**Migrations:**
- `database/migrations/2025_12_02_000002_create_notifications_table.php`

**Factories:**
- `database/factories/NotificationFactory.php` - Untuk testing

**Tests:**
- `tests/Feature/NotificationTest.php` - 17 test cases

**Routes:**
- Defined in `routes/api.php` under `/api/v1/notifications`

---

## 🎯 Frontend Integration Tips

### 1. Notification Badge
- Poll `/notifications/unread-count` setiap 30 detik
- Update badge indicator di navbar
- Ganti warna badge jika ada unread (merah)

### 2. Notification Panel
- Show list dengan pagination
- Group by date (Today, Yesterday, This Week, Older)
- Click notification → mark as read + navigate ke related page
- Bulk actions: Mark all read, Clear read

### 3. Notification Icons
Gunakan emoji atau icon per type:
- 🔧 `TOOL_CALIBRATION_DUE`
- ⚠️ `PRODUCT_OUT_OF_SPEC`
- 🐛 `NEW_ISSUE`
- ⏰ `ISSUE_OVERDUE`
- 💬 `NEW_COMMENT`
- 📊 `MONTHLY_TARGET_WARNING`

### 4. Deep Linking
Gunakan `reference_type` dan `reference_id` untuk navigate:
- `tool` → `/tools/{reference_id}`
- `issue` → `/issues/{reference_id}`
- `product_measurement` → `/measurements/{reference_id}`

### 5. Auto Refresh
- Polling every 30s untuk unread count
- Reload notification list saat panel dibuka
- Atau gunakan WebSocket untuk real-time (advanced)

---

## ✅ Status

**Notification System:** ✅ **PRODUCTION READY**

- ✅ Database schema created
- ✅ 6 notification types implemented
- ✅ 6 API endpoints working
- ✅ 3 scheduled jobs configured
- ✅ 3 real-time triggers integrated
- ✅ Unit tests passed (17/17)
- ✅ Documentation complete

---

**Last Updated:** December 2, 2025
