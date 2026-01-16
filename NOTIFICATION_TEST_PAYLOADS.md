# Notification Test Payloads

Dokumen ini berisi payload untuk testing semua 6 point notification yang sudah diimplementasi.

## Status Implementasi

✅ **Semua 6 point notification sudah terimplementasi dan auto-trigger**

| No | Notification Type | Trigger | Status |
|----|------------------|---------|--------|
| 1 | Kalibrasi Alat Akan Jatuh Tempo | Daily Job | ✅ Implemented |
| 2 | Produk Out of Spec Terdeteksi | INSERT (Auto) | ✅ Implemented |
| 3 | Issue Baru Dibuat | INSERT (Auto) | ✅ Implemented |
| 4 | Issue Overdue | Daily Job | ✅ Implemented |
| 5 | Komentar Baru pada Issue | INSERT (Auto) | ✅ Implemented |
| 6 | Target Bulanan Belum Tercapai | Weekly Job | ✅ Implemented |

---

## 1. Kalibrasi Alat Akan Jatuh Tempo

**Trigger:** Daily Job (Command: `php artisan notifications:check-tool-calibration`)  
**Kondisi:** Tool dengan `status = 'ACTIVE'` dan `next_calibration_at` dalam rentang 0-7 hari dari hari ini

### Setup Data (Create Tool)

**Endpoint:** `POST /api/v1/tools`  
**Headers:** 
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload untuk Tool yang akan jatuh tempo dalam 3 hari:**
```json
{
  "tool_name": "Digital Caliper Pro",
  "tool_model": "MITUTOYO-CD-15CPX",
  "tool_type": "MECHANICAL",
  "imei": "TEST-CAL-001",
  "status": "ACTIVE",
  "last_calibration_at": "2025-01-10",
  "next_calibration_at": "2026-01-18"
}
```

**Payload untuk Tool yang akan jatuh tempo dalam 7 hari:**
```json
{
  "tool_name": "Optical Microscope",
  "tool_model": "OLYMPUS-BX53",
  "tool_type": "OPTICAL",
  "imei": "TEST-CAL-002",
  "status": "ACTIVE",
  "last_calibration_at": "2025-01-10",
  "next_calibration_at": "2026-01-22"
}
```

**Note:** Ganti `next_calibration_at` dengan tanggal 0-7 hari dari hari ini untuk trigger notification.

### Test Command

```bash
php artisan notifications:check-tool-calibration
```

**Expected Result:** Notification dengan type `TOOL_CALIBRATION_DUE` akan terbuat untuk semua user untuk setiap tool yang memenuhi kondisi.

---

## 2. Produk Out of Spec Terdeteksi

**Trigger:** Otomatis saat submit measurement dengan hasil NG (status = false)

### Setup: Buat Product Measurement terlebih dahulu

**Endpoint:** `POST /api/v1/product-measurement`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload Create Measurement:**
```json
{
  "product_id": 1,
  "batch_number": "BATCH-TEST-001",
  "sample_count": 5,
  "measurement_type": "INCOMING",
  "due_date": "2026-01-30"
}
```

**Response akan berisi `measurement_id` - gunakan ID ini untuk submit.**

### Submit Measurement dengan Hasil NG (Out of Spec)

