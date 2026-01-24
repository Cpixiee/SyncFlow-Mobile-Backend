# Notification System - Flutter Implementation Guide

## ✅ Status: Semua Notification Sudah Diimplementasi & Auto-Trigger

**Jawaban Singkat untuk FE:**
- ✅ **Cronjob sudah dikonfigurasi** - Jobs berjalan otomatis via Laravel Scheduler
- ✅ **Semua 6 tipe notification sudah dibuat dan trigger OTOMATIS**
- ✅ **Semua notification trigger OTOMATIS dari Backend** - Frontend TIDAK perlu hit endpoint khusus
- ❌ **Frontend TIDAK PERLU hit endpoint untuk trigger notifikasi** - Hanya perlu GET notifications

**Cronjob Configuration:**
- ✅ Daily Job: Tool Calibration Due (08:00 AM)
- ✅ Daily Job: Issue Overdue (09:00 AM)  
- ✅ Weekly Job: Monthly Target Warning (Monday 08:00 AM)

File: `app/Console/Kernel.php` - Scheduler sudah dikonfigurasi

---

## 📋 Checklist Implementation Status

| No | Notification Type | Trigger | Auto-Trigger | Status |
|----|------------------|---------|--------------|--------|
| 1 | Tool Calibration Due | Daily Job (08:00) | ✅ Ya - Cronjob | ✅ Implemented |
| 2 | Product Out of Spec | INSERT (Auto) | ✅ Ya - Backend | ✅ Implemented |
| 3 | New Issue Created | INSERT (Auto) | ✅ Ya - Backend | ✅ Implemented |
| 4 | Issue Overdue | Daily Job (09:00) | ✅ Ya - Cronjob | ✅ Implemented |
| 5 | New Comment on Issue | INSERT (Auto) | ✅ Ya - Backend | ✅ Implemented |
| 6 | Monthly Target Warning | Weekly Job (Monday 08:00) | ✅ Ya - Cronjob | ✅ Implemented |

---

## 🔄 Cara Kerja Notification System

### Auto-Trigger Mechanism

**Frontend TIDAK PERLU hit endpoint khusus untuk trigger notifikasi!**

#### 1. **Data Operations (INSERT) - Trigger Otomatis**

Ketika user melakukan operasi data normal, backend otomatis trigger notifikasi:

**Contoh Flow:**
1. User submit measurement dengan hasil NG via `POST /api/v1/product-measurement/{id}/submit`
2. Backend otomatis:
   - Simpan measurement results
   - Cek apakah ada NG items
   - Jika ada NG → Auto-create PRODUCT_OUT_OF_SPEC notification
3. Frontend TIDAK perlu hit endpoint khusus untuk trigger notifikasi!

**Notifikasi yang trigger otomatis saat INSERT:**
- ✅ **Product Out of Spec** - Otomatis saat submit measurement dengan NG
- ✅ **New Issue Created** - Otomatis saat create issue
- ✅ **New Comment on Issue** - Otomatis saat add comment

#### 2. **Scheduled Jobs (Daily/Weekly) - Cronjob Otomatis**

Jobs berjalan otomatis di backend via Laravel Scheduler:


**Server Cron Setup (di server):**
Cronjob: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`

**Note:** Ini harus di-setup di server oleh DevOps/Admin server, bukan di code.

**Notifikasi yang trigger via cronjob:**
- ✅ **Tool Calibration Due** - Daily job, cek otomatis setiap hari jam 08:00
- ✅ **Issue Overdue** - Daily job, cek otomatis setiap hari jam 09:00
- ✅ **Monthly Target Warning** - Weekly job, cek otomatis setiap Senin jam 08:00

---

## 📱 Flutter Implementation

### Frontend Hanya Perlu: **GET Notifications & Update Status**

Frontend **TIDAK PERLU** hit endpoint untuk trigger notifikasi. Frontend hanya perlu:

1. **GET notifications** - Ambil list notifikasi
2. **Mark as read** - Update status notifikasi
3. **Polling/Refresh** - Cek notifikasi baru secara berkala

### 1. Get Notifications List

**Endpoint:**
```
GET /api/v1/notifications
```

**Query Parameters:**
- `page` (optional): Halaman yang diminta, default: 1
- `limit` (optional): Jumlah item per halaman, default: 10

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Notifications retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": 1,
        "type": "PRODUCT_OUT_OF_SPEC",
        "title": "Produk Out of Spec Terdeteksi",
        "message": "Batch 'BATCH-001' memiliki 2 measurement item di luar toleransi...",
        "reference_type": "product_measurement",
        "reference_id": "MSR-ABC123",
        "metadata": {
          "measurement_id": "MSR-ABC123",
          "batch_number": "BATCH-001",
          "product_name": "COTO",
          "out_of_spec_items": [...]
        },
        "is_read": false,
        "read_at": null,
        "created_at": "2026-01-14T10:30:00.000000Z"
      }
    ],
    "metadata": {
      "current_page": "1",
      "total_page": 3,
      "limit": "20",
      "total_docs": 45
    }
  }
}
```

### 2. Get Unread Count

**Endpoint:**
```
GET /api/v1/notifications/unread-count
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Unread count retrieved successfully",
  "error_id": null,
  "data": {
    "unread_count": 5
  }
}
```

### 3. Mark Notification as Read

**Endpoint:**
```
POST /api/v1/notifications/{id}/mark-as-read
```

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Path Parameters:**
- `{id}`: Notification ID (integer)

### 4. Mark All as Read

**Endpoint:**
```
POST /api/v1/notifications/mark-all-as-read
```

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

### 5. Delete Notification

