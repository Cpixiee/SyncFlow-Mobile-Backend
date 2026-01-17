# Update 17 Januari 2026 - Reports Endpoints

## Ringkasan Perubahan

Dokumen ini menjelaskan perubahan yang dilakukan pada endpoint Reports pada tanggal 17 Januari 2026. Terdapat 2 perubahan utama yang dilakukan.

---

## 1. Update Endpoint `/reports/filters/products` - Tambah Pagination & Ubah Parameter

### Perubahan
Endpoint `/reports/filters/products` sekarang mendukung pagination dan menggunakan parameter baru untuk search dan filter.

### Endpoint Terpengaruh
- **GET** `/api/v1/reports/filters/products`

### Parameter Baru
- `keyword` (optional): Search berdasarkan nama produk (menggantikan `query`)
- `category` (optional): Filter berdasarkan ID kategori produk (menggantikan `category_id`)
- `page` (optional): Nomor halaman untuk pagination (default: 1)
- `limit` (optional): Jumlah item per halaman (default: 10, max: 100)

### Parameter Existing
- `quarter` (required): Quarter number (1-4)
- `year` (required): Tahun

### Response Structure
Response sekarang menggunakan format pagination:

```json
{
  "http_code": 200,
  "message": "Products retrieved successfully",
  "error_id": null,
  "data": [
    {
      "product_id": "PRD-XXXXX",
      "product_name": "CIVUSAS-S",
      "product_spec_name": "CIVUSAS-S",
      "product_category": "Wire Test Reguler"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_page": 5,
    "limit": 10,
    "total_docs": 50
  }
}
```

### Catatan Penting
- Parameter `keyword` hanya mencari di field `product_name` (bukan di semua field seperti sebelumnya)
- Parameter `category` menggunakan nama yang lebih singkat (menggantikan `category_id`)
- Response format berubah dari array langsung menjadi format pagination
- **Breaking Change:** Response structure berubah, pastikan frontend menyesuaikan

### Contoh Request & Response

**Request:**
```
GET /api/v1/reports/filters/products?quarter=1&year=2026&keyword=CIVUS&category=1&page=1&limit=10
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Products retrieved successfully",
  "error_id": null,
  "data": [
    {
      "product_id": "PRD-MX5C2OCE",
      "product_name": "CIVUS",
      "product_spec_name": "CIVUS 0.75 G Alternative",
      "product_category": "Wire Test Reguler"
    },
    {
      "product_id": "PRD-XXXXX",
      "product_name": "CIVUSAS-S",
      "product_spec_name": "CIVUSAS-S",
      "product_category": "Wire Test Reguler"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_page": 1,
    "limit": 10,
    "total_docs": 2
  }
}
```

**Request (tanpa filter - backward compatible untuk quarter dan year):**
```
GET /api/v1/reports/filters/products?quarter=1&year=2026&page=1&limit=10
```

**Response:** Format sama, menampilkan semua products yang punya measurement di quarter tersebut dengan pagination

---

## 2. Tambah GET Endpoint `/reports/upload-master` - Get Template

### Perubahan
Endpoint baru untuk mendapatkan informasi template master file yang sudah pernah di-upload sebelumnya. Template yang sudah di-upload disimpan dan bisa digunakan kembali tanpa perlu upload ulang.

### Endpoint Baru
- **GET** `/api/v1/reports/upload-master`

### Behavior
- **Upload report baru** → Disimpan sebagai template di database
- **Upload berikutnya** → User bisa reuse template lama dengan melihat informasi template yang sudah ada
- **Frontend Experience:**
  - Jika template sudah ada: Tampilkan nama file template (contoh: "Master Template Civuvas 7.5G.xlsx")
  - Button "Pilih File" diganti menjadi "Ganti Template File"
  - User bisa langsung menggunakan template yang ada atau upload template baru

### Parameter
- `quarter` (required): Quarter number (1-4)
- `year` (required): Tahun
- `product_id` (required): ID produk
- `batch_number` (required): Nomor batch

### Response Structure

**Jika template ada:**
```json
{
  "http_code": 200,
  "message": "Template retrieved successfully",
  "error_id": null,
  "data": {
    "has_template": true,
    "template": {
      "master_file_id": 1,
      "product_measurement_id": 123,
      "measurement_id": "MEAS-001",
      "batch_number": "BATCH-002",
      "original_filename": "Master Template Civuvas 7.5G.xlsx",
      "stored_filename": "master_1234567890_abc123.xlsx",
      "file_path": "reports/master_files/master_1234567890_abc123.xlsx",
      "sheet_names": ["raw_data", "summary", "charts"],
      "total_sheets": 3,
      "has_raw_data_sheet": true,
      "uploaded_by": "admin",
      "uploaded_at": "2026-01-15 10:30:00"
    }
  }
}
```

**Jika template tidak ada:**
```json
{
  "http_code": 200,
  "message": "No template found for this measurement",
  "error_id": null,
  "data": {
    "has_template": false,
    "template": null
  }
}
```

