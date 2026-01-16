# Update: Auto-Create Quarterly Targets & Related Improvements

**Tanggal:** 16 Januari 2026

## Ringkasan Perubahan

Implementasi fitur auto-create quarterly targets, cascade delete untuk product, delete related data sebelum update, dan perbaikan logic summary di result endpoint.

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

### Ketentuan
- ✅ Hanya berlaku untuk **FULL_MEASUREMENT** (tidak untuk SCALE_MEASUREMENT)
- ✅ Jika target untuk quarter tertentu sudah ada, akan di-skip (tidak overwrite)
- ✅ Berlaku untuk single create (`store()`) dan bulk create (`bulkStore()`)

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
- **Methods**: 
  - `store()` - Single product target creation
  - `bulkStore()` - Bulk product targets creation
  - `createQuarterlyTargets()` - Helper method untuk auto-create (NEW)

---

## 2. Cascade Delete untuk Product

### Deskripsi
Ketika product dihapus, semua data terkait (product measurements dan scale measurements) juga ikut terhapus secara otomatis.

### Perubahan
**Sebelum:**
- Product tidak bisa dihapus jika sudah memiliki measurement data
- Error message: "Product tidak dapat dihapus karena sudah memiliki measurement data"

**Sesudah:**
- Product bisa dihapus, dan semua data terkait ikut terhapus
- Cascade delete untuk:
  - `product_measurements` (dan turunannya)
  - `scale_measurements`

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductController.php`
- **Method**: `destroy()`

---

## 3. Delete Related Data Sebelum Update Product

### Deskripsi
Ketika user melakukan update product (terutama measurement_points), sistem akan terlebih dahulu menghapus data terkait untuk menghindari data yang broken akibat perubahan struktur measurement points.

### Perubahan
**Sebelum:**
- Update measurement_points langsung tanpa menghapus data terkait
- Berpotensi menyebabkan data broken jika struktur measurement points berubah

**Sesudah:**
- Sebelum update measurement_points, sistem akan menghapus:
  - `product_measurements` (dan turunannya)
  - `scale_measurements`
- Kemudian baru melakukan update measurement_points

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductController.php`
- **Method**: `update()`

---

## 4. Fix Logic Summary di Result Endpoint

### Deskripsi
Perbaikan logic perhitungan summary di endpoint `GET /api/v1/product-measurement/:id/result`. Summary sekarang diambil dari measurement item dengan sample terbanyak, bukan dari agregasi semua measurement items.

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

### Implementasi
- **File**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
- **Method**: `getResult()`

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

### Test Cases untuk Cascade Delete

1. **Test Delete Product dengan Measurements**
   - Create product dengan measurements
   - Delete product
   - Verify: Product dan semua measurements terhapus

### Test Cases untuk Update Product

1. **Test Update Measurement Points**
   - Create product dengan measurements
   - Update measurement_points
   - Verify: Old measurements terhapus, product terupdate

### Test Cases untuk Summary Logic

1. **Test Summary dari Item dengan Max Samples**
   - Create measurement dengan multiple items (berbeda sample count)
   - Get result
   - Verify: Summary diambil dari item dengan sample terbanyak

---

## API Endpoints

### Tidak Ada Perubahan Endpoint
Semua perubahan dilakukan di endpoint yang sudah ada:
- `POST /api/v1/product-measurement` - Auto-create quarterly targets
- `POST /api/v1/product-measurement/bulk` - Auto-create quarterly targets (bulk)
- `DELETE /api/v1/products/{productId}` - Cascade delete
- `PUT /api/v1/products/{productId}` - Delete related data sebelum update
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
- Cascade delete adalah improvement (sebelumnya product tidak bisa dihapus)
- Delete related data sebelum update adalah improvement (menghindari broken data)
- Summary logic fix adalah perbaikan (response structure tetap sama)

---

## Files Modified

1. `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
   - Added: `createQuarterlyTargets()` method
   - Modified: `store()` method
   - Modified: `bulkStore()` method
   - Modified: `getResult()` method (summary logic)

2. `app/Http/Controllers/Api/V1/ProductController.php`
   - Modified: `destroy()` method (cascade delete)
   - Modified: `update()` method (delete related data)

---

## Notes

- Auto-create quarterly targets hanya berlaku untuk **FULL_MEASUREMENT**
- Jika target untuk quarter tertentu sudah ada, akan di-skip (tidak overwrite)
- Cascade delete menghapus semua data terkait secara permanen
- Delete related data sebelum update menghindari broken data akibat perubahan struktur
- Summary diambil dari measurement item dengan sample terbanyak, bukan agregasi semua items
- Tanggal target di quarter berikutnya dihitung dengan menambahkan 3 bulan per quarter dari tanggal original
