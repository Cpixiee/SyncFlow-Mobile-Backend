# Scale Measurement API

## 📖 Apa itu Scale Measurement?

Scale Measurement adalah fitur untuk **mencatat berat produk per hari**:

- 📊 **Input data berat** produk
- 📅 **Limit per hari**: 1 product hanya bisa 1 measurement per hari
- ✅ **Status otomatis**: 
  - `NOT_CHECKED` → Belum ada weight (weight = null)
  - `CHECKED` → Sudah ada weight (weight ≠ null)
- 🚫 **Tidak ada judgement OK/NG** (beda dengan Product Measurement)

---

## 👥 User yang Diizinkan

### ✅ Operator BISA:
- **View** list measurements
- **View** single measurement
- **View** available products
- **Create** measurement baru
- **Bulk create** (create banyak sekaligus)

### ✅ Admin & SuperAdmin BISA:
- Semua yang Operator bisa, **plus:**
- **Update/Edit** measurement
- **Delete** measurement

### 🔒 Access Control Matrix

| Aksi | Operator | Admin | SuperAdmin |
|------|----------|-------|------------|
| View List | ✅ | ✅ | ✅ |
| View Single | ✅ | ✅ | ✅ |
| View Available Products | ✅ | ✅ | ✅ |
| **Create** | ✅ | ✅ | ✅ |
| **Bulk Create** | ✅ | ✅ | ✅ |
| Update/Edit | ❌ | ✅ | ✅ |
| Delete | ❌ | ✅ | ✅ |

---

## 🚀 Cara Pakai

### Step 1: Login

**Operator Login:**
```
POST http://localhost:8000/api/v1/login

Body:
{
  "username": "operator1",
  "password": "password"
}

Response:
{
  "http_code": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "username": "operator1",
      "role": "operator"
    }
  }
}
```

💾 **Simpan token untuk request selanjutnya!**

---

### Step 2: Lihat Products yang Tersedia

Cek products yang belum ada measurement hari ini.

**Request:**
```
GET http://localhost:8000/api/v1/scale-measurement/available-products?date=2025-12-02
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Available products retrieved successfully",
  "data": [
    {
      "id": "PRD-A1B2C3D4",
      "product_name": "CIVIUSAS-S",
      "product_category_name": "Wire Test Regular",
      "ref_spec_number": "YPES-11-03-009",
      "article_code": "ART-001"
    },
    {
      "id": "PRD-B2C3D4E5",
      "product_name": "DMGAS-6",
      "product_category_name": "Wire Test Regular",
      "article_code": "ART-002"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_docs": 2
  }
}
```

---

### Step 3: Create Measurement (Operator BISA!)

#### Option A: Create Single

**Request:**
```
POST http://localhost:8000/api/v1/scale-measurement
Authorization: Bearer {token}

Body:
{
  "product_id": "PRD-A1B2C3D4",
  "measurement_date": "2025-12-02",
  "weight": 4.5,
  "notes": "Pengukuran pagi"
}
```

**Response:**
```json
{
  "http_code": 201,
  "message": "Scale measurement created successfully",
  "data": {
    "scale_measurement_id": "SCL-X1Y2Z3A4",
    "measurement_date": "2025-12-02",
    "weight": 4.5,
    "status": "CHECKED"
  }
}
```

---

#### Option B: Bulk Create (Operator BISA!)

Buat measurement untuk banyak products sekaligus **tanpa weight** dulu.

**Request:**
```
POST http://localhost:8000/api/v1/scale-measurement/bulk
Authorization: Bearer {token}

Body:
{
  "product_ids": [
    "PRD-A1B2C3D4",
    "PRD-B2C3D4E5",
    "PRD-F6G7H8I9"
  ],
  "measurement_date": "2025-12-02"
}
```

**Response:**
```json
{
  "http_code": 201,
  "message": "Bulk scale measurements created successfully",
  "data": {
    "PRD-A1B2C3D4": "SCL-X1Y2Z3A4",
    "PRD-B2C3D4E5": "SCL-Y2Z3A4B5",
    "PRD-F6G7H8I9": "SCL-Z3A4B5C6"
  }
}
```

**Catatan:** Semua dibuat dengan status `NOT_CHECKED` (weight = null)

---

### Step 4: Lihat List Measurements

