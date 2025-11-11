# Tools/Alat Ukur API Documentation

## 📖 Overview

Fitur untuk mengelola tools/alat ukur yang dapat digunakan sebagai sumber data pengukuran. Tools dapat dipilih berdasarkan model saat create product, dan saat melakukan pengukuran user dapat memilih IMEI spesifik.

**Base URL:** `/api/v1/tools`

---

## 🗄️ Database Schema

### Table: `tools`

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Primary key |
| tool_name | string | Nama tools untuk identifikasi |
| tool_model | string | Model tools (untuk select di product) |
| tool_type | enum | OPTICAL / MECHANICAL |
| last_calibration | date (nullable) | Tanggal kalibrasi terakhir |
| next_calibration | date (nullable) | Auto-calculated (last + 1 year) |
| imei | string (unique) | Serial number unik |
| status | enum | ACTIVE / INACTIVE (default: ACTIVE) |
| created_at | timestamp | |
| updated_at | timestamp | |

### Table: `measurement_items` (Updated)

Ditambahkan field:
- `tool_id` (nullable, foreign key ke tools)

---

## 🎯 API Endpoints

### 1. Get All Tools (dengan Pagination)

```http
GET /api/v1/tools?page=1&limit=10&status=ACTIVE&search=caliper
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 10)
- `status` - Filter: ACTIVE / INACTIVE
- `tool_model` - Filter by model
- `tool_type` - Filter: OPTICAL / MECHANICAL
- `search` - Search by tool_name, tool_model, atau imei

**Response:**
```json
{
  "http_code": 200,
  "message": "Tools retrieved successfully",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": 1,
        "tool_name": "Digital Caliper Lab 1",
        "tool_model": "Mitutoyo CD-6",
        "tool_type": "MECHANICAL",
        "tool_type_description": "Mechanical",
        "last_calibration": "2025-01-15",
        "next_calibration": "2026-01-15",
        "imei": "MIT-CD6-001",
        "status": "ACTIVE",
        "status_description": "Active",
        "created_at": "2025-11-06T10:00:00.000000Z",
        "updated_at": "2025-11-06T10:00:00.000000Z"
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

### 2. Get Single Tool

```http
GET /api/v1/tools/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Tool retrieved successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "tool_name": "Digital Caliper Lab 1",
    "tool_model": "Mitutoyo CD-6",
    "tool_type": "MECHANICAL",
    "tool_type_description": "Mechanical",
    "last_calibration": "2025-01-15",
    "next_calibration": "2026-01-15",
    "imei": "MIT-CD6-001",
    "status": "ACTIVE",
    "status_description": "Active",
    "created_at": "2025-11-06T10:00:00.000000Z",
    "updated_at": "2025-11-06T10:00:00.000000Z"
  }
}
```

---

### 3. Get Tool Models (untuk dropdown saat create product)

```http
GET /api/v1/tools/models
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Tool models retrieved successfully",
  "error_id": null,
  "data": [
    {
      "tool_model": "Mitutoyo CD-6",
      "tool_type": "MECHANICAL",
      "tool_type_description": "Mechanical",
      "imei_count": 3
    },
    {
      "tool_model": "Keyence LK-G5001",
      "tool_type": "OPTICAL",
      "tool_type_description": "Optical",
      "imei_count": 2
    }
  ]
}
```

**Note:** Hanya menampilkan tools dengan status ACTIVE.

---

### 4. Get Tools by Model (untuk select IMEI saat measurement)

```http
GET /api/v1/tools/by-model?tool_model=Mitutoyo%20CD-6
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Tools retrieved successfully",
  "error_id": null,
  "data": {
    "tool_model": "Mitutoyo CD-6",
    "tools": [
      {
        "id": 1,
        "tool_name": "Digital Caliper Lab 1",
        "imei": "MIT-CD6-001",
        "last_calibration": "2025-01-15",
        "next_calibration": "2026-01-15"
      },
      {
        "id": 2,
        "tool_name": "Digital Caliper Lab 2",
        "imei": "MIT-CD6-002",
        "last_calibration": "2025-02-20",
        "next_calibration": "2026-02-20"
      }
    ]
  }
}
```

---

### 5. Create Tool (Admin/SuperAdmin Only)

```http
POST /api/v1/tools
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "tool_name": "Digital Caliper Lab 3",
  "tool_model": "Mitutoyo CD-6",
  "tool_type": "MECHANICAL",
  "last_calibration": "2025-11-06",
  "imei": "MIT-CD6-003",
  "status": "ACTIVE"
}
```

**Validation Rules:**
- `tool_name` - required, string, max 255
- `tool_model` - required, string, max 255
- `tool_type` - required, enum (OPTICAL, MECHANICAL)
- `last_calibration` - optional, date
- `imei` - required, string, max 255, unique
- `status` - optional, enum (ACTIVE, INACTIVE), default: ACTIVE

**Response:**
```json
{
  "http_code": 201,
  "message": "Tool created successfully",
  "error_id": null,
  "data": {
    "id": 3,
    "tool_name": "Digital Caliper Lab 3",
    "tool_model": "Mitutoyo CD-6",
    "tool_type": "MECHANICAL",
    "tool_type_description": "Mechanical",
    "last_calibration": "2025-11-06",
    "next_calibration": "2026-11-06",
    "imei": "MIT-CD6-003",
    "status": "ACTIVE",
    "status_description": "Active",
    "created_at": "2025-11-06T12:00:00.000000Z",
    "updated_at": "2025-11-06T12:00:00.000000Z"
  }
}
```

**Note:** `next_calibration` otomatis dihitung 1 tahun dari `last_calibration`.

---

### 6. Update Tool (Admin/SuperAdmin Only)

```http
PUT /api/v1/tools/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (semua field optional):**
```json
{
  "tool_name": "Digital Caliper Lab 1 - Updated",
  "status": "INACTIVE"
}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Tool updated successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "tool_name": "Digital Caliper Lab 1 - Updated",
    "tool_model": "Mitutoyo CD-6",
    "tool_type": "MECHANICAL",
    "tool_type_description": "Mechanical",
    "last_calibration": "2025-01-15",
    "next_calibration": "2026-01-15",
    "imei": "MIT-CD6-001",
    "status": "INACTIVE",
    "status_description": "Inactive",
    "created_at": "2025-11-06T10:00:00.000000Z",
    "updated_at": "2025-11-06T12:30:00.000000Z"
  }
}
```

**Note:** Jika `last_calibration` diupdate, `next_calibration` akan otomatis terupdate.

---

### 7. Delete Tool (Admin/SuperAdmin Only)

```http
DELETE /api/v1/tools/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Tool deleted successfully",
  "error_id": null,
  "data": {
    "deleted": true
  }
}
```

**Warning:** Tool yang sudah digunakan di measurement_items akan menyebabkan `tool_id` menjadi NULL. Pertimbangkan set status INACTIVE daripada delete.

---

## ❌ Error Responses

### 400 - Validation Error
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_60F9A1B2C3D4E",
  "data": {
    "tool_name": ["The tool name field is required."],
    "imei": ["The imei has already been taken."]
  }
}
```

