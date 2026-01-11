# 📊 Report Feature - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Feature Specifications](#feature-specifications)
3. [API Endpoints](#api-endpoints)
4. [Postman Testing Guide](#postman-testing-guide)
5. [Response Examples](#response-examples)
6. [Troubleshooting](#troubleshooting)
7. [Technical Details](#technical-details)

---

## 🎯 Overview

Report Feature adalah fitur untuk generate dan download laporan measurement dalam format Excel dan PDF. Fitur ini mendukung:

- ✅ Filter data berdasarkan Quarter, Product, dan Batch Number
- ✅ Upload master Excel file untuk custom template
- ✅ Auto-inject measurement data ke sheet "raw_data"
- ✅ Role-based download (Excel untuk Admin/SuperAdmin, PDF untuk Operator)
- ✅ Download multiple PDF (jika master file memiliki multiple sheets)

---

## 📐 Feature Specifications

### **1. Format Excel Report**

Table dengan kolom berikut:

| Kolom | Deskripsi | Contoh |
|-------|-----------|--------|
| **Name** | Nama instrument items untuk setiap product | Diameter, Length, Weight |
| **Type** | Tipe measurement item | Single, Before, After, Variable, Pre Processing Formula, Aggregation |
| **Sample Index** | Index sample (numeric untuk raw samples, "-" untuk calculated values) | 1, 2, 3, atau "-" |
| **Result** | Nilai hasil measurement atau calculation | 27.5, 30.2, 29.8 |

**Contoh Data:**

| Name | Type | Sample Index | Result |
|------|------|--------------|--------|
| Diameter | Single | 1 | 23 |
| Diameter | Single | 2 | 24 |
| Diameter | Single | 3 | 25 |
| Diameter | Aggregation | - | 29 |

### **2. Filtering Flow**

User dapat filter laporan berdasarkan:
1. **Quarter** → Pilih quarter (Q1, Q2, Q3, Q4) dan tahun
2. **Product** → Pilih product yang tersedia di quarter tersebut
3. **Batch Number** → Pilih batch number yang tersedia untuk product tersebut

**Batch numbers akan otomatis filtered berdasarkan Quarter dan Product yang dipilih.**

### **3. Upload & Download Logic**

#### **Default Download (Tanpa Upload Master File):**
- User tidak upload master file
- Download akan menghasilkan file Excel/PDF dengan nama **"raw_data"**
- File hanya berisi 1 sheet dengan tabel measurement items

#### **Upload Master File:**
- User dapat upload master Excel file (misal: "File A.xlsx" dengan 4 sheets)
- Jika master file memiliki sheet bernama **"raw_data"**, data measurement akan di-inject ke sheet tersebut
- Jika sheet "raw_data" tidak ada, sistem akan membuat sheet baru dengan nama "raw_data"
- Data lama di sheet "raw_data" akan di-clear dan diganti dengan data baru

#### **Download Setelah Upload:**
- Jika master file sudah di-upload, download akan menghasilkan master file yang sudah di-update
- Data measurement items sudah ter-inject ke sheet "raw_data"
- Semua sheets lainnya tetap utuh

### **4. Role-Based Download**

#### **Admin / SuperAdmin:**
- ✅ Dapat download sebagai **Excel** (`.xlsx`)
- ❌ Tidak dapat download sebagai PDF
- Jika master file sudah di-upload, download akan menghasilkan master Excel file yang sudah di-update
- Jika belum upload, download akan menghasilkan `raw_data.xlsx`

#### **Operator:**
- ✅ Dapat download sebagai **PDF** (`.pdf`)
- ❌ Tidak dapat download sebagai Excel
- Jika master file sudah di-upload (misal: 4 sheets), download akan menghasilkan **1 file ZIP** berisi:
  - Multiple PDF files (1 PDF per sheet)
  - Nama file: `{master_filename}_Sheet1.pdf`, `{master_filename}_Sheet2.pdf`, dst.
- Jika belum upload, download akan menghasilkan 1 file PDF `raw_data.pdf`

---

## 🔌 API Endpoints

### **Base URL**
```
http://139.59.231.237:2020/api/v1
```

### **Authentication**
Semua endpoint memerlukan **Bearer Token** di header:
```
Authorization: Bearer {your_token}
```

---

### **1. Get Quarters Filter**

Get list semua quarters yang tersedia.

**Endpoint:**
```
GET /api/v1/reports/filters/quarters
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Query Parameters:**
- Tidak ada

**Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Quarters retrieved successfully",
    "error_id": null,
    "data": [
        {
            "quarter": 1,
            "year": 2024,
            "name": "Q1",
            "display_name": "Q1 2024"
        },
        {
            "quarter": 4,
            "year": 2024,
            "name": "Q4",
            "display_name": "Q4 2024",
            "is_active": true
        },
        {
            "quarter": 1,
            "year": 2026,
            "name": "Q1",
            "display_name": "Q1 2026"
        }
    ]
}
```

---

### **2. Get Products Filter**

Get list products yang tersedia di quarter tertentu.

**Endpoint:**
```
GET /api/v1/reports/filters/products?quarter={quarter}&year={year}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |

**Example:**
```
GET /api/v1/reports/filters/products?quarter=1&year=2026
```

**Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Products retrieved successfully",
    "error_id": null,
    "data": [
        {
            "product_id": "PRD-4CIMFX1E",
            "product_name": "Optical Fiber Cable",
            "product_spec_name": "Optical Fiber Cable Q1-2026-001",
            "product_category": "Cable"
        },
        {
            "product_id": "PRD-XRBRZ8VW",
            "product_name": "Fiber Optic Connector",
            "product_spec_name": "Fiber Optic Connector Q1-2026-002",
            "product_category": "Connector"
        }
    ]
}
```

---

### **3. Get Batch Numbers Filter**

Get list batch numbers yang tersedia untuk product tertentu di quarter tertentu.

**Endpoint:**
```
GET /api/v1/reports/filters/batch-numbers?quarter={quarter}&year={year}&product_id={product_id}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |
| `product_id` | string | Yes | Product ID (e.g., PRD-4CIMFX1E) |

**Example:**
```
GET /api/v1/reports/filters/batch-numbers?quarter=1&year=2026&product_id=PRD-4CIMFX1E
```

**Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Batch numbers retrieved successfully",
    "error_id": null,
    "data": [
        {
            "batch_number": "MEASUREMENTTOOLS",
            "measurement_id": "MSR-4CIMFX1E",
            "created_at": "2026-01-10 14:30:00",
            "product_status": "OK"
        },
        {
            "batch_number": "BATCH-001",
            "measurement_id": "MSR-ABCD1234",
            "created_at": "2026-01-09 10:15:00",
            "product_status": "PENDING"
        }
    ]
}
```

**Note:** Data diurutkan dari yang terbaru (`orderBy('created_at', 'desc')`).

---

### **4. Get Report Data**

Get data measurement items untuk preview sebelum download.

**Endpoint:**
```
GET /api/v1/reports/data?quarter={quarter}&year={year}&product_id={product_id}&batch_number={batch_number}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |
| `product_id` | string | Yes | Product ID |
| `batch_number` | string | Yes | Batch number |

**Example:**
```
GET /api/v1/reports/data?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Report data retrieved successfully",
    "error_id": null,
    "data": {
        "product": {
            "product_id": "PRD-4CIMFX1E",
            "product_name": "Optical Fiber Cable",
            "product_spec_name": "Optical Fiber Cable Q1-2026-001",
            "product_category": "Cable"
        },
        "measurement_items": [
            {
                "name": "Diameter",
                "name_id": "diameter",
                "type": "QUANTITATIVE JUDGMENT",
                "status": "OK"
            },
            {
                "name": "Length",
                "name_id": "length",
                "type": "QUANTITATIVE JUDGMENT",
                "status": "OK"
            }
        ],
        "summary": {
            "measurement_ok": 2,
            "measurement_ng": 0,
            "todo": 0
        },
        "measurement_id": "MSR-4CIMFX1E",
        "batch_number": "MEASUREMENTTOOLS"
    }
}
```

---

### **5. Upload Master File**

Upload master Excel file untuk custom template.

**Endpoint:**
```
POST /api/v1/reports/upload-master
```

**Headers:**
```
Authorization: Bearer {token}
```

**⚠️ PENTING:** Jangan tambahkan `Content-Type` header secara manual. Postman akan auto-set `multipart/form-data`.

**Body (form-data):**

| Key | Type | Value | Required |
|-----|------|-------|----------|
| `quarter` | Text | `1` | Yes |
| `year` | Text | `2026` | Yes |
| `product_id` | Text | `PRD-4CIMFX1E` | Yes |
| `batch_number` | Text | `MEASUREMENTTOOLS` | Yes |
| `file` | File | [Select Excel file] | Yes |

**File Requirements:**
- Format: `.xlsx` atau `.xls`
- Max size: 10MB
- Sheet "raw_data" akan digunakan untuk inject data (jika tidak ada, akan dibuat baru)

**Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Master file uploaded successfully",
    "error_id": null,
    "data": {
        "master_file_id": 1,
        "product_measurement_id": 123,
        "measurement_id": "MSR-4CIMFX1E",
        "batch_number": "MEASUREMENTTOOLS",
        "original_filename": "Example Product Data.xlsx",
        "stored_filename": "master_1736543210_abc123.xlsx",
        "file_path": "reports/master_files/master_1736543210_abc123.xlsx",
        "sheet_names": [
            "Sheet1",
            "raw_data",
            "Summary",
            "Appendix"
        ],
        "total_sheets": 4,
        "has_raw_data_sheet": true,
        "uploaded_by": "admin",
        "uploaded_at": "2026-01-10 15:30:45",
        "note": "Data will be injected to existing 'raw_data' sheet"
    }
}
```

**Error Responses:**

**400 Bad Request - File required:**
```json
{
    "http_code": 400,
    "message": "Request invalid",
    "error_id": "VALIDATION_ERROR",
    "data": {
        "file": ["The file field is required."]
    }
}
```

**400 Bad Request - Invalid file type:**
```json
{
    "http_code": 400,
    "message": "Request invalid",
    "error_id": "VALIDATION_ERROR",
    "data": {
        "file": ["The file must be a file of type: xlsx, xls."]
    }
}
```

**404 Not Found - Measurement not found:**
```json
{
    "http_code": 404,
    "message": "Measurement tidak ditemukan untuk batch number ini",
    "error_id": null,
    "data": null
}
```

**Note:** Jika master file sudah ada untuk measurement ini, file lama akan di-replace dengan file baru.

---

### **6. Download Excel (Admin/SuperAdmin Only)**

Download report sebagai Excel file.

**Endpoint:**
```
GET /api/v1/reports/download/excel?quarter={quarter}&year={year}&product_id={product_id}&batch_number={batch_number}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |
| `product_id` | string | Yes | Product ID |
| `batch_number` | string | Yes | Batch number |

**Example:**
```
GET /api/v1/reports/download/excel?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Response:**
- **File download** (Excel file `.xlsx`)
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Behavior:**
- Jika master file sudah di-upload → Download master file yang sudah di-update (data sudah ter-inject ke sheet "raw_data")
- Jika belum upload → Download file baru `raw_data.xlsx` dengan 1 sheet berisi tabel measurement

**Error Responses:**

**403 Forbidden - Unauthorized role:**
```json
{
    "http_code": 403,
    "message": "Only Admin and SuperAdmin can download Excel",
    "error_id": null,
    "data": null
}
```

**404 Not Found - Measurement not found:**
```json
{
    "http_code": 404,
    "message": "No measurement found for the specified filters",
    "error_id": "ERR_MEASUREMENT_NOT_FOUND",
    "data": null
}
```

---

### **7. Download PDF (Operator Only)**

Download report sebagai PDF file(s).

**Endpoint:**
```
GET /api/v1/reports/download/pdf?quarter={quarter}&year={year}&product_id={product_id}&batch_number={batch_number}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |
| `product_id` | string | Yes | Product ID |
| `batch_number` | string | Yes | Batch number |

**Example:**
```
GET /api/v1/reports/download/pdf?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Response:**
- **Single PDF file** (jika belum upload master file atau master file hanya 1 sheet)
  - Content-Type: `application/pdf`
  - Filename: `raw_data.pdf`
  
- **ZIP file** (jika master file sudah di-upload dengan multiple sheets)
  - Content-Type: `application/zip`
  - Filename: `{master_filename}.zip`
  - Contains: Multiple PDF files (1 PDF per sheet)
    - `{master_filename}_Sheet1.pdf`
    - `{master_filename}_raw_data.pdf`
    - `{master_filename}_Summary.pdf`
    - dst.

**Error Responses:**

**403 Forbidden - Unauthorized role:**
```json
{
    "http_code": 403,
    "message": "Only Operator can download PDF",
    "error_id": null,
    "data": null
}
```

**404 Not Found - Measurement not found:**
```json
{
    "http_code": 404,
    "message": "No measurement found for the specified filters",
    "error_id": "ERR_MEASUREMENT_NOT_FOUND",
    "data": null
}
```

---

## 🧪 Postman Testing Guide

### **Base URL**
```
http://139.59.231.237:2020/api/v1
```

### **Test Accounts**

```
SuperAdmin:
- Username: superadmin
- Password: admin123
- Role: superadmin
- Can: Download Excel

Admin:
- Username: admin
- Password: admin123
- Role: admin
- Can: Download Excel

Operator:
- Username: operator
- Password: admin123
- Role: operator
- Can: Download PDF only
```

---

## 📝 Step-by-Step Testing

### **STEP 0: Login & Get Token**

**Request:**
```
POST http://139.59.231.237:2020/api/v1/login
```

**Headers:**
```
Content-Type: application/json
```

**Body (raw JSON):**
```json
{
    "username": "admin",
    "password": "admin123"
}
```

**Response:**
```json
{
    "http_code": 200,
    "message": "Login successful",
    "error_id": null,
    "data": {
        "id": 2,
        "username": "admin",
        "role": "admin",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        ...
    }
}
```

**⚠️ PENTING:** Copy `token` dari response untuk digunakan di semua request berikutnya!

**Postman Tip:** Di tab "Tests" pada request Login, tambahkan script untuk auto-save token:
```javascript
pm.environment.set("token", pm.response.json().data.token);
```

---

### **STEP 1: Get Available Quarters**

**Request:**
```
GET http://139.59.231.237:2020/api/v1/reports/filters/quarters
```

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Expected Response:**
```json
{
    "http_code": 200,
    "message": "Quarters retrieved successfully",
    "error_id": null,
    "data": [
        {
            "quarter": 1,
            "year": 2026,
            "name": "Q1",
            "display_name": "Q1 2026"
        }
    ]
}
```

**📝 Pilih salah satu quarter & year untuk step berikutnya (contoh: Q1 2026)**

---

### **STEP 2: Get Products by Quarter**

**Request:**
```
GET http://139.59.231.237:2020/api/v1/reports/filters/products?quarter=1&year=2026
```

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Expected Response:**
```json
{
    "http_code": 200,
    "message": "Products retrieved successfully",
    "error_id": null,
    "data": [
        {
            "product_id": "PRD-4CIMFX1E",
            "product_name": "Optical Fiber Cable",
            "product_spec_name": "Optical Fiber Cable Q1-2026-001",
            "product_category": "Cable"
        }
    ]
}
```

**📝 Pilih salah satu `product_id` untuk step berikutnya (contoh: PRD-4CIMFX1E)**

---

### **STEP 3: Get Batch Numbers**

**Request:**
```
GET http://139.59.231.237:2020/api/v1/reports/filters/batch-numbers?quarter=1&year=2026&product_id=PRD-4CIMFX1E
```

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Expected Response:**
```json
{
    "http_code": 200,
    "message": "Batch numbers retrieved successfully",
    "error_id": null,
    "data": [
        {
            "batch_number": "MEASUREMENTTOOLS",
            "measurement_id": "MSR-4CIMFX1E",
            "created_at": "2026-01-10 14:30:00",
            "product_status": "OK"
        }
    ]
}
```

**📝 Pilih salah satu `batch_number` untuk step berikutnya (contoh: MEASUREMENTTOOLS)**

---

### **STEP 4: Get Report Data (Preview)**

**Request:**
```
GET http://139.59.231.237:2020/api/v1/reports/data?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Expected Response:**
```json
{
    "http_code": 200,
    "message": "Report data retrieved successfully",
    "error_id": null,
    "data": {
        "product": {...},
        "measurement_items": [...],
        "summary": {...},
        "measurement_id": "MSR-4CIMFX1E",
        "batch_number": "MEASUREMENTTOOLS"
    }
}
```

**📝 Verifikasi data sudah benar sebelum download**

---

### **STEP 5: Upload Master Excel File**

**Request:**
```
POST http://139.59.231.237:2020/api/v1/reports/upload-master
```

**Headers:**
```
Authorization: Bearer {your_token}
```

**⚠️ PENTING:** Jangan tambahkan `Content-Type` header secara manual!

**Body Setup di Postman:**

1. **Tab Body** → Pilih **"form-data"** (bukan raw atau JSON)

2. **Tambahkan fields berikut:**

| Key | Type | Value |
|-----|------|-------|
| `quarter` | Text | `1` |
| `year` | Text | `2026` |
| `product_id` | Text | `PRD-4CIMFX1E` |
| `batch_number` | Text | `MEASUREMENTTOOLS` |
| `file` | **File** | [Pilih file Excel .xlsx atau .xls] |

**Cara Pilih File:**
- Pada row `file`, hover ke kolom Value
- Akan muncul dropdown "Text" → ubah ke **"File"**
- Klik "Select Files"
- Pilih file Excel kamu (misal: `Master_Report.xlsx`)

**⚠️ PENTING:**
- Key `file` harus **lowercase**
- Type harus **File** (bukan Text)
- File harus `.xlsx` atau `.xls` (bukan CSV atau PDF)
- Checkbox di kiri row harus di-check
- Max file size: 10MB

**Visual Setup:**
```
┌────────────────┬──────────┬────────────────────────────────┬─────────────┐
│ ☑️ KEY         │ TYPE     │ VALUE                          │ DESCRIPTION │
├────────────────┼──────────┼────────────────────────────────┼─────────────┤
│ ☑️ quarter     │ Text ▼   │ 1                              │             │
│ ☑️ year        │ Text ▼   │ 2026                           │             │
│ ☑️ product_id  │ Text ▼   │ PRD-4CIMFX1E                   │             │
│ ☑️ batch_number│ Text ▼   │ MEASUREMENTTOOLS               │             │
│ ☑️ file        │ File ▼   │ Master_Report.xlsx 📎          │             │
└────────────────┴──────────┴────────────────────────────────┴─────────────┘
```

**Expected Response (200 OK):**
```json
{
    "http_code": 200,
    "message": "Master file uploaded successfully",
    "error_id": null,
    "data": {
        "master_file_id": 1,
        "product_measurement_id": 123,
        "measurement_id": "MSR-4CIMFX1E",
        "batch_number": "MEASUREMENTTOOLS",
        "original_filename": "Master_Report.xlsx",
        "stored_filename": "master_1736543210_abc123.xlsx",
        "file_path": "reports/master_files/master_1736543210_abc123.xlsx",
        "sheet_names": ["Sheet1", "raw_data", "Summary"],
        "total_sheets": 3,
        "has_raw_data_sheet": true,
        "uploaded_by": "admin",
        "uploaded_at": "2026-01-10 15:30:45",
        "note": "Data will be injected to existing 'raw_data' sheet"
    }
}
```

**Error Responses:**

**400 Bad Request - File required:**
```json
{
    "http_code": 400,
    "message": "Request invalid",
    "error_id": "VALIDATION_ERROR",
    "data": {
        "file": ["The file field is required."]
    }
}
```
**Solusi:** Pastikan file sudah dipilih dan key `file` ada di form-data

**400 Bad Request - Invalid file type:**
```json
{
    "http_code": 400,
    "message": "Request invalid",
    "error_id": "VALIDATION_ERROR",
    "data": {
        "file": ["The file must be a file of type: xlsx, xls."]
    }
}
```
**Solusi:** File harus `.xlsx` atau `.xls`, bukan CSV atau PDF

**404 Not Found - Measurement not found:**
```json
{
    "http_code": 404,
    "message": "Measurement tidak ditemukan untuk batch number ini",
    "error_id": null,
    "data": null
}
```
**Solusi:** Cek kombinasi quarter, year, product_id, dan batch_number

---

### **STEP 6A: Download Excel (Admin/SuperAdmin)**

**Request:**
```
GET http://139.59.231.237:2020/api/v1/reports/download/excel?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Headers:**
```
Authorization: Bearer {your_admin_token}
```

**⚠️ PENTING:** Pastikan masih login sebagai **admin** atau **superadmin**!

**Di Postman:**
1. Klik tombol **"Send and Download"** (bukan "Send" biasa)
   - Di Postman versi lama: Klik dropdown "Send" → "Send and Download"
2. File Excel akan terdownload
3. Buka file Excel tersebut
4. Check sheet **"raw_data"** → harusnya sudah ada data measurement items

**Expected Behavior:**
- ✅ Jika master file sudah di-upload → Download master file yang sudah di-update (data sudah ter-inject ke sheet "raw_data")
- ✅ Jika belum upload → Download file baru `raw_data.xlsx` dengan 1 sheet berisi tabel measurement

**Verifikasi File Excel:**
- ✅ Sheet "raw_data" ada dan berisi tabel dengan kolom: Name | Type | Sample Index | Result
- ✅ Data measurement items sudah ter-inject
- ✅ Jika master file punya sheets lain, sheets tersebut tetap utuh

**Error Responses:**

**403 Forbidden - Unauthorized role:**
```json
{
    "http_code": 403,
    "message": "Only Admin and SuperAdmin can download Excel",
    "error_id": null,
    "data": null
}
```
**Solusi:** Login sebagai admin atau superadmin

---

### **STEP 6B: Download PDF (Operator)**

**⚠️ Untuk test ini, login ulang sebagai operator:**

**1. Login sebagai Operator:**
```
POST http://139.59.231.237:2020/api/v1/login
```

**Body:**
```json
{
    "username": "operator",
    "password": "admin123"
}
```

**Copy token baru dari response!**

**2. Download PDF:**
```
GET http://139.59.231.237:2020/api/v1/reports/download/pdf?quarter=1&year=2026&product_id=PRD-4CIMFX1E&batch_number=MEASUREMENTTOOLS
```

**Headers:**
```
Authorization: Bearer {operator_token}
```

**Di Postman:**
1. Klik tombol **"Send and Download"**
2. File akan terdownload

**Expected Behavior:**
- ✅ Jika belum upload master file → Download 1 file PDF `raw_data.pdf`
- ✅ Jika sudah upload master file dengan 4 sheets → Download 1 file ZIP berisi 4 file PDF terpisah

**Verifikasi:**
- Jika dapat ZIP file, extract dan check:
  - `{master_filename}_Sheet1.pdf`
  - `{master_filename}_raw_data.pdf` (berisi data measurement)
  - `{master_filename}_Summary.pdf`
  - dst.

**Error Responses:**

**403 Forbidden - Unauthorized role:**
```json
{
    "http_code": 403,
    "message": "Only Operator can download PDF",
    "error_id": null,
    "data": null
}
```
**Solusi:** Login sebagai operator

---

## 🔄 Complete Testing Flow

```
1. Login (admin) → Get token ✅
2. Get Quarters → Pilih Q1 2026 ✅
3. Get Products → Pilih PRD-4CIMFX1E ✅
4. Get Batch Numbers → Pilih MEASUREMENTTOOLS ✅
5. Get Report Data → Preview data ✅
6. Upload Master File → Upload Excel dengan sheet "raw_data" ✅
7. Download Excel → Cek data sudah masuk ke sheet "raw_data" ✅
8. Login (operator) → Get new token ✅
9. Download PDF → Cek dapat ZIP dengan multiple PDF ✅
```

---

## ⚠️ Troubleshooting

### **Problem 1: "The file field is required" saat Upload**

**Penyebab:**
- Key `file` tidak ada di form-data
- Type field bukan "File"
- File belum dipilih

**Solusi:**
1. Pastikan di Body → pilih **form-data** (bukan raw)
2. Pastikan key `file` dengan type **File** (bukan Text)
3. Pastikan file sudah dipilih
4. Pastikan checkbox di kiri row file di-check

---

### **Problem 2: "Content type not supported"**

**Penyebab:**
- Header `Content-Type` ditambahkan secara manual

**Solusi:**
1. **JANGAN** tambahkan `Content-Type` di Headers secara manual
2. Hapus header `Content-Type` jika ada
3. Postman akan auto-set `multipart/form-data`

---

### **Problem 3: File tidak ter-upload**

**Penyebab:**
- File size > 10MB
- File bukan Excel (.xlsx atau .xls)
- File corrupt

**Solusi:**
1. Check file size < 10MB
2. Check ekstensi file: `.xlsx` atau `.xls`
3. Check file tidak corrupt (buka di Excel)
4. Try dengan file Excel baru yang simple

---

### **Problem 4: Response sheet names aneh (UUID)**

**Penyebab:**
- File Excel punya sheet dengan nama UUID/random
- Sheet corrupt atau unnamed

**Solusi:**
1. Buka file Excel
2. Rename sheet yang namanya aneh
3. Save dan upload ulang

**Note:** Helper sudah di-update untuk handle invalid sheet names, tapi tetap disarankan gunakan nama sheet yang normal.

---

### **Problem 5: Download Excel tidak ada data**

**Penyebab:**
- Measurement tidak punya data
- Batch number salah

**Solusi:**
1. Cek dengan endpoint `/reports/data` → harusnya ada `measurement_items`
2. Pastikan batch number benar
3. Pastikan measurement sudah submit samples

---

### **Problem 6: Download PDF dapat 1 file padahal master file punya 4 sheets**

**Penyebab:**
- Master file belum di-upload untuk measurement ini
- Master file hanya punya 1 sheet

**Solusi:**
1. Check dengan endpoint `/reports/data` → `has_master_file` harus `true`
2. Upload ulang master file dengan multiple sheets
3. Download ulang PDF

---

### **Problem 7: "Only Admin and SuperAdmin can download Excel"**

**Penyebab:**
- Login sebagai operator

**Solusi:**
- Login sebagai admin atau superadmin untuk download Excel
- Operator hanya bisa download PDF

---

### **Problem 8: "Only Operator can download PDF"**

**Penyebab:**
- Login sebagai admin/superadmin

**Solusi:**
- Login sebagai operator untuk download PDF
- Admin/SuperAdmin hanya bisa download Excel

---

## 🛠️ Technical Details

### **File Storage**

- **Location:** `storage/app/reports/master_files/`
- **Naming:** `master_{timestamp}_{hash}.xlsx`
- **Max Size:** 10MB
- **Allowed Types:** `.xlsx`, `.xls`

### **Database Table**

**Table:** `report_master_files`

**Schema:**
```sql
id                      BIGINT PRIMARY KEY
user_id                 BIGINT (FK: login_users)
product_measurement_id  BIGINT (FK: product_measurements)
original_filename       VARCHAR(255)
stored_filename         VARCHAR(255)
file_path               VARCHAR(500)
sheet_names             JSON (Array of sheet names)
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Indexes:**
- `product_measurement_id` (one master file per measurement)
- `user_id`

### **Helper Classes**

**`ReportExcelHelper`** (`app/Helpers/ReportExcelHelper.php`):

Methods:
- `transformMeasurementResultsToExcelRows()` - Convert measurement results to Excel rows
- `createExcelFile()` - Create new Excel file with raw data
- `mergeDataToMasterFile()` - Merge data to existing master Excel file
- `getSheetNames()` - Extract sheet names from Excel file

### **Models**

**`ReportMasterFile`** (`app/Models/ReportMasterFile.php`):

Relationships:
- `belongsTo(LoginUser, 'user_id')`
- `belongsTo(ProductMeasurement, 'product_measurement_id')`

Casts:
- `sheet_names` → array

### **Dependencies**

- `phpoffice/phpspreadsheet` ^2.0 - Excel manipulation
- `dompdf/dompdf` ^2.0 - PDF generation (core)
- `barryvdh/laravel-dompdf` ^2.0 - Laravel PDF wrapper

### **Role-Based Access Control**

**Middleware:** `api.auth` (JWT authentication)

**Role Checks:**
- Download Excel: `admin`, `superadmin` only
- Download PDF: `operator` only

**Implementation:**
```php
// In ReportController
if (!in_array($user->role, ['admin', 'superadmin'])) {
    return $this->unauthorizedResponse('Only Admin and SuperAdmin can download Excel');
}
```

### **Excel Structure**

**Sheet: raw_data**

| Column A | Column B | Column C | Column D |
|----------|----------|----------|----------|
| Name | Type | Sample Index | Result |
| Diameter | Single | 1 | 27.5 |
| Diameter | Single | 2 | 27.5 |
| Diameter | Aggregation | - | 27.5 |

**Styling:**
- Headers: Bold, white text, blue background
- Alignment: Left (Name, Type), Center (Sample Index), Right (Result)
- Column widths: Auto-sized with minimum widths

### **PDF Generation**

**Library:** `Barryvdh\DomPDF`

**Process:**
1. Load Excel file with PhpSpreadsheet
2. Convert each sheet to HTML table
3. Generate PDF from HTML using DomPDF
4. If multiple sheets → Create ZIP archive with multiple PDFs

**PDF Settings:**
- Format: A4
- Orientation: Portrait
- Margins: 10mm

---

## 📚 Additional Resources

### **API Base URL**
```
http://139.59.231.237:2020/api/v1
```

### **Storage Path**
```
storage/app/reports/master_files/
```

### **Migration File**
```
database/migrations/2026_01_10_210440_create_report_master_files_table.php
```

---

## ✅ Checklist Testing

- [ ] Login berhasil (admin)
- [ ] Get quarters berhasil
- [ ] Get products berhasil (dengan filter quarter)
- [ ] Get batch numbers berhasil (dengan filter quarter & product)
- [ ] Get report data berhasil (preview)
- [ ] Upload master file berhasil
- [ ] Download Excel berhasil (admin)
- [ ] Download Excel file punya data di sheet "raw_data"
- [ ] Login operator berhasil
- [ ] Download PDF berhasil (operator)
- [ ] Download PDF menghasilkan ZIP jika master file punya multiple sheets
- [ ] Error handling bekerja (unauthorized, not found, validation errors)

---

## 📝 Notes

1. **One Master File Per Measurement:** Jika upload master file baru untuk measurement yang sama, file lama akan di-replace.

2. **Sheet "raw_data":** 
   - Jika ada → Data akan di-inject ke sheet tersebut (data lama di-clear)
   - Jika tidak ada → Sistem akan membuat sheet baru dengan nama "raw_data"

3. **Data Format:** 
   - Raw samples: Sample Index = 1, 2, 3, ...
   - Calculated values: Sample Index = "-"
   - Types: Single, Before, After, Variable, Pre Processing Formula, Aggregation

4. **File Naming:**
   - Stored file: `master_{timestamp}_{hash}.xlsx`
   - Original filename: Disimpan di database untuk reference

5. **Role Permissions:**
   - Admin/SuperAdmin: Excel only
   - Operator: PDF only
   - Semua role: Dapat akses filters, data preview, dan upload

---

## 🎉 Summary

Report Feature adalah fitur lengkap untuk generate dan download laporan measurement dengan:
- ✅ Filtering berdasarkan Quarter, Product, dan Batch Number
- ✅ Upload custom Excel template
- ✅ Auto-inject data ke sheet "raw_data"
- ✅ Role-based download (Excel untuk Admin, PDF untuk Operator)
- ✅ Support multiple sheets untuk PDF generation

**Happy Testing!** 🚀

