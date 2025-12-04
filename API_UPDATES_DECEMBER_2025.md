# SyncFlow API Updates - December 2025

## 📋 Ringkasan Perbaikan

Dokumen ini berisi semua perbaikan dan penambahan endpoint API yang dilakukan pada December 2025.

### ✅ Daftar Perbaikan (December 2024)

#### December 4, 2024 - Bug Fixes & Documentation Updates
1. 🐛 **Bug Fix**: Fixed `variable_values` field validation (`name_id` → `name`)
2. 🐛 **Bug Fix**: Fixed `qualitative_value` type validation (boolean → string)
3. 🐛 **Bug Fix**: Fixed "Undefined array key 'type'" error for QUALITATIVE measurements
4. 🐛 **Critical Bug Fix**: Fixed duplicate measurement check (was checking all products instead of per product)
5. 📝 **Update**: Corrected payload examples dengan valid product names
6. 📝 **Update**: Added valid product names list per category
7. 📝 **Update**: Clarified `due_date` format requirements

#### December 2, 2024 - New Features & Endpoints
1. ✅ Endpoint Baru: GET `/product-measurement/available-products`
2. ✅ Filter `product_category_id` di GET `/products`
3. ✅ Fix Filter Quarter di GET `/product-measurement`
4. ✅ Update Tools - Calibration Fields
5. ✅ Endpoint DELETE `/products/:id`
6. ✅ Endpoint UPDATE `/products/:id`
7. ✅ Endpoint `/products/is-product-exists` - Semua Field Basic Info
8. ✅ **Feature**: Measurement Groups dengan Standalone Items support

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
- **Mendukung standalone items** yang tidak perlu dikelompokkan

### Struktur Measurement Groups

Ada 2 tipe dalam `measurement_groups`:

#### 1. **Grouped Items** (Ada `group_name`)
Items yang dikelompokkan bersama dengan nama grup.

#### 2. **Standalone Items** (Tanpa `group_name` / `group_name: null`)
Items yang tidak dikelompokkan, berdiri sendiri, tapi tetap bisa diposisikan dengan `order`.

```json
{
  "measurement_groups": [
    {
      "group_name": "THICKNESS",
      "measurement_items": ["thickness_a", "thickness_b"],
      "order": 1
    },
    {
      "group_name": null,
      "measurement_items": ["analyze"],
      "order": 2
    },
    {
      "group_name": "ROOM TEMP",
      "measurement_items": ["room_temp"],
      "order": 3
    }
  ]
}
```

**Field Explanation:**
- `group_name`: 
  - **String**: Nama grup untuk grouped items (contoh: "THICKNESS", "Physical Properties")
  - **null**: Untuk standalone items yang tidak perlu grup
- `measurement_items`: Array of `name_id` dari measurement points
  - Untuk grouped items: bisa berisi multiple items
  - Untuk standalone items: biasanya 1 item (tapi bisa lebih jika diperlukan)
- `order`: Urutan tampil (ascending). Order lebih kecil tampil lebih dulu

### Contoh 1: Create Product dengan Mixed Grouping (Grouped + Standalone)

**Use Case:** Ada thickness A & B yang digroup, lalu analyze standalone, lalu room_temp di group sendiri.

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "VO",
    "ref_spec_number": "SPEC-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "name_id": "thickness_a",
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
        "name": "Thickness B",
        "name_id": "thickness_b",
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
        "name": "Analyze",
        "name_id": "analyze",
        "sample_amount": 1,
        "nature": "QUALITATIVE",
        "source": "MANUAL"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Hasil analisa?"
        }
      },
      "rule_evaluation_setting": null
    },
    {
      "setup": {
        "name": "Room Temperature",
        "name_id": "room_temp",
        "sample_amount": 1,
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
      "group_name": "THICKNESS",
      "measurement_items": ["thickness_a", "thickness_b"],
      "order": 1
    },
    {
      "group_name": null,
      "measurement_items": ["analyze"],
      "order": 2
    },
    {
      "group_name": "ROOM TEMP",
      "measurement_items": ["room_temp"],
      "order": 3
    }
  ]
}
```

**Hasil Urutan Pengukuran:**

1. **THICKNESS** (Group, order: 1)
   - Thickness A
   - Thickness B

2. **Analyze** (Standalone, order: 2)

3. **ROOM TEMP** (Group, order: 3)
   - Room Temperature

---

### Contoh 2: Product dengan Grouping Tradisional

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COT"
  },
  "measurement_points": [
    {"setup": {"name": "Thickness", "name_id": "thickness", ...}},
    {"setup": {"name": "Width", "name_id": "width", ...}},
    {"setup": {"name": "pH Level", "name_id": "ph_level", ...}},
    {"setup": {"name": "Color", "name_id": "color", ...}}
  ],
  "measurement_groups": [
    {
      "group_name": "Physical Properties",
      "measurement_items": ["thickness", "width"],
      "order": 1
    },
    {
      "group_name": "Chemical Properties",
      "measurement_items": ["ph_level"],
      "order": 2
    },
    {
      "group_name": "Visual Inspection",
      "measurement_items": ["color"],
      "order": 3
    }
  ]
}
```