### 404 - Not Found
```json
{
  "http_code": 404,
  "message": "Tool not found",
  "error_id": "ERR_60F9A1B2C3D4E",
  "data": null
}
```

### 403 - Forbidden
```json
{
  "http_code": 403,
  "message": "Forbidden",
  "error_id": "ERR_60F9A1B2C3D4E",
  "data": null
}
```

---

## 🔄 Integration Flow

### A. Create Product dengan Tool Source

**1. Get tool models untuk dropdown:**
```http
GET /api/v1/tools/models
```

**2. Create product dengan tool source:**
```http
POST /api/v1/products

{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "Product A"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "THICKNESS_A",
        "sample_amount": 10,
        "source": "TOOL",
        "source_tool_model": "Mitutoyo CD-6",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {}
    }
  ]
}
```

---

### B. Melakukan Measurement dengan Tool

**1. Get tools by model:**
```http
GET /api/v1/tools/by-model?tool_model=Mitutoyo%20CD-6
```

**2. User pilih IMEI, lalu save measurement:**
```http
POST /api/v1/measurements

{
  "measurement_id": 123,
  "tool_id": 1,
  "thickness_type": "THICKNESS_A",
  "value": 2.55,
  "sequence": 1
}
```

---

## 🚀 Quick Start

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Seed Sample Data (Optional)
```bash
php artisan db:seed --class=ToolSeeder
```

Akan membuat 10 sample tools:
- 3x Mitutoyo CD-6 (2 active, 1 inactive)
- 2x Keyence LK-G5001
- 2x Mahr Micromar 40 EWR
- Dan lainnya

### 3. Test API

