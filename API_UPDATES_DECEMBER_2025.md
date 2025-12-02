# SyncFlow API Updates - December 2025

## 📋 Ringkasan Perbaikan

Dokumen ini berisi semua perbaikan dan penambahan endpoint API yang dilakukan pada December 2025.

### ✅ Daftar Perbaikan

1. ✅ Endpoint Baru: GET `/product-measurement/available-products`
2. ✅ Filter `product_category_id` di GET `/products`
3. ✅ Fix Filter Quarter di GET `/product-measurement`
4. ✅ Update Tools - Calibration Fields
5. ✅ Endpoint DELETE `/products/:id`
6. ✅ Endpoint UPDATE `/products/:id`
7. ✅ Endpoint `/products/is-product-exists` - Semua Field Basic Info

---

## 🆕 1. Endpoint Baru: Available Products untuk Monthly Target

### GET `/api/v1/product-measurement/available-products`

Endpoint ini digunakan untuk mendapatkan list products yang **belum punya measurement target** di quarter dan year yang dipilih. Sangat berguna untuk fitur "Create New Monthly Target".

**Query Parameters:**
- `quarter` (required): Integer 1-4
- `year` (required): Integer (2020-2100)
- `page` (optional): Integer, default = 1
- `limit` (optional): Integer, default = 10

**Request Example:**
```http
GET /api/v1/product-measurement/available-products?quarter=3&year=2024&page=1&limit=10
Authorization: Bearer {token}
```

**Response Example:**
```json
{
  "http_code": 200,
  "message": "Available products retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": "PRD-ABC123",
        "product_category_id": 1,
        "product_category_name": "TubeTest",
        "product_name": "COT",
        "ref_spec_number": "SPEC-001",
        "nom_size_vo": "12mm",
        "article_code": "ART-001",
        "no_document": "DOC-001",
        "no_doc_reference": "REF-001",
        "color": "#FF0000",
        "size": "Large"
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 5,
      "limit": 10,
      "total_docs": 45
    }
  }
}
```

**Catatan:**
- Response format sama dengan endpoint GET `/products`
- Hanya menampilkan products yang **belum punya** `product_measurement` dengan `measured_at` (due date) di quarter yang dipilih
- Quarter mapping:
  - Q1: Juni-Juli-Agustus (06-08)
  - Q2: September-Oktober-November (09-11)
  - Q3: Desember-Januari-Februari (12-02) *cross-year*
  - Q4: Maret-April-Mei (03-05)

---

## 🔍 2. Filter Product Category di GET `/products`

### GET `/api/v1/products`

Endpoint ini sekarang mendukung filter berdasarkan `product_category_id`.

**Query Parameters:**
- `page` (optional): Integer, default = 1
- `limit` (optional): Integer, default = 10
- `product_category_id` (optional): Integer - Filter by category

**Request Example:**
```http
GET /api/v1/products?product_category_id=1&page=1&limit=10
Authorization: Bearer {token}
```

**Response Example:**
```json
{
  "http_code": 200,
  "message": "Products retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": "PRD-ABC123",
        "product_category_id": 1,
        "product_category_name": "TubeTest",
        "product_name": "COT",
        "ref_spec_number": "SPEC-001",
        "nom_size_vo": "12mm",
        "article_code": "ART-001",
        "no_document": "DOC-001",
        "no_doc_reference": "REF-001",
        "color": "#FF0000",
        "size": "Large"
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 3,
      "limit": 10,
      "total_docs": 25
    }
  }
}
```

---

## 🔧 3. Fix Filter Quarter di GET `/product-measurement`

### GET `/api/v1/product-measurement`

Filter quarter sekarang **bekerja dengan benar**. Tambahan parameter `quarter` dan `year`.

**Query Parameters:**
- `page` (optional): Integer, default = 1
- `limit` (optional): Integer, default = 10
- `quarter` (optional): Integer 1-4
- `year` (optional): Integer (2020-2100)
- `status` (optional): String (TODO, ONGOING, NEED_TO_MEASURE, OK, NG, NOT_COMPLETE)
- `measurement_type` (optional): String (FULL_MEASUREMENT, SCALE_MEASUREMENT)
- `product_category_id` (optional): Integer
- `query` (optional): String - Search by product name, article code, etc.

