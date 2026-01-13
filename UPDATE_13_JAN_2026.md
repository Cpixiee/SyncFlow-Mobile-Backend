# Update 13 Januari 2026

## Issues Fixed

### 1. ✅ Status COMPLETED yang Salah

**Issue:** 
- Status menunjukkan "NEED_TO_MEASURE" padahal measurement sudah COMPLETED dengan hasil NG
- Seharusnya status "NG" untuk COMPLETED measurement dengan hasil NG

**Fix di:** `ProductMeasurementController.php` - `getResult()` method (line ~3395)

**Sebelum:**
```php
if ($measurement->status === 'COMPLETED') {
    $status = $okCount > 0 && $ngCount === 0 ? 'OK' : ($ngCount > 0 ? 'NEED_TO_MEASURE' : 'TODO');
}
```

**Sesudah:**
```php
if ($measurement->status === 'COMPLETED') {
    if ($ngCount > 0) {
        $status = 'NG'; // Ada sample NG
    } elseif ($okCount > 0) {
        $status = 'OK'; // Semua OK
    } else {
        $status = 'OK'; // No evaluated samples (all SKIP_CHECK)
    }
}
```

**Status Logic yang Benar:**
- `COMPLETED` + all OK → status = `"OK"`
- `COMPLETED` + any NG → status = `"NG"`
- `IN_PROGRESS` + pernah submit dengan NG → status = `"NEED_TO_MEASURE"`
- `IN_PROGRESS` + belum pernah submit NG → status = `"ONGOING"`
- `PENDING` → status = `"TODO"`

---

### 2. ✅ Summary Calculation yang Lebih Jelas

**Issue:**
- Summary field "sample" tidak jelas (menunjukkan max sample count, bukan total)
- Tidak ada info tentang jumlah measurement items

**Fix di:** `ProductMeasurementController.php` - `getResult()` method (line ~3359)

**Sebelum:**
```json
{
  "summary": {
    "sample": 5,  // Max sample count
    "ok": 7,
    "ng": 1,
    "ng_ratio": 12.5
  }
}
```

**Sesudah:**
```json
{
  "summary": {
    "total_measurement_items": 2,  // ✅ NEW: Total measurement items
    "max_sample_count": 5,         // ✅ RENAMED: Max sample count dari semua items
    "total_samples": 8,            // ✅ NEW: Total samples yang di-evaluate (3+5)
    "ok": 7,
    "ng": 1,
    "ng_ratio": 12.5
  }
}
```

**Penjelasan:**
- `total_measurement_items`: Jumlah measurement items (2 dalam contoh: sample_3 dan sample_5)
- `max_sample_count`: Sample count terbesar dari semua items (max(3, 5) = 5)
- `total_samples`: Total samples yang di-evaluate (3 + 5 = 8)
- `ok`: Total samples dengan status OK (7)
- `ng`: Total samples dengan status NG (1)
- `ng_ratio`: Persentase NG dari total samples (1/8 = 12.5%)

---

### 3. ✅ Measurement Time Preservation

**Issue:**
- Measurement time kadang hilang di response

**Status:**
- Measurement time sudah di-preserve di semua endpoint
- Sudah ada di:
  - `checkSamples()` response (line 1134)
  - `saveProgress()` processing
  - `submitMeasurement()` processing
  - `getResult()` response (line 3250)

**Verifikasi:**
- Setiap sample sudah include `measurement_time` di response
- Format ISO 8601: `"2026-01-13T00:33:23.361"`

---

### 4. ✅ Quarter Detection Bug - FIXED (Update 12-01-26)

**Issue:**
- Endpoint `/product-measurement/by-product` mengelompokkan semua measurements ke quarter yang salah
- Contoh: Product `CIVUS 0.75 G` diukur di Q1 2026 dan Q2 2026, tapi response menunjukkan Q2 2025 dengan 2 batch numbers

**Root Cause:**
- Logic menggunakan `$measurement->product->quarter` (quarter saat product dibuat) ❌
- Seharusnya menggunakan `$measurement->due_date` (quarter saat measurement dilakukan) ✅

**Fix di:** `ProductMeasurementController.php` - `getByProduct()` method (line ~2988)

**Sebelum (SALAH):**
```php
// Mengambil quarter dari product (kapan product dibuat)
$quarter = $measurement->product->quarter;
if ($quarter) {
    $year = $quarter->year;  // ← Ini year product dibuat!
    $quarterNum = (int) str_replace('Q', '', $quarter->name);
}
```

