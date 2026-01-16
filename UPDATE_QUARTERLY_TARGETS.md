# Update: Auto-Create Quarterly Targets & Summary Logic Fix

**Tanggal:** 16 Januari 2026

## Ringkasan Perubahan

Implementasi fitur auto-create quarterly targets dan perbaikan logic summary di result endpoint.

---

## 1. Auto-Create Quarterly Targets

### Deskripsi
Sistem secara otomatis membuat target untuk quarter berikutnya ketika admin membuat target di quarter tertentu. Prinsipnya adalah target di quarter awal sebagai referensi utama, ditambah 3 bulan untuk setiap quarter berikutnya.

### Logic
- **Q1 → Auto-create Q2, Q3, Q4**: Jika target dibuat di Q1 (Januari-Maret), sistem otomatis membuat target untuk Q2 (April), Q3 (Juli), dan Q4 (Oktober) dengan tanggal yang sama (ditambah 3 bulan per quarter)
- **Q2 → Auto-create Q3, Q4**: Jika target dibuat di Q2, sistem otomatis membuat target untuk Q3 dan Q4
- **Q3 → Auto-create Q4**: Jika target dibuat di Q3, sistem otomatis membuat target untuk Q4
- **Q4 → Hanya Q4**: Jika target dibuat di Q4, hanya target Q4 yang dibuat

### Contoh
Admin membuat target untuk produk "Civuvas 7.5 G" di Q1 pada tanggal **15 Januari 2026**, maka sistem akan otomatis membuat target:
- **Q1**: 15 Januari 2026 (original)
- **Q2**: 15 April 2026 (Jan + 3 bulan)
- **Q3**: 15 Juli 2026 (Jan + 6 bulan)
- **Q4**: 15 Oktober 2026 (Jan + 9 bulan)

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
- **Methods**: 
  - `store()` - Single product target creation
  - `bulkStore()` - Bulk product targets creation
  - `createQuarterlyTargets()` - Helper method untuk auto-create (NEW)

### Ketentuan
- ✅ Hanya berlaku untuk **FULL_MEASUREMENT** (tidak untuk SCALE_MEASUREMENT)
- ✅ Jika target untuk quarter tertentu sudah ada, akan di-skip (tidak overwrite)
- ✅ Berlaku untuk single create (`store()`) dan bulk create (`bulkStore()`)

### Code Implementation

#### Method Helper: `createQuarterlyTargets()`
```php
/**
 * Auto-create quarterly targets untuk quarter berikutnya
 * Logic: Jika buat target di Q1, auto-create Q2, Q3, Q4
 * Jika buat di Q2, auto-create Q3, Q4
 * Jika buat di Q3, auto-create Q4
 * Jika buat di Q4, hanya Q4 saja
 */
private function createQuarterlyTargets(
    Product $product, 
    string $originalDueDate, 
    string $measurementType, 
    $user, 
    ?string $batchNumber = null, 
    ?string $machineNumber = null, 
    ?int $sampleCount = null, 
    ?string $notes = null
): void
{
    // Parse original due date
    $originalDate = \Carbon\Carbon::parse($originalDueDate);
    $year = $originalDate->year;
    $month = $originalDate->month;
    $day = $originalDate->day;

    // Determine current quarter dari month
    $currentQuarter = null;
    if ($month >= 1 && $month <= 3) {
        $currentQuarter = 1; // Q1
    } elseif ($month >= 4 && $month <= 6) {
        $currentQuarter = 2; // Q2
    } elseif ($month >= 7 && $month <= 9) {
        $currentQuarter = 3; // Q3
    } else {
        $currentQuarter = 4; // Q4
    }

    // Determine quarters to create (next quarters only)
    $quartersToCreate = [];
    if ($currentQuarter === 1) {
        $quartersToCreate = [2, 3, 4]; // Q2, Q3, Q4
    } elseif ($currentQuarter === 2) {
        $quartersToCreate = [3, 4]; // Q3, Q4
    } elseif ($currentQuarter === 3) {
        $quartersToCreate = [4]; // Q4
    }
    // If Q4, no quarters to create

    // Get sample count from product if not provided
    if ($sampleCount === null) {
        $measurementPoints = $product->measurement_points ?? [];
        $sampleCount = count($measurementPoints) > 0 ? ($measurementPoints[0]['setup']['sample_amount'] ?? 3) : 3;
    }

    // Create targets for each quarter
    foreach ($quartersToCreate as $targetQuarter) {
        // Calculate target date: original date + (targetQuarter - currentQuarter) * 3 months
        $monthsToAdd = ($targetQuarter - $currentQuarter) * 3;
        $targetDate = $originalDate->copy()->addMonths($monthsToAdd);

        // Check if target already exists for this quarter (skip if exists)
        $quarterRange = $this->getQuarterRangeFromQuarterNumber($targetQuarter, $targetDate->year);
        $existingMeasurement = ProductMeasurement::where('product_id', $product->id)
            ->where('measurement_type', $measurementType)
            ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
            ->first();

        // Skip if target already exists
        if ($existingMeasurement) {
            continue;
        }

        // Create new target
        ProductMeasurement::create([
            'product_id' => $product->id,
            'batch_number' => $batchNumber, // Can be null for bulk create
            'machine_number' => $machineNumber,
            'sample_count' => $sampleCount,
            'measurement_type' => $measurementType,
            'status' => 'TODO',
            'sample_status' => 'NOT_COMPLETE',
            'measured_by' => $user->id,
            'due_date' => $targetDate->format('Y-m-d H:i:s'),
            'measured_at' => null,
            'notes' => $notes,
        ]);
    }
}
```

