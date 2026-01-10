# ✅ Report Feature - Implementation Summary

## 📦 Files Created/Modified

### 1. **Composer Dependencies** ✅
- Updated `composer.json` to include:
  - `phpoffice/phpspreadsheet`: ^2.0
  - `dompdf/dompdf`: ^2.0
  - `barryvdh/laravel-dompdf`: ^2.0

**Installation:**
```bash
composer install
# atau
composer update
```

### 2. **Database Migration** ✅
- Created: `database/migrations/2026_01_10_210440_create_report_master_files_table.php`
- Table: `report_master_files`
- Fields: id, user_id, product_measurement_id, original_filename, stored_filename, file_path, sheet_names (JSON), timestamps

**Run migration:**
```bash
php artisan migrate
```

### 3. **Models** ✅
- Created: `app/Models/ReportMasterFile.php`
- Relationships:
  - `belongsTo(LoginUser::class, 'user_id')`
  - `belongsTo(ProductMeasurement::class)`

### 4. **Helpers** ✅
- Created: `app/Helpers/ReportExcelHelper.php`
- Methods:
  - `transformMeasurementResultsToExcelRows()` - Convert measurement results to Excel format
  - `createExcelFile()` - Create new Excel file with data
  - `mergeDataToMasterFile()` - Merge data into existing master Excel file
  - `getSheetNames()` - Extract sheet names from Excel file

### 5. **Controller** ✅
- Created: `app/Http/Controllers/Api/V1/ReportController.php`
- Endpoints implemented:
  - `GET /api/v1/reports/filters/quarters` - Get available quarters
  - `GET /api/v1/reports/filters/products` - Get products by quarter
  - `GET /api/v1/reports/filters/batch-numbers` - Get batch numbers by product & quarter
  - `GET /api/v1/reports/data` - Get report data (measurement items)
  - `POST /api/v1/reports/upload-master` - Upload master Excel file
  - `GET /api/v1/reports/download/excel` - Download Excel (Admin/SuperAdmin only)
  - `GET /api/v1/reports/download/pdf` - Download PDF (Operator only)

### 6. **Routes** ✅
- Updated: `routes/api.php`
- Added routes under `/api/v1/reports` prefix
- All routes protected by `api.auth` middleware
- Role-based access for download endpoints

---

## 🚀 Setup Instructions

### Step 1: Install Dependencies
```bash
# Masuk ke folder project
cd C:\laragon\www\SyncFlow

# Install composer dependencies
composer install
```

### Step 2: Publish DomPDF Config (Optional)
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### Step 3: Run Migration
```bash
php artisan migrate
```

### Step 4: Create Storage Directory
```bash
# Pastikan directory ini ada:
# storage/app/reports/master_files/

# Jika belum ada, buat manual:
mkdir -p storage/app/reports/master_files
```

### Step 5: Set Storage Permissions (Linux/Mac)
```bash
chmod -R 775 storage/app/reports
```

---

## 📋 API Endpoints Usage

### 1. Get Quarters (Filter)
```
GET /api/v1/reports/filters/quarters
Headers: Authorization: Bearer {token}
Response: [
    { "quarter": 1, "year": 2025, "label": "Q1 2025" },
    ...
]
```

### 2. Get Products (Filter)
```
GET /api/v1/reports/filters/products?quarter=3&year=2025
Headers: Authorization: Bearer {token}
Response: [
    {
        "product_id": "PRD-XXXXX",
        "product_name": "CIVUSAS-S",
        "product_spec_name": "CIVUSAS-S",
        "product_category": "Wire Test Reguler"
    },
    ...
]
```

### 3. Get Batch Numbers (Filter)
```
GET /api/v1/reports/filters/batch-numbers?quarter=3&year=2025&product_id=PRD-XXXXX
Headers: Authorization: Bearer {token}
Response: [
    "XYZ-22082025-01",
    "XYZ-22082025-02",
    ...
]
```

### 4. Get Report Data
```
GET /api/v1/reports/data?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Headers: Authorization: Bearer {token}
Response: {
    "product": { ... },
    "measurement_items": [
        {
            "name": "Inside Diameter",
            "name_id": "inside_diameter",
            "type": "QUANTITATIVE JUDGMENT",
            "status": "OK"
        },
        ...
    ],
    "summary": {
        "measurement_ok": 6,
        "measurement_ng": 0,
        "todo": 0
    }
}
```