**Request - Semua data hari ini:**
```
GET http://localhost:8000/api/v1/scale-measurement?date=2025-12-02
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Scale measurements retrieved successfully",
  "data": [
    {
      "scale_measurement_id": "SCL-X1Y2Z3A4",
      "measurement_date": "2025-12-02",
      "weight": null,
      "status": "NOT_CHECKED",
      "notes": null,
      "product": {
        "id": "PRD-A1B2C3D4",
        "product_name": "CIVIUSAS-S",
        "product_category_name": "Wire Test Regular",
        "article_code": "ART-001"
      },
      "measured_by": {
        "username": "operator1",
        "employee_id": "EMP001"
      },
      "created_at": "2025-12-02 10:30:00",
      "updated_at": "2025-12-02 10:30:00"
    },
    {
      "scale_measurement_id": "SCL-Y2Z3A4B5",
      "measurement_date": "2025-12-02",
      "weight": 4.5,
      "status": "CHECKED",
      "notes": "Sudah diukur",
      "product": {
        "id": "PRD-B2C3D4E5",
        "product_name": "DMGAS-6",
        "product_category_name": "Wire Test Regular",
        "article_code": "ART-002"
      },
      "measured_by": {
        "username": "operator1",
        "employee_id": "EMP001"
      },
      "created_at": "2025-12-02 09:15:00",
      "updated_at": "2025-12-02 11:20:00"
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

---

**Request - Filter yang belum dicek:**
```
GET http://localhost:8000/api/v1/scale-measurement?date=2025-12-02&status=NOT_CHECKED
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Scale measurements retrieved successfully",
  "data": [
    {
      "scale_measurement_id": "SCL-X1Y2Z3A4",
      "weight": null,
      "status": "NOT_CHECKED",
      "product": {
        "product_name": "CIVIUSAS-S"
      }
    }
  ]
}
```

---

### Step 5: Update Weight (Admin/SuperAdmin Only)

❌ **Operator TIDAK BISA update/edit**

**Request:**
```
PUT http://localhost:8000/api/v1/scale-measurement/SCL-X1Y2Z3A4
Authorization: Bearer {admin_token}

Body:
{
  "weight": 5.2,
  "notes": "Updated measurement"
}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Scale measurement updated successfully",
  "data": {
    "scale_measurement_id": "SCL-X1Y2Z3A4",
    "measurement_date": "2025-12-02",
    "weight": 5.2,
    "status": "CHECKED"
  }
}
```

**Operator coba update:**
```json
{
  "http_code": 403,
  "message": "Forbidden"
}
```

---

### Step 6: Delete (Admin/SuperAdmin Only)

❌ **Operator TIDAK BISA delete**

**Request:**
```
DELETE http://localhost:8000/api/v1/scale-measurement/SCL-X1Y2Z3A4
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Scale measurement deleted successfully",
  "data": null
}
```

---

## 📋 API Endpoints Lengkap

### 1. Get List (All Users)

```
GET /api/v1/scale-measurement
```

**Query Parameters:**
- `date` - Filter by tanggal (YYYY-MM-DD)
- `start_date` - Filter range start
- `end_date` - Filter range end
- `status` - Filter by status (NOT_CHECKED / CHECKED)
- `product_category_id` - Filter by category
- `query` - Search by product name/code
- `page` - Halaman (default: 1)
- `limit` - Items per page (default: 10)

**Example:**
```
GET /api/v1/scale-measurement?date=2025-12-02&status=NOT_CHECKED&page=1&limit=10
```

---

### 2. Get Single (All Users)

```
GET /api/v1/scale-measurement/{scale_measurement_id}
```

**Example:**
```
GET /api/v1/scale-measurement/SCL-X1Y2Z3A4
```

---

### 3. Get Available Products (All Users)

```
GET /api/v1/scale-measurement/available-products?date=2025-12-02
```

**Returns:** Products yang belum punya measurement untuk tanggal tersebut

---

### 4. Create (All Users - Operator BISA)

```
POST /api/v1/scale-measurement
```

**Body:**
```json
{
  "product_id": "PRD-A1B2C3D4",
  "measurement_date": "2025-12-02",
  "weight": 4.5,
  "notes": "Optional notes"
}
```

**Validation:**
- `product_id` - required, harus exist
- `measurement_date` - required, format YYYY-MM-DD
- `weight` - optional, numeric, min: 0
- `notes` - optional, text

---

### 5. Bulk Create (All Users - Operator BISA)

```
POST /api/v1/scale-measurement/bulk
```

**Body:**
```json
{
  "product_ids": ["PRD-001", "PRD-002", "PRD-003"],
  "measurement_date": "2025-12-02"
}
```

**Notes:** 
- Semua dibuat dengan weight = null, status = NOT_CHECKED
- Duplicate otomatis di-skip

---

### 6. Update (Admin/SuperAdmin Only)

```
PUT /api/v1/scale-measurement/{scale_measurement_id}
```

**Body:**
```json
{
  "weight": 5.2,
  "notes": "Updated"
}
```

**Operator coba update → 403 Forbidden**

---

### 7. Delete (Admin/SuperAdmin Only)

```
DELETE /api/v1/scale-measurement/{scale_measurement_id}
```

**Operator coba delete → 403 Forbidden**

---

## 🎬 Workflow Lengkap

### Scenario: Daily Weight Tracking

**Pagi Hari - Operator buat target:**

```
1. Login sebagai Operator
2. Bulk create untuk 5 products hari ini
   POST /scale-measurement/bulk
   {
     "product_ids": ["PRD-001", "PRD-002", "PRD-003", "PRD-004", "PRD-005"],
     "measurement_date": "2025-12-02"
   }
   
   → 5 measurements dibuat dengan status NOT_CHECKED