**Request Example:**
```http
GET /api/v1/product-measurement?quarter=3&year=2024&status=ONGOING&page=1&limit=10
Authorization: Bearer {token}
```

**Response Example:**
```json
{
  "http_code": 200,
  "message": "Product measurements retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "product_measurement_id": "MSR-ABC123",
        "measurement_type": "FULL_MEASUREMENT",
        "status": "ONGOING",
        "sample_status": "NOT_COMPLETE",
        "batch_number": "BATCH-20241215-ABC123",
        "progress": 50.0,
        "due_date": "2024-12-31 00:00:00",
        "product": {
          "id": "PRD-ABC123",
          "product_category_id": 1,
          "product_category_name": "TubeTest",
          "product_name": "COT",
          "ref_spec_number": "SPEC-001",
          "nom_size_vo": "12mm",
          "article_code": "ART-001",
          "no_document": null,
          "no_doc_reference": null
        }
      }
    ],
    "metadata": {
      "current_page": 1,
      "total_page": 2,
      "limit": 10,
      "total_docs": 15
    }
  }
}
```

**Perbaikan:**
- Filter quarter sekarang menggunakan date range yang benar
- Data yang dikembalikan hanya yang `due_date` nya masuk ke quarter yang dipilih
- Implementasi method `getQuarterRangeFromQuarterNumber()` untuk mapping yang akurat

---

## 🛠️ 4. Update Tools - Calibration Fields

### Breaking Change ⚠️

Field calibration di tabel `tools` telah diubah:

**Field Lama:**
- `last_calibration` (date)
- `next_calibration` (date)

**Field Baru:**
- `last_calibration_at` (datetime, nullable)
- `next_calibration_at` (datetime, nullable)

**Migration File:** `database/migrations/2025_12_02_000001_update_tools_calibration_field_names.php`

**Response Example (semua endpoint tools):**
```json
{
  "http_code": 200,
  "message": "Tool retrieved successfully",
  "data": {
    "id": 1,
    "tool_name": "Digital Caliper",
    "tool_model": "DC-100",
    "tool_type": "MECHANICAL",
    "tool_type_description": "Mechanical Tool",
    "last_calibration_at": "2024-06-01",
    "next_calibration_at": "2025-06-01",
    "imei": "IME123456789",
    "status": "ACTIVE",
    "status_description": "Active",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-06-01T00:00:00.000000Z"
  }
}
```

**Catatan:**
- Kedua field tetap nullable
- Auto-update logic masih berfungsi: `next_calibration_at` otomatis di-set 1 tahun dari `last_calibration_at`
- **Frontend harus update** untuk menggunakan field names yang baru

**Jalankan Migration:**
```bash
php artisan migrate
```

---

## ❌ 5. Endpoint DELETE Product

### DELETE `/api/v1/products/{productId}`

Endpoint untuk menghapus product. **Hanya bisa delete jika product belum punya measurement data**.

**Path Parameters:**
- `productId` (required): String - Product ID

**Request Example:**
```http
DELETE /api/v1/products/PRD-ABC123
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Product berhasil dihapus",
  "error_id": null,
  "data": {
    "deleted": true
  }
}
```

**Error Response - Product Has Measurements (400):**
```json
{
  "http_code": 400,
  "message": "Product tidak dapat dihapus karena sudah memiliki measurement data",
  "error_id": "PRODUCT_HAS_MEASUREMENTS",
  "data": null
}
```

**Error Response - Not Found (404):**
```json
{
  "http_code": 404,
  "message": "Product tidak ditemukan",
  "error_id": "NOT_FOUND",
  "data": null
}
```

**Authorization:**
- Role: **admin** atau **superadmin**

---

## ✏️ 6. Endpoint UPDATE Product

### PUT `/api/v1/products/{productId}`

Endpoint untuk update product. Mendukung **partial update** (hanya field yang dikirim yang akan diupdate).

**Path Parameters:**
- `productId` (required): String - Product ID

**Request Body:**

