# 📊 Walkthrough Fitur Report Excel/PDF

## 🎯 Overview

Fitur Report memungkinkan user untuk:
1. Filter data berdasarkan **Quarter → Product → Batch Number**
2. Lihat **Measurement Items** beserta **Type** dan **Status**
3. **Download Excel** (Admin/SuperAdmin) atau **PDF** (Operator)
4. **Upload Excel Master File** untuk merge data ke dalam file existing

---

## 📋 Spesifikasi Format Excel

### Struktur Tabel
| Name | Type | Sample Index | Result |
|------|------|--------------|--------|
| Diameter | Single | 1 | 23 |
| Diameter | Single | 2 | 24 |
| Diameter | Single | 3 | 25 |
| Diameter | Aggregation | 1 | 29 |
| Room Temp | Variable | - | 25.5 |
| Final Value | Pre Processing Formula | - | 27.8 |

### Keterangan Kolom:
- **Name**: Nama measurement item (dari `measurement_points[].setup.name`)
- **Type**: 
  - `Single` - Sample raw value (tipe SINGLE)
  - `Before` - Before value (tipe BEFORE_AFTER)
  - `After` - After value (tipe BEFORE_AFTER)
  - `Variable` - Variable value (dari variables)
  - `Pre Processing Formula` - Hasil pre-processing formula
  - `Aggregation` - Hasil joint/aggregation formula (evaluation_type = JOINT)
- **Sample Index**: 
  - Nomor sample (1, 2, 3, dst) untuk Single/Before/After
  - `-` atau kosong untuk Variable/Pre Processing Formula/Aggregation
- **Result**: Nilai hasil measurement

---

## 🔄 Flow User

### 1. Filter Data
```
Step 1: User pilih Quarter (Q1, Q2, Q3, Q4) + Year
Step 2: System tampilkan Product yang tersedia di Quarter tersebut
Step 3: User pilih Product
Step 4: System tampilkan Batch Number yang tersedia untuk Product tersebut di Quarter tersebut
Step 5: User pilih Batch Number
```

**Endpoint Filter:**
- `GET /api/v1/reports/filters/quarters` - List semua quarters tersedia
- `GET /api/v1/reports/filters/products?quarter=3&year=2025` - List products di quarter
- `GET /api/v1/reports/filters/batch-numbers?quarter=3&year=2025&product_id=PRD-XXXXX` - List batch numbers

### 2. Lihat Report Data
```
Step 6: System tampilkan measurement items dengan:
- Nama measurement item
- Type (QUANTITATIVE JUDGMENT / QUALITATIVE JUDGMENT)
- Status (OK / NG / -)
```

**Endpoint:**
- `GET /api/v1/reports/data?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01`

### 3. Download/Upload Excel

#### **Scenario A: Default (Tidak ada master file)**
- **Admin/SuperAdmin**: Download Excel dengan nama `raw_data.xlsx`
  - Isi: Sheet "raw_data" dengan tabel format di atas
- **Operator**: Download PDF dengan nama `raw_data.pdf`
  - Isi: PDF dari tabel format di atas

#### **Scenario B: Upload Master File**
- User upload file Excel (misal: `Master_Report.xlsx`) dengan 4 sheets:
  - Sheet 1: "Cover"
  - Sheet 2: "Summary"
  - Sheet 3: "raw_data" ← Data akan masuk ke sini
  - Sheet 4: "Appendix"
- File disimpan ke storage dengan mapping ke user/product/quarter/batch

#### **Scenario C: Download setelah Upload Master**
- **Admin/SuperAdmin**: Download Excel `Master_Report.xlsx`
  - Semua 4 sheets tetap ada
  - Sheet "raw_data" sudah terisi dengan data measurement
- **Operator**: Download PDF
  - Convert setiap sheet menjadi PDF terpisah:
    - `Master_Report_Cover.pdf`
    - `Master_Report_Summary.pdf`
    - `Master_Report_raw_data.pdf`
    - `Master_Report_Appendix.pdf`

---

## 🛠️ Technical Implementation

### 1. Database Schema

#### Tabel: `report_master_files`
```sql
CREATE TABLE report_master_files (
    id BIGINT PRIMARY KEY,
    user_id BIGINT, -- User yang upload
    product_measurement_id BIGINT, -- FK ke product_measurements
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255), -- Nama file di storage
    file_path VARCHAR(500), -- Path lengkap di storage
    sheet_names JSON, -- ['Cover', 'Summary', 'raw_data', 'Appendix']
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES login_users(id),
    FOREIGN KEY (product_measurement_id) REFERENCES product_measurements(id),
    INDEX idx_product_measurement (product_measurement_id)
);
```

