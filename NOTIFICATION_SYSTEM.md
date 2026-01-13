# Notification System Documentation

## Overview

Sistem notifikasi di SyncFlow berjalan **otomatis dari Backend** tanpa perlu Frontend memanggil endpoint khusus untuk trigger notifikasi. Notifikasi akan tercipta secara otomatis saat event tertentu terjadi (INSERT data) atau melalui scheduled jobs (daily/weekly).

---

## Tipe Notifikasi

### 1. Kalibrasi Alat Akan Jatuh Tempo (Tool Calibration Nearing Due Date)

**Judul:** Kalibrasi Alat Akan Jatuh Tempo  
**Deskripsi:** Mengingatkan bahwa jadwal kalibrasi alat mendekati jatuh tempo dalam beberapa hari ke depan dan perlu dijadwalkan.

**Kategori:** Tools Management

**Trigger:**
- **Job harian** (Daily Job) - dijalankan via scheduler
- Kondisi: `status = 'ACTIVE'` AND selisih antara `next_calibration_at` dan `CURRENT_DATE` berada dalam rentang 0-7 hari
- Command: `php artisan notifications:check-tool-calibration`
- File: `app/Console/Commands/CheckToolCalibrationDue.php`

**Implementasi:**
✅ **Sudah diimplementasi** - Command dijalankan secara otomatis via Laravel Scheduler

**Recipient:** Semua user (semua role)

**Pencegahan Duplikasi:** Hanya kirim 1 notifikasi per tool per user per hari

---

### 2. Produk Out of Spec Terdeteksi (Product Out of Spec Detected)

**Judul:** Produk Out of Spec Terdeteksi  
**Deskripsi:** Hasil pengukuran menemukan dimensi produk di luar batas toleransi yang ditentukan.

**Kategori:** Product Measurement

**Trigger:**
- **Saat simpan hasil ukur** (INSERT measurements) - Otomatis saat submit measurement
- Kondisi: Jika ada measurement item dengan `status = false` (NG) setelah submit
- Endpoint: `POST /api/v1/product-measurement/{id}/submit`
- File: `app/Http/Controllers/Api/V1/ProductMeasurementController.php` - `submitMeasurement()`

**Implementasi:**
✅ **Sudah diimplementasi** - Trigger otomatis saat submit measurement dengan hasil NG

**Recipient:** Semua user (semua role)

**Logic:**
- System mengecek setiap measurement item dalam hasil submit
- Jika ada item dengan `status = false`, item tersebut dianggap out of spec
- Notifikasi dikirim ke semua user dengan informasi batch number dan daftar item yang out of spec

---

### 3. Issue Baru Dibuat (New Issue Created)

**Judul:** Issue Baru Dibuat  
**Deskripsi:** Issue kualitas baru dibuat oleh operator dengan judul dan ringkasan.

**Kategori:** Issues Tracking

**Trigger:**
- **Saat issue baru dibuat** (INSERT issues) - Otomatis saat create issue
- Endpoint: `POST /api/v1/issues`
- File: `app/Http/Controllers/Api/V1/IssueController.php` - `store()`

**Implementasi:**
✅ **Sudah diimplementasi** - Trigger otomatis saat create issue

**Recipient:** Semua user kecuali creator issue

**Logic:**
- System mengecek setiap kali ada INSERT ke table `issues`
- Notifikasi "Issue Baru Dibuat" dikirim ke semua user kecuali user yang membuat issue

---

### 4. Issue Overdue

**Judul:** Issue Overdue  
**Deskripsi:** Issue telah melewati due date dan masih terbuka, memerlukan tindakan segera.

**Kategori:** Issues Tracking

**Trigger:**
- **Job harian** (Daily Job) - dijalankan via scheduler
- Kondisi: `status IN ('PENDING', 'ON_GOING')` AND `due_date < CURRENT_DATE`
- Command: `php artisan notifications:check-overdue-issues`
- File: `app/Console/Commands/CheckOverdueIssues.php`