**Hasil Urutan:**

1. **Physical Properties** (order: 1)
   - Thickness
   - Width

2. **Chemical Properties** (order: 2)
   - pH Level

3. **Visual Inspection** (order: 3)
   - Color

---

### Measurement Items yang Tidak Didefinisikan di Groups

Jika ada measurement items yang **tidak disebutkan** di `measurement_groups`, mereka akan otomatis muncul di **paling akhir** dengan order tinggi (9999+).

**Contoh:**

```json
{
  "measurement_points": [
    {"setup": {"name_id": "item_1"}},
    {"setup": {"name_id": "item_2"}},
    {"setup": {"name_id": "item_3"}},
    {"setup": {"name_id": "item_4"}}
  ],
  "measurement_groups": [
    {
      "group_name": "Group A",
      "measurement_items": ["item_1", "item_2"],
      "order": 1
    }
  ]
}
```

**Hasil Urutan:**
1. item_1 (Group A)
2. item_2 (Group A)
3. item_3 (Tidak didefinisikan, muncul di akhir)
4. item_4 (Tidak didefinisikan, muncul di akhir)

### Tips Penggunaan Grouping

1. **Kapan menggunakan Grouped Items vs Standalone Items?**
   - **Grouped Items** (`group_name` ada): Untuk measurement yang secara logis terkait
     - Contoh: Thickness A, B, C → Group "THICKNESS"
     - Contoh: pH, Density, Viscosity → Group "Chemical Properties"
   
   - **Standalone Items** (`group_name: null`): Untuk measurement yang berdiri sendiri
     - Contoh: Analyze, Notes, Special Check
     - Items yang tidak perlu dikelompokkan tapi perlu posisi spesifik

2. **Gunakan order yang teratur**
   - Beri jarak antar order (1, 10, 20, 30) untuk fleksibilitas insert item baru di tengah
   - Order menentukan urutan tampilan saat pengukuran

3. **Validation Rules**
   - `name_id` di `measurement_items` harus valid (ada di `measurement_points`)
   - Satu measurement item hanya bisa masuk ke 1 grup/entry
   - Order harus integer
   - `measurement_items` minimal berisi 1 item

4. **Optional**
   - `measurement_groups` bersifat optional
   - Jika tidak diisi, measurement items ditampilkan sesuai urutan di array `measurement_points`

5. **Best Practice**
   - Untuk multiple items sejenis → gunakan grouped items
   - Untuk single item yang perlu posisi khusus → gunakan standalone items
   - Items yang tidak penting urutannya → biarkan tidak didefinisikan (akan muncul di akhir)

---

---

## 🎯 PAYLOAD LENGKAP: Create Product sampai Submit Measurement (OK & NG)

### STEP 1: CREATE PRODUCT dengan Grouping & Standalone