### 2. Dependencies (composer.json)
```json
{
    "require": {
        "phpoffice/phpspreadsheet": "^2.0",
        "dompdf/dompdf": "^2.0",
        "barryvdh/laravel-dompdf": "^2.0"
    }
}
```

### 3. API Endpoints

#### **Filter Endpoints**
```php
GET /api/v1/reports/filters/quarters
Response: [
    { "quarter": 1, "year": 2025, "label": "Q1 2025" },
    { "quarter": 2, "year": 2025, "label": "Q2 2025" },
    ...
]

GET /api/v1/reports/filters/products?quarter=3&year=2025
Response: [
    { "product_id": "PRD-XXXXX", "product_name": "CIVUSAS-S", ... },
    ...
]

GET /api/v1/reports/filters/batch-numbers?quarter=3&year=2025&product_id=PRD-XXXXX
Response: [
    "XYZ-22082025-01",
    "XYZ-22082025-02",
    ...
]
```

#### **Report Data Endpoint**
```php
GET /api/v1/reports/data?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Response: {
    "product": {
        "product_id": "PRD-XXXXX",
        "product_name": "CIVUSAS-S",
        "product_category": "Wire Test Reguler"
    },
    "measurement_items": [
        {
            "name": "Inside Diameter",
            "name_id": "inside_diameter",
            "type": "QUANTITATIVE JUDGMENT",
            "status": "OK"
        },
        {
            "name": "LOW TEMPERATURE WINDING",
            "name_id": "low_temp_winding",
            "type": "QUALITATIVE JUDGMENT",
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

#### **Upload Master File**
```php
POST /api/v1/reports/upload-master
Headers: Content-Type: multipart/form-data
Body: {
    quarter: 3,
    year: 2025,
    product_id: "PRD-XXXXX",
    batch_number: "XYZ-22082025-01",
    file: <file>
}
Response: {
    "master_file_id": 1,
    "filename": "Master_Report.xlsx",
    "sheets": ["Cover", "Summary", "raw_data", "Appendix"],
    "message": "Master file uploaded successfully"
}
```

#### **Download Excel (Admin/SuperAdmin)**
```php
GET /api/v1/reports/download/excel?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Response: Excel file download
- Jika ada master_file_id: Merge data ke sheet "raw_data" dalam master file
- Jika tidak ada: Download file baru "raw_data.xlsx"
```

#### **Download PDF (Operator)**
```php
GET /api/v1/reports/download/pdf?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
Response: PDF file(s) download
- Jika ada master_file_id: Convert setiap sheet menjadi PDF terpisah (zip)
- Jika tidak ada: Download single PDF "raw_data.pdf"
```

---

## 📊 Transform Data ke Format Excel

### Mapping dari `measurement_results` ke Excel Rows:

```php
// Contoh measurement_results:
{
    "measurement_item_name_id": "diameter",
    "status": true,
    "samples": [
        {
            "sample_index": 1,
            "raw_values": { "single_value": 23 },
            "processed_values": { "final_value": 23.5 },
            "status": true,
            "evaluated_value": 23
        },
        {
            "sample_index": 2,
            "raw_values": { "single_value": 24 },
            "processed_values": { "final_value": 24.5 },
            "status": true,
            "evaluated_value": 24
        }
    ],
    "final_value": 29, // Jika evaluation_type = JOINT
    "joint_results": [
        { "name": "avg_all", "value": 29, "is_final_value": true }
    ]
}