### Contoh Request & Response

**Request:**
```
GET /api/v1/reports/upload-master?quarter=1&year=2026&product_id=PRD-MX5C2OCE&batch_number=BATCH-002
Headers: Authorization: Bearer {token}
```

**Response (Template ada):**
```json
{
  "http_code": 200,
  "message": "Template retrieved successfully",
  "error_id": null,
  "data": {
    "has_template": true,
    "template": {
      "master_file_id": 1,
      "product_measurement_id": 123,
      "measurement_id": "MEAS-001",
      "batch_number": "BATCH-002",
      "original_filename": "Master Template Civuvas 7.5G.xlsx",
      "stored_filename": "master_1705123456_xyz789.xlsx",
      "file_path": "reports/master_files/master_1705123456_xyz789.xlsx",
      "sheet_names": ["raw_data", "summary"],
      "total_sheets": 2,
      "has_raw_data_sheet": true,
      "uploaded_by": "admin",
      "uploaded_at": "2026-01-15 10:30:00"
    }
  }
}
```

**Response (Template tidak ada):**
```json
{
  "http_code": 200,
  "message": "No template found for this measurement",
  "error_id": null,
  "data": {
    "has_template": false,
    "template": null
  }
}
```

### Catatan Penting
- Endpoint ini digunakan untuk check apakah template sudah ada sebelum menampilkan form upload
- Frontend bisa menggunakan field `has_template` untuk menentukan UI yang ditampilkan
- Field `original_filename` digunakan untuk menampilkan nama file di UI
- Template disimpan per measurement (berdasarkan `product_measurement_id`)
- Jika user upload template baru, template lama akan di-replace (behavior existing tetap sama)

### Frontend Integration

**Flow yang disarankan:**
1. User memilih quarter, year, product_id, dan batch_number
2. Frontend call GET `/api/v1/reports/upload-master` untuk check template
3. Jika `has_template = true`:
   - Tampilkan nama file dari `template.original_filename` (contoh: "Master Template Civuvas 7.5G.xlsx")
   - Tampilkan button "Ganti Template File" (bukan "Pilih File")
   - User bisa langsung menggunakan template atau upload template baru
4. Jika `has_template = false`:
   - Tampilkan "Belum ada file dipilih"
   - Tampilkan button "Pilih File"
   - User upload template baru

---

## Endpoint POST `/reports/upload-master` (Existing)

### Behavior (Tidak Berubah)
- Upload template baru akan menyimpan template di database
- Jika template sudah ada untuk measurement yang sama, template lama akan di-replace
- Template yang di-upload bisa digunakan kembali di kemudian hari

### Catatan
- Behavior upload tetap sama seperti sebelumnya
- Template yang di-upload otomatis tersimpan dan bisa diakses via GET endpoint

---

## Breaking Changes

### 1. `/reports/filters/products`
- **Response format berubah:** Dari array langsung menjadi format pagination
- **Parameter berubah:** 
  - `query` → `keyword` (dan hanya search di `product_name`)
  - `category_id` → `category`
- **Parameter baru:** `page` dan `limit` untuk pagination

**Migration Guide:**
- Update frontend untuk menggunakan parameter baru (`keyword`, `category`, `page`, `limit`)
- Update response handling untuk menggunakan format pagination
- Response data sekarang ada di `data` array, bukan langsung di root

### 2. `/reports/upload-master`
- **Tidak ada breaking changes:** Endpoint GET adalah endpoint baru, tidak mengubah behavior POST yang sudah ada

---

## Testing Recommendations

### 1. Testing `/reports/filters/products` dengan Pagination
- Test dengan parameter `page` dan `limit`
- Test dengan `keyword` untuk search nama produk
- Test dengan `category` untuk filter kategori
- Test kombinasi `keyword` dan `category`
- Verifikasi response format pagination benar
- Test edge cases: page yang tidak ada, limit maksimal, dll

### 2. Testing GET `/reports/upload-master`
- Test dengan measurement yang sudah punya template
- Test dengan measurement yang belum punya template
- Verifikasi response `has_template` sesuai kondisi
- Verifikasi semua field di response template lengkap
- Test dengan parameter yang invalid (product tidak ada, measurement tidak ada, dll)

### 3. Testing Flow Upload Template
- Upload template baru → Verifikasi template tersimpan
- Get template setelah upload → Verifikasi template bisa diambil
- Upload template baru untuk measurement yang sama → Verifikasi template lama di-replace
- Get template setelah replace → Verifikasi template baru yang muncul

---

## Summary

1. ✅ **Updated:** `/reports/filters/products` - Tambah pagination, ubah parameter ke `keyword` dan `category`
2. ✅ **Added:** GET `/reports/upload-master` - Endpoint baru untuk get template yang sudah ada
3. ✅ **Behavior:** Template yang di-upload disimpan dan bisa digunakan kembali

Semua perubahan telah diimplementasikan dan siap untuk testing.