**Endpoint:** `POST /api/v1/products`

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TubeTest",
    "ref_spec_number": "SPEC-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "name_id": "thickness_a",
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
        "unit": "mm",
        "value": 2.5,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.1
      }
    },
    {
      "setup": {
        "name": "Thickness B",
        "name_id": "thickness_b",
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
        "unit": "mm",
        "value": 2.5,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.1
      }
    },
    {
      "setup": {
        "name": "Analyze Check",
        "name_id": "analyze",
        "sample_amount": 1,
        "nature": "QUALITATIVE",
        "source": "MANUAL"
      },
      "variables": [],
      "pre_processing_formulas": [],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Hasil analisa visual?"
        }
      },
      "rule_evaluation_setting": null
    },
    {
      "setup": {
        "name": "Room Temperature",
        "name_id": "room_temp",
        "sample_amount": 1,
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
        "tolerance_minus": 3,
        "tolerance_plus": 3
      }
    },
    {
      "setup": {
        "name": "Width",
        "name_id": "width",
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
        "unit": "mm",
        "value": 25,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    }
  ],
  "measurement_groups": [
    {
      "group_name": "THICKNESS",
      "measurement_items": ["thickness_a", "thickness_b"],
      "order": 1
    },
    {
      "group_name": null,
      "measurement_items": ["analyze"],
      "order": 2
    },
    {
      "group_name": "ROOM TEMP",
      "measurement_items": ["room_temp"],
      "order": 3
    }
  ]
}
```

**Penjelasan Grouping:**
- **Order 1 - THICKNESS** (Group): thickness_a, thickness_b
- **Order 2 - Standalone**: analyze (group_name: null) ← **DI TENGAH GROUPING!**
- **Order 3 - ROOM TEMP** (Group): room_temp
- **width**: Tidak didefinisikan di groups, akan muncul paling akhir (order 9999+)

**☑️ PENTING:** Standalone items (group_name: null) **BISA diposisikan di mana saja** (awal, tengah, akhir) sesuai order-nya!

---

### STEP 2: CREATE MEASUREMENT ENTRY

**Endpoint:** `POST /api/v1/product-measurement`

**Payload:**
```json
{
  "product_id": "PRD-ABC12345",
  "batch_number": "BATCH-20241204-001",
  "sample_count": 3,
  "measurement_type": "FULL_MEASUREMENT",
  "due_date": "2024-12-10",
  "notes": "Pengukuran batch pertama"
}
```

**Note:** Format `due_date`:
- ✅ Gunakan format `Y-m-d` (contoh: `"2024-12-10"`)
- ✅ Atau ISO 8601: `Y-m-d\TH:i:s` (contoh: `"2024-12-10T10:00:00"`)
- ⚠️ Tanggal harus >= hari ini (`after_or_equal:today`)

---

### STEP 3A: SUBMIT MEASUREMENT - Hasil OK

**Endpoint:** `POST /api/v1/product-measurement/{measurement_id}/submit`

**Payload:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 2.48,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 2.52,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 2.50,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "thickness_b",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 2.49,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 2.51,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 2.50,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "analyze",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": null,
          "before_after_value": null,
          "qualitative_value": "Permukaan halus, tidak ada cacat"
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "room_temp",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 24.5,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "width",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 25.2,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 25.1,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 25.3,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    }
  ]
}
```

---

### STEP 3B: SUBMIT MEASUREMENT - Hasil NG