Semua field di `basic_info`, `measurement_points`, dan `measurement_groups` bersifat **optional**. Hanya kirim field yang ingin diupdate.

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COT Updated",
    "ref_spec_number": "SPEC-002",
    "nom_size_vo": "15mm",
    "article_code": "ART-002",
    "no_document": "DOC-002",
    "no_doc_reference": "REF-002",
    "color": "#00FF00",
    "size": "Medium"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Temperature",
        "name_id": "temperature",
        "sample_amount": 3,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "°C",
        "value": 25,
        "tolerance_minus": 2,
        "tolerance_plus": 2
      }
    }
  ],
  "measurement_groups": [
    {
      "group_name": "Physical Properties",
      "measurement_items": ["temperature"],
      "order": 1
    }
  ]
}
```

**Request Example (Partial Update - Basic Info Only):**
```http
PUT /api/v1/products/PRD-ABC123
Authorization: Bearer {token}
Content-Type: application/json

{
  "basic_info": {
    "product_name": "COT - Updated Name",
    "color": "#FF5733"
  }
}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Product berhasil diupdate",
  "error_id": null,
  "data": {
    "product_id": "PRD-ABC123",
    "basic_info": {
      "product_category_id": 1,
      "product_name": "COT - Updated Name",
      "ref_spec_number": "SPEC-001",
      "nom_size_vo": "12mm",
      "article_code": "ART-001",
      "no_document": "DOC-001",
      "no_doc_reference": "REF-001",
      "color": "#FF5733",
      "size": "Large"
    },
    "measurement_points": [...],
    "measurement_groups": [...]
  }
}
```

**Error Response - Not Found (404):**
```json
{
  "http_code": 404,
  "message": "Product tidak ditemukan",
  "error_id": "NOT_FOUND",
  "data": null
}
```

**Validasi:**
- Sama seperti saat create product
- Formula validation
- Name uniqueness validation
- Type-specific validation

**Authorization:**
- Role: **admin** atau **superadmin**

---

## ✅ 7. Endpoint Check Product Exists - Semua Field

### GET `/api/v1/products/is-product-exists`

Endpoint ini **sudah menerima semua field basic info** untuk pengecekan. Product dianggap sama jika **SEMUA field match** (termasuk null values).

**Query Parameters:**

**Required:**
- `product_category_id`: Integer
- `product_name`: String

**Optional (Nullable):**
- `ref_spec_number`: String
- `nom_size_vo`: String
- `article_code`: String
- `no_document`: String
- `no_doc_reference`: String
- `color`: String (HEX format)
- `size`: String

**Request Example:**
```http
GET /api/v1/products/is-product-exists?product_category_id=1&product_name=COT&ref_spec_number=SPEC-001&nom_size_vo=12mm&article_code=ART-001&no_document=DOC-001&no_doc_reference=REF-001&color=%23FF0000&size=Large
Authorization: Bearer {token}
```

**Response Example - Product Exists:**
```json
{
  "http_code": 200,
  "message": "Success",
  "error_id": null,
  "data": {
    "is_product_exists": true
  }
}
```

**Response Example - Product Not Exists:**
```json
{
  "http_code": 200,
  "message": "Success",
  "error_id": null,
  "data": {
    "is_product_exists": false
  }
}
```

**Logic:**
- Product dianggap **berbeda** jika ada 1 saja field yang berbeda
- Field yang `null` vs field yang diisi value dianggap berbeda
- Contoh:
  - Product A: `ref_spec_number = null`
  - Product B: `ref_spec_number = "SPEC-001"`
  - ❌ Dianggap **berbeda**

**Authorization:**
- Role: **admin** atau **superadmin**

---

## 📦 Contoh Payload: Measurement Groups (Create Product)

### Apa itu Measurement Groups?

Measurement Groups digunakan untuk **mengelompokkan dan mengurutkan** measurement items saat ditampilkan ke user. Ini sangat berguna untuk:
- Mengorganisir measurement items berdasarkan kategori (Physical, Chemical, Visual, dll)
- Mengatur urutan tampilan measurement items
- Memudahkan user saat melakukan measurement

### Struktur Measurement Groups

```json
{
  "measurement_groups": [
    {
      "group_name": "Physical Properties",
      "measurement_items": ["thickness", "width", "length"],
      "order": 1
    },
    {
      "group_name": "Chemical Properties",
      "measurement_items": ["ph_level", "density"],
      "order": 2
    },
    {
      "group_name": "Visual Inspection",
      "measurement_items": ["color_check", "surface_quality"],
      "order": 3
    }
  ]
}
```

**Field Explanation:**
- `group_name`: Nama grup (contoh: "Physical Properties")
- `measurement_items`: Array of `name_id` dari measurement points yang masuk grup ini
- `order`: Urutan grup (ascending). Grup dengan order lebih kecil tampil lebih dulu

### Contoh Lengkap: Create Product dengan Grouping

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TubeTest",
    "ref_spec_number": "SPEC-TUBE-001",
    "nom_size_vo": "25mm",
    "article_code": "TUBE-25",
    "no_document": "DOC-2024-001",
    "no_doc_reference": "REF-2024-001",
    "color": "#4A90E2",
    "size": "Medium"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness",
        "name_id": "thickness",
        "sample_amount": 5,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.5,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.1
      }
    },
    {
      "setup": {
        "name": "Width",
        "name_id": "width",
        "sample_amount": 5,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 25,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    },
    {
      "setup": {
        "name": "Length",
        "name_id": "length",
        "sample_amount": 5,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 100,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    },
    {
      "setup": {
        "name": "pH Level",
        "name_id": "ph_level",
        "sample_amount": 3,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "pH",
        "value": 7,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    },
    {
      "setup": {
        "name": "Density",
        "name_id": "density",
        "sample_amount": 3,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "g/cm³",
        "value": 1.2,
        "tolerance_minus": 0.05,
        "tolerance_plus": 0.05
      }
    },
    {
      "setup": {
        "name": "Color Check",
        "name_id": "color_check",
        "sample_amount": 1,
        "nature": "QUALITATIVE",
        "source": "MANUAL"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Apakah warna sesuai standar?"
        }
      },
      "rule_evaluation_setting": null
    },
    {
      "setup": {
        "name": "Surface Quality",
        "name_id": "surface_quality",
        "sample_amount": 1,
        "nature": "QUALITATIVE",
        "source": "MANUAL"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Apakah permukaan bebas cacat?"
        }
      },
      "rule_evaluation_setting": null
    }
  ],
  "measurement_groups": [
    {
      "group_name": "Dimensi Fisik",
      "measurement_items": ["thickness", "width", "length"],
      "order": 1
    },
    {
      "group_name": "Properti Kimia",
      "measurement_items": ["ph_level", "density"],
      "order": 2
    },
    {
      "group_name": "Inspeksi Visual",
      "measurement_items": ["color_check", "surface_quality"],
      "order": 3
    }
  ]
}
```