```

---

**Siang Hari - Operator cek progress:**

```
3. Lihat yang belum dicek
   GET /scale-measurement?date=2025-12-02&status=NOT_CHECKED
   
   → Tampil 5 products yang belum diukur
```

---

**Sore Hari - Admin update weight:**

```
4. Admin update weight satu per satu
   PUT /scale-measurement/SCL-001
   { "weight": 4.5 }
   
   PUT /scale-measurement/SCL-002
   { "weight": 3.8 }
   
   → Status berubah jadi CHECKED
```

---

**Akhir Hari - Cek laporan:**

```
5. Lihat summary hari ini
   GET /scale-measurement?date=2025-12-02
   
   → Tampil semua (CHECKED dan NOT_CHECKED)
```

---

## 📊 Status Flow

```
Create tanpa weight        Create dengan weight
       ↓                          ↓
  NOT_CHECKED                  CHECKED
  (weight = null)          (weight = 4.5)
       ↓
  Admin update weight
       ↓
    CHECKED
```

**Contoh:**
1. Bulk create → Status: `NOT_CHECKED` (weight = null)
2. Admin update weight = 4.5 → Status: `CHECKED`
3. Admin update weight = null → Status: `NOT_CHECKED`

---

## ⚠️ Error Responses

### Duplicate Measurement (400)
```json
{
  "http_code": 400,
  "message": "Product ini sudah memiliki scale measurement untuk tanggal tersebut",
  "error_id": "DUPLICATE_SCALE_MEASUREMENT"
}
```

### Not Found (404)
```json
{
  "http_code": 404,
  "message": "Scale measurement tidak ditemukan",
  "error_id": "ERR_692EA96E201CB"
}
```

### Forbidden (403)
```json
{
  "http_code": 403,
  "message": "Forbidden"
}
```

Terjadi ketika:
- Operator coba **update** measurement
- Operator coba **delete** measurement

### Unauthorized (401)
```json
{
  "http_code": 401,
  "message": "Unauthorized"
}
```

Terjadi ketika:
- Token tidak ada
- Token invalid
- Token expired

### Validation Error (400)
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "data": {
    "product_id": ["The product id field is required."],
    "measurement_date": ["The measurement date field is required."]
  }
}
```

---

## 📌 Perbedaan dengan Product Measurement

| Feature | Scale Measurement | Product Measurement |
|---------|------------------|---------------------|
| **Limit** | 1 per **hari** | 1 per **quarter** (3 bulan) |
| **Data Input** | Berat saja | Samples + formulas + variables |
| **Status** | NOT_CHECKED / CHECKED | PENDING / IN_PROGRESS / COMPLETED |
| **Judgement** | ❌ Tidak ada | ✅ Ada OK/NG evaluation |
| **Filter** | Per tanggal | Per quarter |
| **Create** | ✅ Operator bisa | ✅ Operator bisa |
| **Update** | ❌ Operator tidak bisa | ✅ Operator bisa |
| **Delete** | ❌ Operator tidak bisa | ❌ Admin only |