**Payload:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 2.70,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 2.65,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 2.68,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "thickness_b",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 2.49,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 2.51,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 2.50,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "analyze",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": null,
          "before_after_value": null,
          "qualitative_value": "Permukaan normal"
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "room_temp",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 30.5,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    },
    {
      "measurement_item_name_id": "width",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 25.2,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 2,
          "single_value": 25.1,
          "before_after_value": null,
          "qualitative_value": null
        },
        {
          "sample_index": 3,
          "single_value": 25.3,
          "before_after_value": null,
          "qualitative_value": null
        }
      ],
      "joint_setting_formula_values": []
    }
  ]
}
```

**Penjelasan NG:**
- **thickness_a**: NG karena semua sample (2.65-2.70) melebihi batas max 2.60 (2.5 + 0.1)
- **room_temp**: NG karena 30.5°C melebihi batas max 28°C (25 + 3)
- **Overall Result**: NG karena ada minimal 1 item NG

---

## ✅ KONFIRMASI: Standalone Items Bisa Di Tengah Grouping

### Contoh Kasus yang User Tanyakan:

**Payload measurement_groups:**
```json
{
  "measurement_groups": [
    {
      "group_name": "THICKNESS",
      "measurement_items": ["thickness_a", "thickness_b"],
      "order": 1
    },
    {
      "group_name": null,
      "measurement_items": ["analyze"],
      "order": 2
    },
    {
      "group_name": "ROOM TEMP",
      "measurement_items": ["room_temp"],
      "order": 3
    }
  ]
}
```

**Hasil Urutan Saat Pengukuran:**

```
┌─────────────────────────────────────────┐
│ ORDER 1: THICKNESS (Group)              │
├─────────────────────────────────────────┤
│   • Thickness A                         │
│   • Thickness B                         │
├─────────────────────────────────────────┤
│ ORDER 2: Analyze (Standalone) ⬅️ TENGAH! │
├─────────────────────────────────────────┤
│ ORDER 3: ROOM TEMP (Group)              │
├─────────────────────────────────────────┤
│   • Room Temperature                    │
└─────────────────────────────────────────┘
```

**Response GET Product:**
```json
{
  "measurement_points": [
    {
      "setup": {"name": "Thickness A", "name_id": "thickness_a"},
      "group_name": "THICKNESS",
      "group_order": 1
    },
    {
      "setup": {"name": "Thickness B", "name_id": "thickness_b"},
      "group_name": "THICKNESS",
      "group_order": 1
    },
    {
      "setup": {"name": "Analyze", "name_id": "analyze"},
      "group_name": null,
      "group_order": 2
    },
    {
      "setup": {"name": "Room Temperature", "name_id": "room_temp"},
      "group_name": "ROOM TEMP",
      "group_order": 3
    }
  ]
}
```

**✅ KESIMPULAN:**
- ✅ **Group** (order 1) → posisi pertama
- ✅ **Standalone** (order 2) → posisi **DI TENGAH** ← **BISA!**
- ✅ **Group** (order 3) → posisi ketiga

**Order menentukan posisi, bukan tipe (grouped/standalone)!**

---

### STEP 4: CREATE PRODUCT TANPA GROUPING

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Weight",
        "name_id": "weight",
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
        "unit": "g",
        "value": 100,
        "tolerance_minus": 5,
        "tolerance_plus": 5
      }
    },
    {
      "setup": {
        "name": "Length",
        "name_id": "length",
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
        "unit": "mm",
        "value": 50,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ]
}
```

**Catatan:** Tidak ada field `measurement_groups`, items ditampilkan sesuai urutan array.

---

## 🔧 BUG FIXES

### Fixed Validation Errors (December 4, 2024)

#### 1. variable_values field name
**File:** `app/Http/Controllers/Api/V1/ProductMeasurementController.php` (Line 1029)

- **Before:** `'measurement_results.*.variable_values.*.name_id'`
- **After:** `'measurement_results.*.variable_values.*.name'`
- **Reason:** Variable menggunakan `name`, bukan `name_id`

#### 2. qualitative_value type
**File:** `app/Http/Controllers/Api/V1/ProductMeasurementController.php` (Line 1038)

- **Before:** `'measurement_results.*.samples.*.qualitative_value' => 'nullable|boolean'`
- **After:** `'measurement_results.*.samples.*.qualitative_value' => 'nullable|string'`
- **Reason:** Qualitative value adalah text/string, bukan boolean

#### 3. Undefined array key "type" error
**File:** `app/Models/ProductMeasurement.php` (Line 200-208)

**Error Message:**
```
Error processing measurement: Undefined array key "type"
```

**Problem:**
Untuk QUALITATIVE measurement items, field `type` tidak wajib (karena hanya QUANTITATIVE yang butuh), tapi code langsung mengakses `$setup['type']` tanpa cek nullable.

**Fix:**
- Gunakan variable `$type` yang sudah di-check nullable (`$type = $setup['type'] ?? null;`)
- Tambahkan null coalescing operator untuk `qualitative_value`

#### 4. Duplicate measurement check salah - Mengecek semua product
**File:** `app/Http/Controllers/Api/V1/ProductMeasurementController.php` (Line 1244-1261)

**Error Message:**
```
Error processing measurement: Undefined array key "type"
```

**Problem:**
Untuk QUALITATIVE measurement items, field `type` tidak wajib (karena hanya QUANTITATIVE yang butuh), tapi code langsung mengakses `$setup['type']` tanpa cek nullable.