### Hasil Grouping

Setelah product dibuat dengan grouping di atas, measurement items akan diurutkan sebagai berikut:

**Grup 1: Dimensi Fisik** (order: 1)
1. Thickness
2. Width
3. Length

**Grup 2: Properti Kimia** (order: 2)
4. pH Level
5. Density

**Grup 3: Inspeksi Visual** (order: 3)
6. Color Check
7. Surface Quality

### Measurement Items yang Tidak Dikelompokkan

Jika ada measurement items yang tidak masuk ke grup manapun, mereka akan otomatis masuk ke grup "Ungrouped" dengan order = 999 (paling akhir).

Contoh:
```json
{
  "measurement_points": [
    {"setup": {"name_id": "item_1"}},
    {"setup": {"name_id": "item_2"}},
    {"setup": {"name_id": "item_3"}}
  ],
  "measurement_groups": [
    {
      "group_name": "Group A",
      "measurement_items": ["item_1"],
      "order": 1
    }
  ]
}
```

Hasil urutan:
1. item_1 (Group A)
2. item_2 (Ungrouped)
3. item_3 (Ungrouped)

### Tips Penggunaan Grouping

1. **Buat grup berdasarkan kategori measurement**
   - Physical (Thickness, Width, Length)
   - Chemical (pH, Density, Viscosity)
   - Visual (Color, Surface, Defects)
   - Functional (Strength, Durability)