**Sesudah (BENAR):**
```php
// Mengambil quarter dari measurement (kapan measurement dilakukan)
if ($measurement->due_date) {
    $dueDate = \Carbon\Carbon::parse($measurement->due_date);
    $year = $dueDate->year;  // ← Ini year measurement!
    $month = $dueDate->month;
    // Calculate quarter from month (1-3=Q1, 4-6=Q2, 7-9=Q3, 10-12=Q4)
}
```

**Expected Result:**
- MSR-F7Y0WZUT (due_date: 2026-01-31) → Q1 2026 ✅
- MSR-GINUYHMM (due_date: 2026-04-15) → Q2 2026 ✅

**Response sekarang:**
```json
{
  "docs": [
    {
      "quarter": 2,
      "year": 2026,  // ✅ FIXED: Dari 2025 jadi 2026
      "product_measurements": [
        {
          "product_measurement_id": "MSR-GINUYHMM",
          "batch_number": "563223211",
          "finished": true
        }
      ]
    },
    {
      "quarter": 1,
      "year": 2026,  // ✅ FIXED: Sekarang ada Q1 2026
      "product_measurements": [
        {
          "product_measurement_id": "MSR-F7Y0WZUT",
          "batch_number": "23232323232",
          "finished": true
        }
      ]
    }
  ]
}
```

---

### 5. ✅ Enhanced DERIVED Source (Update 12-01-26)

**New Features:**
- DERIVED items bisa punya variables, pre-processing formulas, dan rule evaluation sendiri (berbeda dengan source)
- Validasi sample_amount dan type harus sama dengan source
- Auto-copy samples dari source saat checkSamples
- Auto-process variables, pre-processing, dan joint formulas

**Validasi di ProductController:**
```php
// Sample amount harus sama
if ($sourceSampleAmount !== $currentSampleAmount) {
    // Error: sample_amount harus sama
}

// Type harus sama
if ($sourceType !== $currentType) {
    // Error: type harus sama
}
```

**Auto-Process di checkSamples:**
1. Copy samples dari source (hanya values)
2. Process variables dari DERIVED config
3. Process pre-processing formulas dari DERIVED config
4. Evaluate dengan rule evaluation dari DERIVED config
5. Return hasil lengkap

---

## Test Results

### Expected Response Format

#### GET `/product-measurement/{id}/result`

```json
{
  "product_measurement_id": "MSR-U2BTVOHQ",
  "product_id": "PRD-O51YFGHY",
  "product_name": "COTO",
  "product_spec_name": "COTO TEST DETAIL RESULT",
  "status": "NG",  // ✅ FIXED: Was "NEED_TO_MEASURE", now correctly "NG"
  "batch_number": "detail",
  "progress": 100,
  "due_date": "2026-01-30T17:00:00.000000Z",
  "summary": {
    "total_measurement_items": 2,  // ✅ NEW
    "max_sample_count": 5,         // ✅ RENAMED from "sample"
    "total_samples": 8,            // ✅ NEW
    "ok": 7,
    "ng": 1,
    "ng_ratio": 12.5
  },
  "measurement_point_results": [
    {
      "name": "sample 3",
      "name_id": "sample_3",
      "unit": "mm",
      "summary": {
        "sample_amount": 3,
        "ok": 3,
        "ng": 0,
        "ng_ratio": 0,
        "maximum_value": 3,
        "minimum_value": 1,
        "sigma": 1,
        "sigma_3": 3,
        "sigma_6": 6,
        "cp": null,
        "cpk": 2.67
      },
      "samples": [
        {
          "sample_index": 1,
          "status": "ok",
          "single_value": 3,
          "measurement_time": "2026-01-13T00:33:23.361"  // ✅ Preserved
        },
        // ... other samples
      ]
    }
  ],
  "finished_at": "2026-01-12T17:36:52.000000Z",
  "created_at": "2026-01-12T17:33:06.000000Z",
  "updated_at": "2026-01-12T17:36:52.000000Z"
}
```

---

## Breaking Changes

### Summary Field Changes

**Old Response:**
```json
{
  "summary": {
    "sample": 5,  // Ambiguous
    "ok": 7,
    "ng": 1,
    "ng_ratio": 12.5
  }
}
```