#### Integration di `store()` method
```php
// Single product flow
$measurement = ProductMeasurement::create([...]);

// ✅ NEW: Auto-create quarterly targets untuk FULL_MEASUREMENT
if ($request->measurement_type === 'FULL_MEASUREMENT') {
    $this->createQuarterlyTargets(
        $product, 
        $request->due_date, 
        $request->measurement_type, 
        $user, 
        $batchNumber, 
        $request->machine_number, 
        $sampleCount, 
        $request->notes
    );
}
```

#### Integration di `bulkStore()` method
```php
foreach ($products as $product) {
    $measurement = ProductMeasurement::create([...]);
    
    // ✅ NEW: Auto-create quarterly targets untuk FULL_MEASUREMENT
    if ($request->measurement_type === 'FULL_MEASUREMENT') {
        $this->createQuarterlyTargets(
            $product, 
            $request->due_date, 
            $request->measurement_type, 
            $user, 
            null, // batch_number null untuk bulk
            null, // machine_number null untuk bulk
            ($product->measurement_points[0]['setup']['sample_amount'] ?? 3), 
            null  // notes null untuk bulk
        );
    }
}
```

---

## 2. Fix Logic Summary di Result Endpoint

### Deskripsi
Perbaikan logic perhitungan summary di endpoint `GET /api/v1/product-measurement/:id/result`. Summary sekarang diambil dari measurement item dengan sample terbanyak, bukan dari agregasi semua measurement items.

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
- **Method**: `getResult()`

### Perubahan Logic

**Sebelum:**
- `total_samples` = jumlah total semua samples dari semua measurement items
- `ok` = jumlah total OK dari semua measurement items
- `ng` = jumlah total NG dari semua measurement items

**Sesudah:**
- `total_samples` = jumlah sample dari measurement item dengan sample terbanyak (sebut saja item A)
- `ok` = jumlah OK dari item A
- `ng` = jumlah NG dari item A

### Contoh
Dengan data measurement results:
```json
[
  {
    "measurement_item_name": "thickness",
    "sample": 5,
    "ok": 3,
    "ng": 2
  },
  {
    "measurement_item_name": "room_temp",
    "sample": 10,
    "ok": 7,
    "ng": 3
  },
  {
    "measurement_item_name": "border_width",
    "sample": 7,
    "ok": 3,
    "ng": 4
  }
]
```

**Hasil Summary:**
```json
{
  "total_measurement_items": 3,
  "max_sample_count": 10,
  "total_samples": 10,  // ✅ Dari room_temp (sample terbanyak)
  "ok": 7,              // ✅ OK dari room_temp
  "ng": 3,              // ✅ NG dari room_temp
  "ng_ratio": 30.0
}
```