// Transform ke Excel rows:
[
    ["Diameter", "Single", 1, 23],
    ["Diameter", "Single", 2, 24],
    ["Diameter", "Pre Processing Formula", 1, 23.5], // jika ada processed_values
    ["Diameter", "Pre Processing Formula", 2, 24.5],
    ["Diameter", "Aggregation", 1, 29], // dari final_value atau joint_results
]
```

### Logic Transform:

```php
function transformMeasurementResultsToExcelRows($product, $measurementResults) {
    $rows = [];
    
    foreach ($measurementResults as $item) {
        $itemName = $item['measurement_item_name_id'];
        $measurementPoint = $product->getMeasurementPointByNameId($itemName);
        $displayName = $measurementPoint['setup']['name'];
        $type = $measurementPoint['setup']['type'] ?? 'SINGLE';
        $evaluationType = $measurementPoint['evaluation_type'];
        
        // 1. Process samples (Single/Before/After)
        if (!empty($item['samples'])) {
            foreach ($item['samples'] as $sample) {
                $sampleIndex = $sample['sample_index'];
                
                // Raw values
                if ($type === 'SINGLE' && isset($sample['raw_values']['single_value'])) {
                    $rows[] = [$displayName, 'Single', $sampleIndex, $sample['raw_values']['single_value']];
                }
                
                if ($type === 'BEFORE_AFTER' && isset($sample['raw_values']['before_after_value'])) {
                    $beforeAfter = $sample['raw_values']['before_after_value'];
                    if (isset($beforeAfter['before'])) {
                        $rows[] = [$displayName, 'Before', $sampleIndex, $beforeAfter['before']];
                    }
                    if (isset($beforeAfter['after'])) {
                        $rows[] = [$displayName, 'After', $sampleIndex, $beforeAfter['after']];
                    }
                }
                
                // Processed values (pre-processing formulas)
                if (!empty($sample['processed_values'])) {
                    foreach ($sample['processed_values'] as $formulaName => $value) {
                        $rows[] = [$displayName, 'Pre Processing Formula', $sampleIndex, $value];
                    }
                }
            }
        }
        
        // 2. Variables (FIXED/MANUAL/FORMULA)
        // Note: Variables sudah di-process saat measurement, perlu extract dari variable_values
        
        // 3. Aggregation/Joint results
        if ($evaluationType === 'JOINT' && !empty($item['joint_results'])) {
            foreach ($item['joint_results'] as $jointResult) {
                if ($jointResult['is_final_value']) {
                    $rows[] = [$displayName, 'Aggregation', 1, $jointResult['value']];
                } else {
                    $rows[] = [$displayName, 'Pre Processing Formula', '-', $jointResult['value']];
                }
            }
        } else if (isset($item['final_value'])) {
            $rows[] = [$displayName, 'Aggregation', 1, $item['final_value']];
        }
    }
    
    return $rows;
}
```

---

## ✅ Checklist Implementation

- [ ] Install dependencies (PhpSpreadsheet, DomPDF)
- [ ] Buat migration `report_master_files`
- [ ] Buat Model `ReportMasterFile`
- [ ] Buat `ReportController`
- [ ] Implement filter endpoints (quarters, products, batch-numbers)
- [ ] Implement GET report data endpoint
- [ ] Implement POST upload master file endpoint
- [ ] Implement GET download Excel endpoint (dengan role check)
- [ ] Implement GET download PDF endpoint (dengan role check)
- [ ] Buat helper/service untuk transform data ke Excel format
- [ ] Buat helper/service untuk merge data ke master Excel file
- [ ] Buat helper/service untuk convert Excel sheets ke PDF
- [ ] Test semua flow (filter → upload → download)
- [ ] Test role-based access (Admin/SuperAdmin vs Operator)

---

## 🚨 Edge Cases & Considerations

1. **Jika master file tidak punya sheet "raw_data"**:
   - Buat sheet baru dengan nama "raw_data"
   - Atau return error: "Master file harus memiliki sheet 'raw_data'"

2. **Jika batch number tidak ditemukan**:
   - Return empty result atau error message

3. **Jika measurement_results kosong/null**:
   - Excel hanya berisi header, tidak ada data
   - Atau return message: "Tidak ada data measurement untuk batch ini"

4. **File size limit**:
   - Set max upload size (misal: 10MB) untuk master Excel file

5. **Storage management**:
   - Simpan master files di `storage/app/reports/master_files/`
   - Cleanup old files secara berkala (optional)

6. **Security**:
   - Validate file type (hanya .xlsx, .xls)
   - Validate sheet names (prevent malicious sheet names)
   - Sanitize filename sebelum save

---

## 📝 Notes

- Format Excel menggunakan PhpSpreadsheet untuk read/write
- PDF generation menggunakan DomPDF untuk convert HTML table ke PDF
- Untuk multi-sheet Excel → PDF, perlu convert sheet per sheet
- Operator mendapatkan PDF terpisah per sheet (bisa di-zip jika banyak sheets)