**Implementasi:**
✅ **Sudah diimplementasi** - Command dijalankan secara otomatis via Laravel Scheduler

**Recipient:** Semua user (semua role)

**Logic:**
- System mencari semua issue dengan status `PENDING` atau `ON_GOING` yang `due_date` sudah lewat
- Untuk setiap issue yang overdue, kirim notifikasi ke semua user
- Menampilkan jumlah hari yang sudah overdue

**Pencegahan Duplikasi:** Hanya kirim 1 notifikasi per issue per user per hari

---

### 5. Komentar Baru pada Issue (New Comment on Issue)

**Judul:** Komentar Baru pada Issue  
**Deskripsi:** Ada komentar/diskusi baru pada issue yang diikuti atau ditugaskan kepada user.

**Kategori:** Issues Tracking

**Trigger:**
- **Saat komentar baru disimpan** (INSERT issue_comments) - Otomatis saat add comment
- Endpoint: `POST /api/v1/issues/{id}/comments`
- File: `app/Http/Controllers/Api/V1/IssueController.php` - `addComment()`

**Implementasi:**
✅ **Sudah diimplementasi** - Trigger otomatis saat add comment

**Recipient:** Semua user kecuali user yang membuat komentar

**Logic:**
- System mengecek setiap kali ada INSERT ke table `issue_comments`
- Notifikasi dikirim ke semua user yang follow issue (creator, PIC, subscriber) kecuali user yang membuat komentar
- Notifikasi berisi preview komentar (100 karakter pertama)

---

### 6. Target Bulanan per Minggu Ini Belum Tercapai (Monthly Target Not Reached This Week)

**Judul:** Target Bulanan per Minggu Ini Belum Tercapai  
**Deskripsi:** Pencapaian terhadap target bulanan hingga minggu ini masih di bawah persentase yang ditetapkan. Memerlukan percepatan penyelesaian inspeksi dan penanganan issue untuk mencapai target akhir bulan.

**Kategori:** Monthly Target

**Trigger:**
- **Job mingguan** (Weekly Job) - dijalankan via scheduler
- Kondisi:
  1. Ambil `monthly_target` (total measurements expected this month)
  2. Hitung `actual_inspection` hingga hari ini (completed measurements this month)
  3. Hitung `expected = monthly_target * (minggu_ke_n / total_minggu_bulan_ini)`
  4. Jika `actual_inspection < expected`, kirim notifikasi
- Command: `php artisan notifications:check-monthly-target`
- File: `app/Console/Commands/CheckMonthlyTargetProgress.php`

**Implementasi:**
✅ **Sudah diimplementasi** - Command dijalankan secara otomatis via Laravel Scheduler

**Recipient:** Semua user (semua role), terutama QA Leader / Manager QA

**Logic:**
- System menghitung progress bulanan setiap minggu
- Jika actual progress < expected progress (berdasarkan minggu ke berapa), kirim notifikasi warning
- Notifikasi menampilkan gap persentase dan jumlah inspections yang kurang

**Pencegahan Duplikasi:** Hanya kirim 1 notifikasi per user per minggu

---

## Auto-Trigger Mechanism

### ✅ **SEMUA NOTIFIKASI TRIGGER OTOMATIS DARI BACKEND**

Frontend **TIDAK PERLU** memanggil endpoint khusus untuk trigger notifikasi. Notifikasi akan tercipta otomatis saat:

1. **Data Operations (INSERT)**
   - User hit endpoint untuk create/save data (e.g., create issue, submit measurement)
   - Backend otomatis trigger notifikasi di dalam controller method
   - Frontend hanya perlu hit endpoint biasa untuk operasi data

2. **Scheduled Jobs (Daily/Weekly)**
   - Jobs dijalankan otomatis oleh Laravel Scheduler
   - Tidak perlu intervensi manual atau API call dari frontend
   - Jobs di-schedule di `app/Console/Kernel.php`

---

## Endpoint Notifikasi untuk Frontend

### Get Notifications List
```
GET /api/v1/notifications
Query params: page, limit (optional)
```