2. **Gunakan order yang teratur**
   - Beri jarak antar order (1, 10, 20) untuk fleksibilitas insert grup baru di tengah

3. **Validation**
   - `name_id` di `measurement_items` harus valid (ada di `measurement_points`)
   - Satu measurement item bisa masuk ke 1 grup saja
   - Order harus integer

4. **Optional**
   - `measurement_groups` bersifat optional
   - Jika tidak diisi, measurement items ditampilkan sesuai urutan di array `measurement_points`

---

## 🔐 Authorization & Roles

### Role Requirements per Endpoint:

| Endpoint | Required Role |
|----------|---------------|
| GET `/product-measurement/available-products` | user, admin, superadmin |
| GET `/products` | admin, superadmin |
| PUT `/products/{id}` | admin, superadmin |
| DELETE `/products/{id}` | admin, superadmin |
| GET `/products/is-product-exists` | admin, superadmin |
| GET `/product-measurement` | user, admin, superadmin |

### Header Authorization:
```http
Authorization: Bearer {jwt_token}
```

Token didapat dari endpoint `/api/v1/login`.

---

## 📝 Notes Penting

### 1. Migration Wajib Dijalankan

```bash
php artisan migrate
```

Migration ini akan mengubah nama field di tabel `tools`:
- `last_calibration` → `last_calibration_at`
- `next_calibration` → `next_calibration_at`

### 2. Breaking Changes - Tools API

Frontend harus update request/response handling untuk tools endpoints:

**Before:**
```json
{
  "last_calibration": "2024-06-01",
  "next_calibration": "2025-06-01"
}
```

**After:**
```json
{
  "last_calibration_at": "2024-06-01",
  "next_calibration_at": "2025-06-01"
}
```

### 3. Quarter Mapping

System menggunakan mapping quarter sebagai berikut:

| Quarter | Bulan | Date Range |
|---------|-------|------------|
| Q1 | Juni - Agustus | 01 Jun - 31 Aug |
| Q2 | September - November | 01 Sep - 30 Nov |
| Q3 | Desember - Februari | 01 Dec - 28/29 Feb (next year) |
| Q4 | Maret - Mei | 01 Mar - 31 May |

**Perhatian:** Q3 melewati batas tahun (cross-year).

### 4. Product Delete Restriction

Product hanya bisa dihapus jika:
- ✅ Belum punya data `product_measurements`
- ❌ Sudah punya data `product_measurements` → Error 400

### 5. Product Exists Logic

Endpoint `/products/is-product-exists` membandingkan **SEMUA field** basic info:
- Jika semua field match (termasuk null) → `is_product_exists: true`
- Jika ada 1 field berbeda → `is_product_exists: false`

Field nullable yang tidak diisi vs diisi dianggap **berbeda**.

---

## 🧪 Testing Endpoints

### Test dengan cURL:

#### 1. Available Products
```bash
curl -X GET "http://localhost/api/v1/product-measurement/available-products?quarter=3&year=2024&page=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 2. Products with Category Filter
```bash
curl -X GET "http://localhost/api/v1/products?product_category_id=1&page=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 3. Product Measurements with Quarter Filter
```bash
curl -X GET "http://localhost/api/v1/product-measurement?quarter=3&year=2024&status=ONGOING" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 4. Update Product
```bash
curl -X PUT "http://localhost/api/v1/products/PRD-ABC123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "basic_info": {
      "product_name": "Updated Product Name",
      "color": "#FF5733"
    }
  }'
```

#### 5. Delete Product
```bash
curl -X DELETE "http://localhost/api/v1/products/PRD-ABC123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 6. Check Product Exists
```bash
curl -X GET "http://localhost/api/v1/products/is-product-exists?product_category_id=1&product_name=COT&ref_spec_number=SPEC-001" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📚 Referensi

- [OpenAPI Documentation](./openapi.yaml)
- [Formula Guide](./FORMULA_AND_DATA_PROCESSING_GUIDE.md)
- [Tools Logic](./TOOLS_LOGIC_EXPLANATION.md)

---

## 📞 Support

Jika ada pertanyaan atau issue terkait API updates ini, silakan hubungi tim development.

**Last Updated:** December 2, 2025