**Endpoint:**
```
DELETE /api/v1/notifications/{id}
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Path Parameters:**
- `{id}`: Notification ID (integer)

### 6. Delete All Read Notifications

**Endpoint:**
```
DELETE /api/v1/notifications/all-read
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Note:** Menghapus semua notifikasi yang sudah dibaca (`is_read = true`)

---

## 🔔 Notification Polling Strategy

Karena notifikasi trigger otomatis, Frontend perlu implement **polling** untuk get notifikasi baru.

### Recommended Approach: Background Refresh

**Konsep:**
- Poll saat app aktif (foreground) - setiap 30-60 detik
- Stop polling saat app di background - untuk save battery
- Refresh saat app kembali ke foreground - ambil notifikasi terbaru

**Polling Interval:**
- **Recommended:** 30-60 detik
- **Minimum:** 15 detik (jika perlu real-time)
- **Maximum:** 5 menit (jika ingin hemat battery)

### Alternative Approaches:

**1. Periodic Polling**
- Poll terus menerus dengan interval tetap
- Simple tapi bisa boros battery

**2. Pull-to-Refresh**
- User manual refresh dengan pull gesture
- Hemat battery tapi tidak real-time

**3. Event-Based (Jika ada WebSocket/SSE)**
- Real-time update via WebSocket
- Paling efisien tapi butuh setup tambahan

---

## 🎯 Implementation Guidelines untuk Flutter

### Service Structure

Buat service class untuk handle notification operations:
- `getNotifications(page, limit)` - GET /api/v1/notifications
- `getUnreadCount()` - GET /api/v1/notifications/unread-count
- `markAsRead(notificationId)` - POST /api/v1/notifications/{id}/mark-as-read
- `markAllAsRead()` - POST /api/v1/notifications/mark-all-as-read
- `deleteNotification(notificationId)` - DELETE /api/v1/notifications/{id}
- `deleteAllRead()` - DELETE /api/v1/notifications/all-read
- `startPolling(interval)` - Start periodic polling
- `stopPolling()` - Stop polling (saat app di background)

---

## 📊 Notification Types & Navigation Handling

Ketika user tap notifikasi, Frontend perlu handle navigation berdasarkan `type`:

### 1. PRODUCT_OUT_OF_SPEC
- **Navigate to:** Measurement Detail Page
- **Parameter:** `measurement_id` dari `metadata.measurement_id`
- **Route:** `/measurement/{measurement_id}` atau `MeasurementDetailPage(measurementId)`

### 2. NEW_ISSUE
- **Navigate to:** Issue Detail Page
- **Parameter:** `issue_id` dari `metadata.issue_id`
- **Route:** `/issue/{issue_id}` atau `IssueDetailPage(issueId)`

### 3. NEW_COMMENT
- **Navigate to:** Issue Detail Page (dengan highlight comment)
- **Parameter:** 
  - `issue_id` dari `metadata.issue_id`
  - `comment_id` dari `metadata.comment_id` (optional, untuk highlight)
- **Route:** `/issue/{issue_id}` atau `IssueDetailPage(issueId, highlightCommentId)`

### 4. TOOL_CALIBRATION_DUE
- **Navigate to:** Tools Page / Tool List Page
- **Parameter:** `tool_id` dari `metadata.tool_id` (optional)
- **Route:** `/tools` atau `ToolsPage()`

### 5. ISSUE_OVERDUE
- **Navigate to:** Issue Detail Page
- **Parameter:** `issue_id` dari `metadata.issue_id`
- **Route:** `/issue/{issue_id}` atau `IssueDetailPage(issueId)`

### 6. MONTHLY_TARGET_WARNING
- **Navigate to:** Dashboard / Monthly Target Page
- **Parameter:** Tidak ada parameter khusus
- **Route:** `/dashboard` atau `DashboardPage()`

---

## ✅ Summary untuk Frontend

### Yang Perlu Dilakukan Frontend:
1. ✅ **GET notifications** - Ambil list notifikasi
2. ✅ **Polling/Refresh** - Cek notifikasi baru secara berkala (30-60 detik)
3. ✅ **Mark as read** - Update status saat user baca notifikasi
4. ✅ **Navigate** - Handle tap notifikasi untuk navigate ke detail page

### Yang TIDAK Perlu Dilakukan Frontend:
❌ **TIDAK PERLU hit endpoint untuk trigger notifikasi**
- Backend sudah handle semua trigger otomatis
- Frontend hanya perlu GET notifikasi yang sudah dibuat oleh backend

### Notification Flow:

1. **User Action** → User melakukan action (e.g., Submit Measurement)
2. **Backend Process** → Backend proses data dan auto-create notification
3. **Frontend Polling** → Frontend polling GET /notifications secara berkala
4. **Display** → Frontend tampilkan notifikasi ke user
5. **User Tap** → User tap notifikasi
6. **Navigate** → Frontend navigate ke detail page sesuai type notifikasi
7. **Mark as Read** → Frontend mark notifikasi sebagai read

---

## 🔧 Server Setup Checklist

Pastikan server sudah dikonfigurasi dengan benar:

### 1. Laravel Scheduler Cronjob

**Setup di Server:**
- File: `crontab -e` (di server)
- Cronjob: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`
- **Note:** Setup ini dilakukan di server, bukan di code

### 2. Verify Scheduler Working

**Test Commands (di server):**
- `php artisan notifications:check-tool-calibration`
- `php artisan notifications:check-overdue-issues`
- `php artisan notifications:check-monthly-target`
- `php artisan schedule:list` (cek scheduled tasks)

---

**Last Updated:** 2026-01-14  
**Status:** ✅ Ready for Flutter Implementation  
**Auto-Trigger:** ✅ Semua notification trigger otomatis dari backend