### 5. Upload Master File
```
POST /api/v1/reports/upload-master
Headers: 
    Authorization: Bearer {token}
    Content-Type: multipart/form-data
Body:
    quarter: 3
    year: 2025
    product_id: "PRD-XXXXX"
    batch_number: "XYZ-22082025-01"
    file: <Excel file>
Response: {
    "master_file_id": 1,
    "filename": "Master_Report.xlsx",
    "sheets": ["Cover", "Summary", "raw_data", "Appendix"]
}
```

### 6. Download Excel (Admin/SuperAdmin)
```
GET /api/v1/reports/download/excel?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Headers: Authorization: Bearer {token}
Response: Excel file download
- If master file exists: Merged master file with data in 'raw_data' sheet
- If no master file: New 'raw_data.xlsx' file
```

### 7. Download PDF (Operator)
```
GET /api/v1/reports/download/pdf?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Headers: Authorization: Bearer {token}
Response: PDF file(s) download
- If master file exists: ZIP file containing PDF for each sheet
- If no master file: Single 'raw_data.pdf' file
```

---

## 🔍 Excel Format Structure

### Headers:
| Name | Type | Sample Index | Result |

### Example Data:
| Name | Type | Sample Index | Result |
|------|------|--------------|--------|
| Diameter | Single | 1 | 23 |
| Diameter | Single | 2 | 24 |
| Diameter | Aggregation | 1 | 29 |
| Room Temp | Variable | - | 25.5 |

### Type Values:
- `Single` - Raw single value from SINGLE type measurement
- `Before` - Before value from BEFORE_AFTER type measurement
- `After` - After value from BEFORE_AFTER type measurement
- `Variable` - Variable value (FIXED/MANUAL/FORMULA)
- `Pre Processing Formula` - Result from pre-processing formula
- `Aggregation` - Final value from JOINT evaluation or aggregation

---

## ⚠️ Important Notes

1. **Role-Based Access:**
   - Excel download: Only `admin` and `superadmin`
   - PDF download: Only `operator`

2. **Master File Logic:**
   - If master file uploaded: Data merged to `raw_data` sheet
   - If no master file: New Excel file created with `raw_data` sheet only
   - Master file must have `raw_data` sheet (or it will be created)

3. **Storage:**
   - Master files stored in: `storage/app/reports/master_files/`
   - Temp files automatically cleaned up after download

4. **File Size Limits:**
   - Max upload: 10MB
   - File types: `.xlsx`, `.xls` only

5. **PDF Generation:**
   - Uses DomPDF library
   - Paper size: A4 Landscape
   - Multiple sheets converted to separate PDFs and zipped

---

## 🐛 Troubleshooting

### Issue: "Class 'Barryvdh\DomPDF\Facade\Pdf' not found"
**Solution:**
```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
php artisan config:clear
```

### Issue: "Storage directory not writable"
**Solution:**
- Check `storage/app/reports/master_files/` exists
- Set proper permissions: `chmod -R 775 storage`

### Issue: "Memory limit exceeded" when processing large Excel files
**Solution:**
- Increase PHP memory limit in `php.ini`: `memory_limit = 256M`
- Or add to `.env`: `MEMORY_LIMIT=256M`

### Issue: "PDF generation failed"
**Solution:**
- Ensure DomPDF config is published
- Check if GD extension is enabled in PHP
- Verify temp directory is writable

---

## ✅ Testing Checklist

- [ ] Install dependencies (`composer install`)
- [ ] Run migration (`php artisan migrate`)
- [ ] Test filter endpoints (quarters, products, batch-numbers)
- [ ] Test get report data endpoint
- [ ] Test upload master file (as any user)
- [ ] Test download Excel (as admin/superadmin)
- [ ] Test download PDF (as operator)
- [ ] Verify Excel format matches specification
- [ ] Verify PDF generation works correctly
- [ ] Test with master file (multiple sheets)
- [ ] Test without master file (default raw_data)
- [ ] Verify role-based access restrictions

---

## 📝 Next Steps

1. Run `composer install` to install dependencies
2. Run `php artisan migrate` to create table
3. Create storage directory: `storage/app/reports/master_files/`
4. Test endpoints using Postman or frontend app
5. Verify Excel/PDF output matches requirements

---

**Status: ✅ Implementation Complete**
**Date: 2026-01-10**