**Endpoint:** `POST /api/v1/product-measurement/{measurement_id}/submit`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload untuk Trigger Out of Spec Notification:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "item_001",
      "status": false,
      "samples": [
        {
          "sample_index": 1,
          "status": false,
          "single_value": 15.5
        },
        {
          "sample_index": 2,
          "status": false,
          "single_value": 16.2
        }
      ],
      "variable_values": []
    },
    {
      "measurement_item_name_id": "item_002",
      "status": false,
      "samples": [
        {
          "sample_index": 1,
          "status": false,
          "single_value": 25.8
        }
      ],
      "variable_values": []
    }
  ]
}
```

**Note:** Pastikan `status: false` untuk trigger out of spec notification. Ganti `measurement_item_name_id` dengan ID yang valid dari product configuration.

**Expected Result:** Notification dengan type `PRODUCT_OUT_OF_SPEC` akan terbuat untuk semua user otomatis setelah submit.

---

## 3. Issue Baru Dibuat

**Trigger:** Otomatis saat create issue baru

**Endpoint:** `POST /api/v1/issues`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload:**
```json
{
  "issue_name": "Kualitas Produk Tidak Sesuai Spesifikasi",
  "description": "Produk batch BATCH-001 ditemukan memiliki dimensi di luar toleransi yang ditentukan. Perlu investigasi lebih lanjut.",
  "status": "PENDING",
  "category": "QUALITY",
  "due_date": "2026-02-15"
}
```

**Payload Alternatif:**
```json
{
  "issue_name": "Alat Ukur Rusak",
  "description": "Digital caliper menunjukkan pembacaan yang tidak akurat. Perlu kalibrasi ulang atau perbaikan.",
  "status": "ON_GOING",
  "category": "EQUIPMENT",
  "due_date": "2026-02-20"
}
```

**Expected Result:** Notification dengan type `NEW_ISSUE` akan terbuat untuk semua user kecuali user yang membuat issue.

---

## 4. Issue Overdue

**Trigger:** Daily Job (Command: `php artisan notifications:check-overdue-issues`)  
**Kondisi:** Issue dengan `status IN ('PENDING', 'ON_GOING')` dan `due_date < CURRENT_DATE`

### Setup: Buat Issue dengan Due Date di Masa Lalu

**Endpoint:** `POST /api/v1/issues`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload untuk Issue Overdue (PENDING):**
```json
{
  "issue_name": "Issue Overdue Test - PENDING",
  "description": "Issue ini dibuat untuk test notification overdue dengan status PENDING",
  "status": "PENDING",
  "category": "QUALITY",
  "due_date": "2026-01-10"
}
```

**Payload untuk Issue Overdue (ON_GOING):**
```json
{
  "issue_name": "Issue Overdue Test - ON_GOING",
  "description": "Issue ini dibuat untuk test notification overdue dengan status ON_GOING",
  "status": "ON_GOING",
  "category": "PROCESS",
  "due_date": "2026-01-12"
}
```

**Note:** Pastikan `due_date` adalah tanggal di masa lalu (sebelum hari ini) dan status adalah `PENDING` atau `ON_GOING`.

### Test Command

```bash
php artisan notifications:check-overdue-issues
```

**Expected Result:** Notification dengan type `ISSUE_OVERDUE` akan terbuat untuk semua user untuk setiap issue yang overdue.

---

## 5. Komentar Baru pada Issue

**Trigger:** Otomatis saat add comment pada issue

### Setup: Pastikan ada Issue terlebih dahulu

Buat issue terlebih dahulu menggunakan payload di Point 3, atau gunakan issue yang sudah ada.

**Endpoint:** `POST /api/v1/issues/{issue_id}/comments`  
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload:**
```json
{
  "comment": "Saya sudah melakukan investigasi awal. Ditemukan bahwa produk batch tersebut memang memiliki dimensi di luar toleransi. Perlu dilakukan re-measurement untuk konfirmasi."
}
```

**Payload Alternatif:**
```json
{
  "comment": "Status update: Issue sedang dalam proses penanganan. Tim QA sudah melakukan sampling ulang dan hasilnya masih menunjukkan ketidaksesuaian."
}
```

**Expected Result:** Notification dengan type `NEW_COMMENT` akan terbuat untuk semua user kecuali user yang menambahkan komentar.

---

## 6. Target Bulanan Belum Tercapai

**Trigger:** Weekly Job (Command: `php artisan notifications:check-monthly-target`)  
**Kondisi:** Actual inspections < Expected progress (berdasarkan minggu ke-n dari total minggu dalam bulan)

### Setup: Pastikan Data Measurement

Command ini akan menghitung:
- `monthly_target` = total products
- `actual_inspections` = ProductMeasurement dengan status `COMPLETED` dalam bulan ini
- `expected_percentage` = (minggu_ke_n / total_minggu) * 100
- `actual_percentage` = (actual_inspections / monthly_target) * 100

Jika `actual_percentage < expected_percentage`, notification akan dikirim.

### Test Command

```bash
php artisan notifications:check-monthly-target
```

**Expected Result:** Notification dengan type `MONTHLY_TARGET_WARNING` akan terbuat untuk semua user jika actual progress < expected progress.

**Note:** Untuk memastikan notification ter-trigger, pastikan:
1. Ada beberapa products di database
2. Jumlah ProductMeasurement dengan status `COMPLETED` dalam bulan ini lebih sedikit dari expected (berdasarkan minggu ke-n)

---

## Cara Test Semua Notification

### Test Auto-Trigger Notifications (Point 2, 3, 5)

1. **Test Point 3 - New Issue:**
   ```bash
   # Gunakan payload Point 3
   POST /api/v1/issues
   ```

2. **Test Point 5 - New Comment:**
   ```bash
   # Gunakan payload Point 5 (pastikan ada issue_id)
   POST /api/v1/issues/{issue_id}/comments
   ```

3. **Test Point 2 - Out of Spec:**
   ```bash
   # 1. Create measurement
   POST /api/v1/product-measurement
   
   # 2. Submit dengan hasil NG
   POST /api/v1/product-measurement/{measurement_id}/submit
   ```

### Test Scheduled Job Notifications (Point 1, 4, 6)

1. **Test Point 1 - Tool Calibration:**
   ```bash
   # 1. Create tool dengan next_calibration_at dalam 0-7 hari
   POST /api/v1/tools
   
   # 2. Run command
   php artisan notifications:check-tool-calibration
   ```

2. **Test Point 4 - Issue Overdue:**
   ```bash
   # 1. Create issue dengan due_date di masa lalu
   POST /api/v1/issues
   
   # 2. Run command
   php artisan notifications:check-overdue-issues
   ```

3. **Test Point 6 - Monthly Target:**
   ```bash
   # Run command (akan check otomatis)
   php artisan notifications:check-monthly-target
   ```

---

## Verifikasi Notification

Setelah menjalankan test, verifikasi notification dengan:

**Endpoint:** `GET /api/v1/notifications`  
**Headers:**
```
Authorization: Bearer {token}
```

**Response akan menampilkan semua notification yang terbuat, termasuk:**
- `type`: Type notification (TOOL_CALIBRATION_DUE, PRODUCT_OUT_OF_SPEC, NEW_ISSUE, ISSUE_OVERDUE, NEW_COMMENT, MONTHLY_TARGET_WARNING)
- `title`: Judul notification
- `message`: Pesan notification
- `metadata`: Data tambahan terkait notification
- `is_read`: Status sudah dibaca atau belum

---

## Catatan Penting

1. **Auto-Trigger:** Point 2, 3, dan 5 akan trigger otomatis saat operasi INSERT dilakukan. Tidak perlu hit endpoint khusus.

2. **Scheduled Jobs:** Point 1, 4, dan 6 menggunakan scheduled jobs yang berjalan otomatis via Laravel Scheduler. Untuk testing manual, jalankan command artisan.

3. **Duplikasi Prevention:** 
   - Point 1: Hanya 1 notifikasi per tool per user per hari
   - Point 4: Hanya 1 notifikasi per issue per user per hari
   - Point 6: Hanya 1 notifikasi per user per minggu

4. **Recipients:** 
   - Semua notification dikirim ke semua user (semua role)
   - Kecuali Point 3 dan 5: tidak dikirim ke creator/commenter

---

**Last Updated:** 2026-01-15  
**Status:** ✅ All Notifications Ready for Testing