**New Response:**
```json
{
  "summary": {
    "total_measurement_items": 2,  // ✅ NEW: Clear count of measurement items
    "max_sample_count": 5,         // ✅ RENAMED: Max sample count
    "total_samples": 8,            // ✅ NEW: Total samples evaluated
    "ok": 7,
    "ng": 1,
    "ng_ratio": 12.5
  }
}
```

**Migration for Frontend:**
```dart
// Old
int sampleCount = response['summary']['sample'];

// New (choose appropriate field)
int totalItems = response['summary']['total_measurement_items'];  // Count of measurement items
int maxSamples = response['summary']['max_sample_count'];        // Max sample count
int totalSamples = response['summary']['total_samples'];         // Total samples
```

---

## Recommendations

### 1. For Quarter Issue

Always provide `due_date` when creating measurement:
```json
{
  "product_ids": ["PRD-XXX"],
  "due_date": "2026-04-30",  // ✅ Required - ensures quarter can be calculated
  "measurement_type": "FULL_MEASUREMENT"
}
```

### 2. For Status Display

Use correct status mapping in UI:
- `"OK"` → Green badge, "Semua sample OK"
- `"NG"` → Red badge, "Ada sample yang NG"
- `"NEED_TO_MEASURE"` → Yellow badge, "Perlu pengukuran ulang"
- `"ONGOING"` → Blue badge, "Sedang dalam proses"
- `"TODO"` → Gray badge, "Belum dimulai"

### 3. For Summary Display

Show all three metrics for clarity:
- Total Measurement Items: 2 items
- Max Sample Count: 5 samples
- Total Samples Evaluated: 8 samples
- OK: 7 samples (87.5%)
- NG: 1 sample (12.5%)

---

### 6. ✅ NEW: Dashboard Endpoints

**New Features:**
Menambahkan 2 endpoint baru untuk dashboard/homepage:

**a. Overdue Calibration Tools**
- Endpoint: `GET /api/v1/tools/overdue-calibration`
- Query param: `date` (required)
- Returns: List of tools yang `next_calibration_at < date`
- Hanya tools ACTIVE dengan next_calibration_at yang terisi

**b. Overdue Issues**
- Endpoint: `GET /api/v1/issue-tracking/overdue`
- Query param: `date` (required)
- Returns: List of issues yang `due_date < date` AND `status IN (PENDING, ON_GOING)`
- Exclude issues dengan status SOLVED

**Response Format:**
```json
// Tools Overdue
{
  "data": [
    {
      "id": 1,
      "toolName": "Micrometer Digital",
      "toolModel": "MDC-250",
      "toolType": "MECHANICAL",
      "lastCalibration": "2025-06-15T00:00:00.000000Z",
      "nextCalibration": "2025-12-15T00:00:00.000000Z",
      "imei": "IMEI-001-MDC250",
      "status": "ACTIVE"
    }
  ]
}

// Issues Overdue
{
  "data": [
    {
      "id": 1,
      "title": "Equipment malfunction",
      "description": "Production line A showing error",
      "status": "PENDING",
      "reportedBy": "john_doe",
      "dueDate": "2026-01-10T00:00:00.000000Z",
      "commentCount": 3
    }
  ]
}
```

**Documentation:** See `DASHBOARD_ENDPOINTS.md` for complete API documentation.

---

## Files Modified

```
app/Http/Controllers/Api/V1/
├── ProductController.php
│   └── Added DERIVED source validation (sample_amount & type match)
├── ProductMeasurementController.php
│   ├── Fixed status determination for COMPLETED measurements
│   ├── Enhanced summary with total_measurement_items, max_sample_count, total_samples
│   ├── Fixed getByProduct quarter detection (use measurement.due_date)
│   └── Enhanced DERIVED checkSamples with auto-copy and auto-process
├── ToolController.php
│   └── Added getOverdueCalibration() method
└── IssueController.php
    └── Added getOverdue() method

routes/
└── api.php
    ├── Added GET /tools/overdue-calibration
    └── Added GET /issue-tracking/overdue
```

---

## Testing

### Test Case: COMPLETED Measurement with NG

**Expected:**
- `status: "NG"` (not "NEED_TO_MEASURE")
- `summary.total_measurement_items: 2`
- `summary.total_samples: 8`
- `measurement_time` preserved in all samples

**Verify:**
```bash
GET /api/v1/product-measurement/MSR-U2BTVOHQ/result
```

---

**Last Updated:** 2026-01-13
**Status:** ✅ Ready for Testing