---

## 💡 Tips Penggunaan

### 1. Gunakan Bulk Create untuk Efficiency
```
Pagi: Admin/Operator buat target dengan bulk create
      → Semua products dibuat dengan status NOT_CHECKED

Siang-Sore: Admin update weight satu per satu
      → Status berubah jadi CHECKED setelah input weight
```

---

### 2. Filter by Status untuk Monitoring
```
- status=NOT_CHECKED → Lihat yang belum diukur
- status=CHECKED → Lihat yang sudah diukur
- Tanpa filter → Lihat semua
```

---

### 3. Search untuk Cari Product
```
GET /scale-measurement?query=CIVIUSAS
→ Cari by product_name, product_id, atau article_code
```

---

### 4. Date Range untuk Reporting
```
GET /scale-measurement?start_date=2025-12-01&end_date=2025-12-07
→ Laporan mingguan
```

---

### 5. Permission Management
```
Operator:
  ✅ Bisa create measurement baru
  ✅ Bisa lihat semua data
  ❌ Tidak bisa edit data yang sudah ada
  ❌ Tidak bisa delete

Admin/SuperAdmin:
  ✅ Full access (CRUD)
```

---

## 🧪 Testing Results

```
✅ Tests:    1 skipped, 49 passed (105 assertions)
✅ Duration: 101.94s
✅ Success Rate: 98%
```

**Test Coverage:**
- ✅ CRUD operations (17 tests)
- ✅ Validation rules (4 tests)
- ✅ Access control (9 tests)
- ✅ Filters & search (5 tests)
- ✅ Status management (6 tests)
- ✅ Model methods (17 tests)

---

## 📂 File Structure

```
app/
├── Models/
│   └── ScaleMeasurement.php
└── Http/Controllers/Api/V1/
    └── ScaleMeasurementController.php

database/migrations/
└── 2025_12_02_151700_create_scale_measurements_table.php

routes/
└── api.php (scale-measurement routes)

tests/
├── Feature/
│   ├── ScaleMeasurementTest.php (24 tests)
│   └── ScaleMeasurementAuthTest.php (9 tests)
└── Unit/
    └── ScaleMeasurementModelTest.php (17 tests)
```

---

## 🗄️ Database Schema

```sql
scale_measurements
├── id (PK)
├── scale_measurement_id (UNIQUE) → SCL-XXXXXXXX
├── product_id (FK → products)
├── measurement_date (DATE)
├── weight (DECIMAL 10,2) NULL
├── status (ENUM: NOT_CHECKED, CHECKED)
├── measured_by (FK → login_users) NULL
├── notes (TEXT) NULL
├── created_at
└── updated_at

UNIQUE KEY: (product_id, measurement_date)
→ 1 product hanya bisa 1 measurement per hari
```

---

## ✅ Kesimpulan

**Scale Measurement** adalah fitur untuk tracking berat produk harian dengan:

1. **Input Simple**: Hanya berat dan tanggal
2. **Status Otomatis**: NOT_CHECKED/CHECKED berdasarkan weight
3. **Operator Friendly**: 
   - ✅ Bisa create (input data baru)
   - ❌ Tidak bisa edit/delete (untuk keamanan data)
4. **Admin Control**: Full CRUD untuk koreksi data
5. **Daily Limit**: 1 product per hari untuk prevent duplicate
6. **No Judgement**: Tidak ada evaluasi OK/NG, hanya record data

---

## 📞 Quick Reference

| Aksi | Endpoint | Method | Operator | Admin |
|------|----------|--------|----------|-------|
| Lihat list | `/scale-measurement` | GET | ✅ | ✅ |
| Lihat single | `/scale-measurement/{id}` | GET | ✅ | ✅ |
| Available products | `/scale-measurement/available-products` | GET | ✅ | ✅ |
| Create | `/scale-measurement` | POST | ✅ | ✅ |
| Bulk create | `/scale-measurement/bulk` | POST | ✅ | ✅ |
| Update | `/scale-measurement/{id}` | PUT | ❌ | ✅ |
| Delete | `/scale-measurement/{id}` | DELETE | ❌ | ✅ |

**Base URL:** `http://localhost:8000/api/v1`

---

**Scale Measurement siap digunakan!** 🚀