**Login:**
```bash
POST /api/v1/login
{
  "username": "admin",
  "password": "password"
}
```

**Get Tools:**
```bash
GET /api/v1/tools?page=1&limit=10
Authorization: Bearer {token}
```

---

## 💡 Key Features

### 1. Auto-Calculate Next Calibration
- Saat create/update tool dengan `last_calibration`
- System otomatis set `next_calibration` = last_calibration + 1 year

### 2. IMEI Grouping
- Multiple tools bisa punya model sama tapi IMEI berbeda
- Saat create product: tampilkan 1 model
- Saat measurement: user pilih IMEI mana yang digunakan

### 3. Status Management
- **ACTIVE**: Tool bisa dipilih saat create product dan measurement
- **INACTIVE**: Tool tidak bisa dipilih, tapi data tetap ada (untuk historical)

### 4. Search & Filter
- Pagination: `page`, `limit`
- Filter: `status`, `tool_model`, `tool_type`
- Search: by tool_name, tool_model, atau imei

---

## 📊 Response Format

Semua endpoint menggunakan format konsisten:

**Success:**
```json
{
  "http_code": 200,
  "message": "Success message",
  "error_id": null,
  "data": {...}
}
```

**Pagination:**
```json
{
  "http_code": 200,
  "message": "Success message",
  "error_id": null,
  "data": {
    "docs": [...],
    "metadata": {
      "current_page": 1,
      "total_page": 3,
      "limit": 10,
      "total_docs": 25
    }
  }
}
```

**Error:**
```json
{
  "http_code": 400|404|500,
  "message": "Error message",
  "error_id": "ERR_UNIQUE_ID",
  "data": null
}
```

---

## 🔐 Authorization

| Endpoint | Role Required |
|----------|---------------|
| GET /tools | Authenticated User |
| GET /tools/{id} | Authenticated User |
| GET /tools/models | Authenticated User |
| GET /tools/by-model | Authenticated User |
| POST /tools | Admin / SuperAdmin |
| PUT /tools/{id} | Admin / SuperAdmin |
| DELETE /tools/{id} | Admin / SuperAdmin |

---

## 📝 Notes

1. **IMEI harus unique** di seluruh database
2. **Status ACTIVE** required agar tool muncul di models list
3. **Calibration** auto-calculated, adjust jika periode berbeda
4. **Delete** akan set tool_id = NULL di measurement_items (consider soft delete)
5. **Product source** sekarang support: INSTRUMENT, MANUAL, DERIVED, **TOOL**

---

## 🧪 Testing Checklist

- [ ] Create tool dengan semua field
- [ ] Create tool dengan field optional
- [ ] Verify next_calibration auto-calculated
- [ ] Get all tools dengan pagination
- [ ] Get all tools dengan filters
- [ ] Get tool models (hanya ACTIVE)
- [ ] Get tools by model (hanya ACTIVE)
- [ ] Update tool dan verify next_calibration update
- [ ] Update tool status ke INACTIVE
- [ ] Verify INACTIVE tools tidak muncul di models
- [ ] Delete tool
- [ ] Validate IMEI uniqueness
- [ ] Create product dengan source TOOL
- [ ] Create measurement dengan tool_id

---

## 🆘 Support

**Files Created:**
- Migration: `2025_11_06_000001_create_tools_table.php`
- Migration: `2025_11_06_000002_add_tool_id_to_measurement_items_table.php`
- Model: `app/Models/Tool.php`
- Enum: `app/Enums/ToolType.php`, `app/Enums/ToolStatus.php`
- Controller: `app/Http/Controllers/Api/V1/ToolController.php`
- Seeder: `database/seeders/ToolSeeder.php`

**Routes:** `routes/api.php` - prefix: `/api/v1/tools`

---

## ✨ Example Usage (cURL)

```bash
# Get all tools
curl -X GET "http://localhost:8000/api/v1/tools?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create tool
curl -X POST "http://localhost:8000/api/v1/tools" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool_name": "Digital Caliper Lab 4",
    "tool_model": "Mitutoyo CD-6",
    "tool_type": "MECHANICAL",
    "last_calibration": "2025-11-06",
    "imei": "MIT-CD6-004"
  }'

# Update tool status
curl -X PUT "http://localhost:8000/api/v1/tools/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "INACTIVE"}'

# Delete tool
curl -X DELETE "http://localhost:8000/api/v1/tools/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

**Ready to use!** 🎉