### Code Implementation
```php
// ✅ FIX: Calculate overall summary - ambil dari measurement item dengan sample terbanyak
$maxSampleCount = 0;
$totalMeasurementItems = count($measurementPointResults);
$itemWithMaxSamples = null;

// Find measurement item dengan sample terbanyak
foreach ($measurementPointResults as $pointResult) {
    $sampleAmount = $pointResult['summary']['sample_amount'] ?? 0;
    
    // Track max sample count
    if ($sampleAmount > $maxSampleCount) {
        $maxSampleCount = $sampleAmount;
        $itemWithMaxSamples = $pointResult;
    }
}

// Jika ada item dengan max samples, ambil ok/ng dari item tersebut
$totalSamples = $maxSampleCount;
$summaryOk = 0;
$summaryNg = 0;

if ($itemWithMaxSamples) {
    $summaryOk = $itemWithMaxSamples['summary']['ok'] ?? 0;
    $summaryNg = $itemWithMaxSamples['summary']['ng'] ?? 0;
}

$summary = [
    'total_measurement_items' => $totalMeasurementItems,
    'max_sample_count' => $maxSampleCount,
    'total_samples' => $totalSamples, // ✅ FIX: Diambil dari measurement item dengan sample terbanyak
    'ok' => $summaryOk,                 // ✅ FIX: OK dari measurement item dengan sample terbanyak
    'ng' => $summaryNg,                 // ✅ FIX: NG dari measurement item dengan sample terbanyak
    'ng_ratio' => $totalSamples > 0 ? ($summaryNg / $totalSamples) * 100 : 0,
];
```

---

## Testing

### Test Cases untuk Auto-Create Quarterly Targets

1. **Test Q1 → Q2, Q3, Q4**
   - Create target di Q1 (15 Januari 2026)
   - Verify: Target Q2 (15 April), Q3 (15 Juli), Q4 (15 Oktober) terbuat

2. **Test Q2 → Q3, Q4**
   - Create target di Q2 (15 April 2026)
   - Verify: Target Q3 (15 Juli), Q4 (15 Oktober) terbuat

3. **Test Q3 → Q4**
   - Create target di Q3 (15 Juli 2026)
   - Verify: Target Q4 (15 Oktober) terbuat

4. **Test Q4 → No auto-create**
   - Create target di Q4 (15 Oktober 2026)
   - Verify: Hanya target Q4 yang terbuat

5. **Test Skip Existing Target**
   - Create target di Q1
   - Create target di Q1 lagi (duplicate)
   - Verify: Target Q2, Q3, Q4 tidak dibuat ulang (skip)

6. **Test SCALE_MEASUREMENT**
   - Create SCALE_MEASUREMENT target
   - Verify: Tidak ada auto-create (hanya untuk FULL_MEASUREMENT)

7. **Test Bulk Create**
   - Create bulk targets untuk multiple products
   - Verify: Auto-create berlaku untuk semua products

### Test Cases untuk Summary Logic

1. **Test Summary dari Item dengan Max Samples**
   - Create measurement dengan multiple items (berbeda sample count)
   - Get result
   - Verify: Summary diambil dari item dengan sample terbanyak

2. **Test Multiple Items dengan Same Max Samples**
   - Create measurement dengan multiple items yang memiliki sample count sama (max)
   - Get result
   - Verify: Summary diambil dari item pertama yang ditemukan dengan max samples

---

## API Endpoints

### Tidak Ada Perubahan Endpoint
Semua perubahan dilakukan di endpoint yang sudah ada:
- `POST /api/v1/product-measurement` - Auto-create quarterly targets
- `POST /api/v1/product-measurement/bulk` - Auto-create quarterly targets (bulk)
- `GET /api/v1/product-measurement/{id}/result` - Summary logic fix

---

## Migration Notes

### Tidak Ada Migration Database
Semua perubahan adalah logic changes, tidak ada perubahan struktur database.

---

## Breaking Changes

### Tidak Ada Breaking Changes
Semua perubahan backward compatible:
- Auto-create quarterly targets adalah fitur baru (tidak mengubah behavior existing)
- Summary logic fix adalah perbaikan (response structure tetap sama, hanya logic perhitungan yang berubah)

---

## Files Modified

1. `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
   - Added: `createQuarterlyTargets()` method
   - Modified: `store()` method
   - Modified: `bulkStore()` method
   - Modified: `getResult()` method (summary logic)

---

## Notes

- Auto-create quarterly targets hanya berlaku untuk **FULL_MEASUREMENT**
- Jika target untuk quarter tertentu sudah ada, akan di-skip (tidak overwrite)
- Summary diambil dari measurement item dengan sample terbanyak, bukan agregasi semua items
- Tanggal target di quarter berikutnya dihitung dengan menambahkan 3 bulan per quarter dari tanggal original