### Get Unread Count
```
GET /api/v1/notifications/unread-count
```

### Mark Notification as Read
```
POST /api/v1/notifications/{id}/mark-as-read
```

### Mark All as Read
```
POST /api/v1/notifications/mark-all-as-read
```

### Delete Notification
```
DELETE /api/v1/notifications/{id}
```

### Delete All Read Notifications
```
DELETE /api/v1/notifications/all-read
```

---

## Response Format

### Notification Object
```json
{
  "id": 1,
  "type": "PRODUCT_OUT_OF_SPEC",
  "title": "Produk Out of Spec Terdeteksi",
  "message": "Batch 'BATCH-001' memiliki 2 measurement item di luar toleransi: Thickness (A), Width (B). Segera lakukan verifikasi dan tindakan korektif.",
  "reference_type": "product_measurement",
  "reference_id": "MSR-ABC123",
  "metadata": {
    "measurement_id": "MSR-ABC123",
    "batch_number": "BATCH-001",
    "product_name": "COTO",
    "out_of_spec_items": [
      {
        "item_name": "Thickness (A)",
        "item_name_id": "thickness_a"
      },
      {
        "item_name": "Width (B)",
        "item_name_id": "width_b"
      }
    ]
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2026-01-14T10:30:00.000000Z",
  "updated_at": "2026-01-14T10:30:00.000000Z"
}
```

---

## Scheduler Configuration

Pastikan Laravel Scheduler sudah dikonfigurasi untuk menjalankan jobs otomatis:

**File:** `app/Console/Kernel.php`
```php
protected function schedule(Schedule $schedule)
{
    // Daily: Check tool calibration due (run at 8 AM every day)
    $schedule->command('notifications:check-tool-calibration')
        ->dailyAt('08:00');
    
    // Daily: Check overdue issues (run at 9 AM every day)
    $schedule->command('notifications:check-overdue-issues')
        ->dailyAt('09:00');
    
    // Weekly: Check monthly target progress (run every Monday at 8 AM)
    $schedule->command('notifications:check-monthly-target')
        ->weeklyOn(1, '08:00');
}
```

**Cron Job Required:**
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Implementation Status

| No | Notification Type | Trigger Type | Status | Implementation |
|----|------------------|--------------|--------|----------------|
| 1 | Tool Calibration Due | Daily Job | ✅ | `CheckToolCalibrationDue.php` |
| 2 | Product Out of Spec | INSERT (Auto) | ✅ | `ProductMeasurementController::submitMeasurement()` |
| 3 | New Issue Created | INSERT (Auto) | ✅ | `IssueController::store()` |
| 4 | Issue Overdue | Daily Job | ✅ | `CheckOverdueIssues.php` |
| 5 | New Comment on Issue | INSERT (Auto) | ✅ | `IssueController::addComment()` |
| 6 | Monthly Target Warning | Weekly Job | ✅ | `CheckMonthlyTargetProgress.php` |

**All notifications are automatically triggered by backend - Frontend tidak perlu hit endpoint khusus untuk trigger.**

---

## Testing

### Manual Test Trigger (Auto)
1. **Product Out of Spec:**
   - Submit measurement dengan hasil NG
   - ✅ Notifikasi otomatis tercipta

2. **New Issue:**
   - Create issue baru via `POST /api/v1/issues`
   - ✅ Notifikasi otomatis tercipta

3. **New Comment:**
   - Add comment via `POST /api/v1/issues/{id}/comments`
   - ✅ Notifikasi otomatis tercipta

### Manual Test Scheduled Jobs
1. **Tool Calibration Due:**
   ```bash
   php artisan notifications:check-tool-calibration
   ```

2. **Issue Overdue:**
   ```bash
   php artisan notifications:check-overdue-issues
   ```

3. **Monthly Target Warning:**
   ```bash
   php artisan notifications:check-monthly-target
   ```

---

**Last Updated:** 2026-01-14  
**Status:** ✅ All Notifications Implemented and Auto-Triggered