**Before:**
```php
// Set raw values berdasarkan type
if ($setup['type'] === 'SINGLE') {
    $processedSample['raw_values']['single_value'] = $sample['single_value'];
} elseif ($setup['type'] === 'BEFORE_AFTER') {
    $processedSample['raw_values']['before_after_value'] = $sample['before_after_value'];
}

// Set qualitative value jika ada
if ($setup['nature'] === 'QUALITATIVE') {
    $processedSample['raw_values']['qualitative_value'] = $sample['qualitative_value'];
}
```

**After:**
```php
// Set raw values berdasarkan type
if ($type === 'SINGLE') {
    $processedSample['raw_values']['single_value'] = $sample['single_value'];
} elseif ($type === 'BEFORE_AFTER') {
    $processedSample['raw_values']['before_after_value'] = $sample['before_after_value'];
}

// Set qualitative value jika ada
if ($setup['nature'] === 'QUALITATIVE') {
    $processedSample['raw_values']['qualitative_value'] = $sample['qualitative_value'] ?? null;
}
```

**Fix:**
- Gunakan variable `$type` yang sudah di-check nullable (`$type = $setup['type'] ?? null;`)
- Tambahkan null coalescing operator untuk `qualitative_value`

#### 4. Duplicate measurement check salah - Mengecek semua product ⚠️ CRITICAL BUG
**File:** `app/Http/Controllers/Api/V1/ProductMeasurementController.php` (Line 1244-1261)

**Error Message:**
```json
{
  "http_code": 400,
  "message": "Quarter ini sudah memiliki measurement (maksimal 1 data per quarter)",
  "error_id": "DUPLICATE_MEASUREMENT"
}
```

**Problem:**
Saat create measurement untuk **Product A**, sistem mengecek apakah ada measurement di quarter tersebut untuk **SEMUA product**, bukan hanya untuk Product A.

**Scenario Bug:**
- Product A sudah punya measurement di Q4 2024 ✅
- User coba buat measurement untuk Product B di Q4 2024
- ❌ Error: "Quarter ini sudah memiliki measurement"
- ✅ Harusnya: Product B belum punya measurement, jadi BOLEH dibuat!

**Before (SALAH):**
```php
return ProductMeasurement::where('measurement_type', 'FULL_MEASUREMENT')
    ->whereBetween('measured_at', [$quarterRange['start'], $quarterRange['end']])
    ->exists(); // ❌ Cek SEMUA product!
```

**After (BENAR):**
```php
return ProductMeasurement::where('product_id', $productId) // ✅ Filter by product
    ->where('measurement_type', 'FULL_MEASUREMENT')
    ->whereBetween('measured_at', [$quarterRange['start'], $quarterRange['end']])
    ->exists();
```

**Impact:**
- ✅ Product berbeda bisa punya measurement di quarter/hari yang sama
- ✅ 1 Product hanya bisa 1 measurement per quarter (FULL_MEASUREMENT)
- ✅ 1 Product hanya bisa 1 measurement per hari (SCALE_MEASUREMENT)

---

### Response Format untuk Product dengan Grouping

Ketika product di-retrieve (GET `/products` atau GET `/product-measurement/available-products`), measurement points akan dikembalikan dengan informasi grouping:

**Response Example:**

```json
{
  "http_code": 200,
  "message": "Success",
  "data": {
    "id": 1,
    "product_id": "PRD-ABC123",
    "product_name": "TubeTest",
    "measurement_points": [
      {
        "setup": {
          "name": "Thickness A",
          "name_id": "thickness_a",
          "sample_amount": 5,
          "nature": "QUANTITATIVE",
          "source": "MANUAL",
          "type": "SINGLE"
        },
        "group_name": "THICKNESS",
        "group_order": 1,
        "evaluation_type": "PER_SAMPLE",
        "rule_evaluation_setting": {...}
      },
      {
        "setup": {
          "name": "Thickness B",
          "name_id": "thickness_b",
          "sample_amount": 5,
          "nature": "QUANTITATIVE",
          "source": "MANUAL",
          "type": "SINGLE"
        },
        "group_name": "THICKNESS",
        "group_order": 1,
        "evaluation_type": "PER_SAMPLE",
        "rule_evaluation_setting": {...}
      },
      {
        "setup": {
          "name": "Analyze",
          "name_id": "analyze",
          "sample_amount": 1,
          "nature": "QUALITATIVE",
          "source": "MANUAL"
        },
        "group_name": null,
        "group_order": 2,
        "evaluation_type": "SKIP_CHECK",
        "evaluation_setting": {...}
      },
      {
        "setup": {
          "name": "Room Temperature",
          "name_id": "room_temp",
          "sample_amount": 1,
          "nature": "QUANTITATIVE",
          "source": "MANUAL",
          "type": "SINGLE"
        },
        "group_name": "ROOM TEMP",
        "group_order": 3,
        "evaluation_type": "PER_SAMPLE",
        "rule_evaluation_setting": {...}
      }
    ]
  }
}
```

**Field Explanation di Response:**
- `group_name`: 
  - **String** = Item ini bagian dari grup
  - **null** = Item standalone (tidak dikelompokkan)
- `group_order`: Urutan tampilan (sudah sorted dari backend)

**Frontend Implementation:**
Frontend bisa menampilkan UI berdasarkan `group_name`:
- Jika `group_name` ada → Tampilkan sebagai grup dengan header
- Jika `group_name` null → Tampilkan sebagai item standalone tanpa header grup
- Items sudah sorted by `group_order`, tinggal render sesuai urutan array

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

### 6. Valid Product Names per Category

Product name **harus valid** sesuai dengan product category. Gunakan salah satu dari list berikut:

#### Tube Test (Category ID: 1)
```
VO, COTO, COT, COTO-FR, COT-FR, CORUTUBE → PFPY17, 
CORUTUBE → PFP-FR-UF-09PL, CORUTUBE → PFP-FR-HEV-YKA, 
CORUTUBE → PFP-FR-HEV-YSA, RFCOT, HCOT
```

#### Wire Test Reguler (Category ID: 2)
```
CAVS, ACCAVS, CIVUS, ACCIVUS, AVSS, AVSSH, AVS, AV
```

#### Shield Wire Test (Category ID: 3)
```
CIVUSAS, CIVUSAS-S, CAVSAS-S, AVSSHCS, AVSSCS, AVSSCS-S
```

**Error jika product_name tidak valid:**
```json
{
  "http_code": 400,
  "message": "Product name \"XYZ\" tidak valid untuk category \"Tube Test\"",
  "error_id": "INVALID_PRODUCT_NAME"
}
```

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

## 📊 CHANGELOG SUMMARY

### December 4, 2024
- 🐛 **Bug Fix**: Fixed `variable_values` field name (`name_id` → `name`)
- 🐛 **Bug Fix**: Fixed `qualitative_value` type validation (boolean → string)
- 🐛 **Bug Fix**: Fixed undefined array key "type" error for QUALITATIVE measurements
- 🐛 **Critical Bug Fix**: Fixed duplicate measurement check (was checking all products, now checks per product)
- ✅ **Update**: Corrected payload examples dengan valid product names
- ✅ **Update**: Clarified `due_date` format requirements
- ✅ **Documentation**: Added valid product names per category

### December 2, 2024
- ✅ Added endpoint: `GET /product-measurement/available-products`
- ✅ Added filter: `product_category_id` di `GET /products`
- ✅ Fixed filter: Quarter di `GET /product-measurement`
- ✅ Updated: Tools calibration fields (`last_calibration_at`, `next_calibration_at`)
- ✅ Added endpoint: `DELETE /products/{id}`
- ✅ Added endpoint: `PUT /products/{id}`
- ✅ Updated endpoint: `GET /products/is-product-exists` (all fields support)
- ✅ Added feature: Measurement Groups dengan Standalone Items support

---

## 📚 Referensi

- [OpenAPI Documentation](./openapi.yaml)
- [Formula Guide](./FORMULA_AND_DATA_PROCESSING_GUIDE.md)
- [Tools Logic](./TOOLS_LOGIC_EXPLANATION.md)

---

## 📞 Support

Jika ada pertanyaan atau issue terkait API updates ini, silakan hubungi tim development.

**Last Updated:** December 4, 2024

