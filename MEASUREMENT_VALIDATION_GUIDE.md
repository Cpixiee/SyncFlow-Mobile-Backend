# 🎯 Measurement Flow & Jejak Detection - Complete Guide

## 🆕 **What's New - December 2025**

### **Summary of Changes**

Sistem measurement flow telah di-update dengan mekanisme **"Jejak" (Last Check Data)** untuk mencegah data yang invalid masuk ke database.

---

### **📊 Changes Overview**

#### **1. Database Schema Changes**
- ✅ **Added:** Column `last_check_data` (JSON) di table `product_measurements`
- **Purpose:** Store snapshot hasil terakhir dari `/samples/check` untuk validation

#### **2. API Behavior Changes**

**A. `POST /samples/check`**
- ✅ **NEW:** Sekarang save hasil ke `last_check_data` (jejak)
- ❌ **Unchanged:** Tetap TIDAK save ke `measurement_results`
- **Impact:** Jejak digunakan untuk comparison saat `save-progress`

**B. `POST /save-progress` - CRITICAL CHANGE!**
- ✅ **NEW:** Validate data terhadap jejak sebelum save
- ✅ **NEW:** Return **400 Bad Request** jika ada validation warnings
- ❌ **OLD BEHAVIOR:** Save data dulu, return warnings setelah save (SALAH!)
- ✅ **NEW BEHAVIOR:** Check warnings dulu, jika ada → STOP & return error (BENAR!)

**Comparison:**
```
OLD (WRONG):
1. Merge new data
2. Detect warnings
3. Save to DB ✅ ← DATA SALAH MASUK!
4. Return 200 OK with warnings

NEW (CORRECT):
1. Merge new data
2. Detect warnings
3. If warnings exist → Return 400 Bad Request ❌
4. If no warnings → Save to DB ✅
```

**C. `POST /submit`**
- ✅ **NEW:** Apply same validation logic as `save-progress`

---

#### **3. New Validation System**

**Two-Level Detection:**

**Level 1 - CRITICAL:**
- **What:** Raw data berubah tapi belum di-validate ulang
- **How:** Compare new samples vs jejak (`last_check_data`)
- **Result:** Return 400 dengan warning level "CRITICAL"

**Level 2 - WARNING:**
- **What:** Dependencies berubah tapi dependent item belum di-update
- **How:** Extract dependencies dari formulas, check if any changed
- **Result:** Return 400 dengan warning level "WARNING"

**Chain Dependencies:**
- ✅ Automatic detection untuk transitive dependencies
- ✅ Example: `thickness_a` → `room_temp` → `fix_temp`
- ✅ Unlimited depth detection dengan iterative checking

---

#### **4. Files Modified**

**Backend:**
1. ✅ `database/migrations/2025_12_06_212507_add_last_check_data_to_product_measurements_table.php` (NEW)
   - Add `last_check_data` column

2. ✅ `app/Models/ProductMeasurement.php`
   - Added `last_check_data` to `$fillable`
   - Added `last_check_data` to `$casts` (as array)

3. ✅ `app/Http/Controllers/Api/V1/ProductMeasurementController.php`
   - **`checkSamples()`**: Added `saveLastCheckData()` call
   - **`saveProgress()`**: Added validation logic, return 400 if warnings
   - **`validateDependencies()`**: Enhanced with iterative loop for chain detection
   - **NEW `saveLastCheckData()`**: Helper method to save jejak
   - **NEW `findMeasurementItemByVariableName()`**: Resolve variable names to items

**Documentation:**
4. ✅ `MEASUREMENT_VALIDATION_GUIDE.md` (NEW - Consolidated)
   - Complete flow explanation
   - Payload & response examples
   - Test scenarios
   - Chain dependencies

5. ✅ `openapi.yaml`
   - Updated `/samples/check` with detailed request/response examples
   - Updated `/save-progress` with success & error response examples
   - Added 400 error examples for validation failures

**Deleted (Consolidated):**
6. ❌ `MEASUREMENT_FLOW_FIX.md` (merged to MEASUREMENT_VALIDATION_GUIDE.md)
7. ❌ `JEJAK_DETECTION_LOGIC.md` (merged)
8. ❌ `POSTMAN_TEST_GUIDE.md` (merged)
9. ❌ `CHAIN_DEPENDENCIES_GUIDE.md` (merged)

---


#### **6. Migration Required**

```bash
# Run this command to add last_check_data column
php artisan migrate
```

**SQL executed:**
```sql
ALTER TABLE product_measurements 
ADD COLUMN last_check_data JSON NULL;
```

---

## 📋 **Overview**

Sistem ini menggunakan **"Jejak" (Last Check Data)** untuk mendeteksi perubahan raw data dan dependencies, memastikan data yang disimpan ke database selalu valid dan ter-validate.

---

## 🔑 **Key Concepts**

### **1. Jejak (Last Check Data)**
- **What:** Snapshot hasil terakhir dari `/samples/check`
- **Stored in:** Column `last_check_data` (JSON) di table `product_measurements`
- **Purpose:** Reference untuk comparison saat `save-progress`

### **2. Three Endpoints**
1. **`/samples/check`** - Validate & calculate (NO save to DB)
2. **`/save-progress`** - Save to DB (WITH validation)
3. **`/submit`** - Final submit (WITH validation)

### **3. Two-Level Validation**
- **Level 1 (CRITICAL):** Raw data changed but not re-checked
- **Level 2 (WARNING):** Dependencies changed but dependent item not updated

### **4. Chain Dependencies**
- Automatically detect transitive dependencies
- Example: thickness_a → room_temp → fix_temp

---

## 🗄️ **Database Schema**

### **New Field: `last_check_data`**

```sql
ALTER TABLE product_measurements 
ADD COLUMN last_check_data JSON NULL;
```

**Structure:**
```json
{
  "last_check_data": {
    "thickness_a": {
      "samples": [
        {"sample_index": 1, "status": true, "single_value": 10}
      ],
      "status": true,
      "variable_values": [],
      "checked_at": "2025-12-06T10:00:00.000000Z"
    },
    "room_temp": {
      "samples": [...],
      "variable_values": [
        {"name": "CROSS_SECTION", "value": 10.0}
      ],
      "checked_at": "2025-12-06T10:05:00.000000Z"
    }
  }
}
```

---

## 📊 **Complete Flow**

### **Scenario: Normal Flow (No Changes)**

```
Step 1: User measure thickness_a [10,10,10]
POST /samples/check
→ Backend: calculate & validate
→ Backend: save to last_check_data ✅
→ Response: {status: true, samples: [...]}

Step 2: User save-progress (data sama)
POST /save-progress
→ Backend: compare new [10,10,10] vs jejak [10,10,10]
→ No change detected ✅
→ Backend: SAVE to DB ✅
→ Response: 200 OK
```

---

### **Scenario: Raw Data Changed (Belum Re-check)**

```
Step 1: User measure thickness_a [10,10,10]
POST /samples/check (10:00 AM)
→ Jejak saved: [10,10,10] ✅

Step 2: User ganti raw data → [50,10,30] (di FE)
(User SKIP /samples/check!)

Step 3: User save-progress (10:15 AM)
POST /save-progress
Body: {
  "measurement_results": [{
    "measurement_item_name_id": "thickness_a",
    "samples": [
      {"sample_index": 1, "single_value": 50},  // CHANGED!
      {"sample_index": 2, "single_value": 10},
      {"sample_index": 3, "single_value": 30}   // CHANGED!
    ]
  }]
}

Backend Process:
1. Compare new vs jejak
   Jejak: [10,10,10]
   New:   [50,10,30]
   → CHANGED ✅

2. Check timestamp
   Last checked: 10:00 AM
   Now: 10:15 AM (15 min > 5 min)
   → TOO OLD ❌

3. Decision: CRITICAL WARNING!

Response: 400 Bad Request
{
  "http_code": 400,
  "message": "Tidak dapat menyimpan progress karena ada data yang perlu di-validate ulang: 1 item dengan raw data berubah belum di-check ulang",
  "error_id": "VALIDATION_REQUIRED",
  "data": {
    "warnings": [{
      "measurement_item_name_id": "thickness_a",
      "level": "CRITICAL",
      "reason": "Raw data berubah dari hasil check terakhir tetapi belum di-validate ulang",
      "last_check_values": [10, 10, 10],
      "current_values": [50, 10, 30],
      "type": "RAW_DATA_CHANGED_NOT_VALIDATED"
    }],
    "critical_count": 1,
    "dependency_count": 0
  }
}

❌ DATA TIDAK TERSIMPAN KE DB!
```

---

### **Scenario: Chain Dependencies**

```
Setup:
- thickness_a, thickness_b, thickness_c (raw)
- room_temp (depends on thickness_a,b,c)
- final_temp (depends on thickness_a,b)
- fix_temp (depends on room_temp + final_temp)

Flow:
Step 1: Measure all items (initial)
thickness_a: [10,10,10] → check ✅
thickness_b: [10,10,10] → check ✅
thickness_c: [10,10,10] → check ✅
room_temp: check → CROSS_SECTION = 10 ✅
final_temp: check → FINAL_AVG = 10 ✅
fix_temp: check → FIX_VALUE = 30 ✅

Step 2: User ganti thickness_a → [50,10,30] (SKIP re-check)

Step 3: User save-progress
Body: {
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a",
      "samples": [
        {"sample_index": 1, "single_value": 50},
        {"sample_index": 2, "single_value": 10},
        {"sample_index": 3, "single_value": 30}
      ]
    },
    {
      "measurement_item_name_id": "room_temp",
      "variable_values": [{"name": "CROSS_SECTION", "value": 10}]  // OLD!
    },
    {
      "measurement_item_name_id": "final_temp",
      "variable_values": [{"name": "FINAL_AVG", "value": 10}]  // OLD!
    },
    {
      "measurement_item_name_id": "fix_temp",
      "variable_values": [{"name": "FIX_VALUE", "value": 30}]  // OLD!
    }
  ]
}

Response: 400 Bad Request
{
  "http_code": 400,
  "message": "Tidak dapat menyimpan progress karena ada data yang perlu di-validate ulang: 1 item dengan raw data berubah belum di-check ulang, 3 item terpengaruh perubahan dependency",
  "error_id": "VALIDATION_REQUIRED",
  "data": {
    "warnings": [
      {
        "measurement_item_name_id": "thickness_a",
        "level": "CRITICAL",
        "reason": "Raw data berubah...",
        "last_check_values": [10, 10, 10],
        "current_values": [50, 10, 30]
      },
      {
        "measurement_item_name_id": "room_temp",
        "level": "WARNING",
        "reason": "Item ini bergantung pada thickness_a yang telah berubah...",
        "dependencies_changed": ["thickness_a"]
      },
      {
        "measurement_item_name_id": "final_temp",
        "level": "WARNING",
        "reason": "Item ini bergantung pada thickness_a yang telah berubah...",
        "dependencies_changed": ["thickness_a"]
      },
      {
        "measurement_item_name_id": "fix_temp",
        "level": "WARNING",
        "reason": "Item ini bergantung pada room_temp, final_temp...",
        "dependencies_changed": ["room_temp", "final_temp"]
      }
    ],
    "critical_count": 1,
    "dependency_count": 3
  }
}

❌ DATA TIDAK TERSIMPAN KE DB!
```

---

## ✅ **Fix Process**

```
Step 1: Re-check thickness_a with new data
POST /samples/check
Body: {
  "measurement_item_name_id": "thickness_a",
  "samples": [
    {"sample_index": 1, "single_value": 50},
    {"sample_index": 2, "single_value": 10},
    {"sample_index": 3, "single_value": 30}
  ]
}
→ Jejak updated ✅

Step 2: Re-check room_temp
POST /samples/check
Body: {"measurement_item_name_id": "room_temp", ...}
→ CROSS_SECTION recalculated = 30 ✅
→ Jejak updated ✅

Step 3: Re-check final_temp
→ FINAL_AVG recalculated = 30 ✅

Step 4: Re-check fix_temp
→ FIX_VALUE recalculated = 70 ✅

Step 5: Save-progress
POST /save-progress (with updated data)
→ All data match jejak ✅
→ No warnings ✅
→ DATA TERSIMPAN KE DB ✅

Response: 200 OK
{
  "http_code": 200,
  "message": "Progress saved successfully",
  "data": {
    "measurement_id": "MSR-XXX",
    "status": "IN_PROGRESS",
    "progress": {...},
    "saved_items": 4,
    "total_saved_items": 4
  }
}
```

---

## 🎯 **Key Points**

### **✅ SAFE to Save:**
- No warnings detected
- All data matches jejak
- Recent checks (< 5 min)

### **❌ NOT SAFE to Save:**
- CRITICAL warnings (raw data changed)
- WARNING (dependencies changed)
- **RESULT: 400 Bad Request, data TIDAK disimpan**

### **Jejak Detection:**
| Scenario | Jejak | New Data | Time Diff | Result |
|----------|-------|----------|-----------|--------|
| Normal flow | [10,10,10] | [10,10,10] | 1 min | ✅ Save |
| Changed & re-checked | [50,10,30] | [50,10,30] | 1 min | ✅ Save |
| Changed NOT re-checked | [10,10,10] | [50,10,30] | 10 min | ❌ Error 400 |
| Dependency changed | thickness_a changed | room_temp not updated | - | ❌ Error 400 |

---

## 🧪 **Test di Postman**

### **Test 1: Normal Flow (Should Success)**

```http
POST {{baseUrl}}/api/v1/product-measurement/samples/check
Authorization: Bearer {{token}}

{
  "measurement_id": "MSR-XXX",
  "measurement_item_name_id": "thickness_a",
  "samples": [
    {"sample_index": 1, "single_value": 10},
    {"sample_index": 2, "single_value": 10},
    {"sample_index": 3, "single_value": 10}
  ]
}

Expected: 200 OK
```

```http
POST {{baseUrl}}/api/v1/product-measurement/save-progress
Authorization: Bearer {{token}}

{
  "measurement_id": "MSR-XXX",
  "measurement_results": [{
    "measurement_item_name_id": "thickness_a",
    "samples": [
      {"sample_index": 1, "single_value": 10},
      {"sample_index": 2, "single_value": 10},
      {"sample_index": 3, "single_value": 10}
    ]
  }]
}

Expected: 200 OK (Data tersimpan)
```

---

### **Test 2: Raw Data Changed (Should Fail)**

```http
POST {{baseUrl}}/api/v1/product-measurement/save-progress
Authorization: Bearer {{token}}

{
  "measurement_id": "MSR-XXX",
  "measurement_results": [{
    "measurement_item_name_id": "thickness_a",
    "samples": [
      {"sample_index": 1, "single_value": 50},
      {"sample_index": 2, "single_value": 10},
      {"sample_index": 3, "single_value": 30}
    ]
  }]
}

Expected: 400 Bad Request
{
  "http_code": 400,
  "message": "Tidak dapat menyimpan progress...",
  "error_id": "VALIDATION_REQUIRED",
  "data": {
    "warnings": [
      {
        "measurement_item_name_id": "thickness_a",
        "level": "CRITICAL",
        "reason": "Raw data berubah dari hasil check terakhir tetapi belum di-validate ulang"
      }
    ],
    "critical_count": 1,
    "dependency_count": 0
  }
}
```

---

## 🚀 **Setup & Deployment**

### **1. Run Migration**
```bash
php artisan migrate
```

### **2. Verify Database**
```sql
SELECT 
  measurement_id,
  JSON_EXTRACT(last_check_data, '$.thickness_a') as jejak
FROM product_measurements;
```

### **3. Test Flow**
1. Hit `/samples/check` → verify jejak saved
2. Hit `/save-progress` (same data) → should save ✅
3. Hit `/save-progress` (changed data) → should fail ❌
4. Re-hit `/samples/check` (new data) → jejak updated
5. Hit `/save-progress` → should save ✅

---

## 📖 **API Summary**

### **POST /samples/check**
- **Purpose:** Validate & calculate
- **Side Effect:** Save to `last_check_data` (jejak)
- **DB Changes:** Update `last_check_data` only
- **Returns:** Calculation result

### **POST /save-progress**
- **Purpose:** Save progress to DB
- **Validation:** Check against jejak
- **DB Changes:** 
  - ✅ Update `measurement_results` (if valid)
  - ❌ NO changes (if invalid)
- **Returns:** 
  - 200 OK (saved)
  - 400 Bad Request (validation failed)

### **POST /submit**
- **Purpose:** Final submit
- **Validation:** Same as save-progress
- **DB Changes:** Set status to COMPLETED

---

## ⚠️ **Important Behavior Changes**

### **Before (OLD - WRONG!):**
```
save-progress:
1. Detect warnings ⚠️
2. Save to DB ✅ ← DATA SALAH MASUK!
3. Return warnings
```

### **After (NEW - CORRECT!):**
```
save-progress:
1. Detect warnings ⚠️
2. If warnings exist → STOP! Return 400 ❌
3. If no warnings → Save to DB ✅
```

---

---

## 📱 **For Flutter/Frontend Developers**

### **Complete API Flow & Payloads**

#### **1. Create Product with Measurement Items**

```http
POST /api/v1/products
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "Test Product A",
    "ref_spec_number": "SPEC-001",
    "color": "Blue",
    "size": "Large"
  },
  "measurement_points": [
    {
      "measurement_item_name_id": "thickness_a",
      "measurement_item_name": "Thickness A",
      "evaluation_type": "SINGLE_VALUE",
      "setup": {
        "source": "MANUAL",
        "name_id": "thickness_a"
      },
      "rule_evaluation": {
        "rule": "RANGE",
        "min": 5,
        "max": 15
      },
      "variables": []
    },
    {
      "measurement_item_name_id": "thickness_b",
      "measurement_item_name": "Thickness B",
      "evaluation_type": "SINGLE_VALUE",
      "setup": {
        "source": "MANUAL",
        "name_id": "thickness_b"
      },
      "rule_evaluation": {
        "rule": "RANGE",
        "min": 5,
        "max": 15
      },
      "variables": []
    },
    {
      "measurement_item_name_id": "thickness_c",
      "measurement_item_name": "Thickness C",
      "evaluation_type": "SINGLE_VALUE",
      "setup": {
        "source": "MANUAL",
        "name_id": "thickness_c"
      },
      "rule_evaluation": {
        "rule": "RANGE",
        "min": 5,
        "max": 15
      },
      "variables": []
    },
    {
      "measurement_item_name_id": "room_temp",
      "measurement_item_name": "Room Temperature",
      "evaluation_type": "SINGLE_VALUE",
      "setup": {
        "source": "MANUAL",
        "name_id": "room_temp"
      },
      "rule_evaluation": {
        "rule": "RANGE",
        "min": 20,
        "max": 30
      },
      "variables": [
        {
          "name": "CROSS_SECTION",
          "type": "FORMULA",
          "formula": "(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3"
        }
      ]
    }
  ]
}
```

**Response (201 Created):**
```json
{
  "http_code": 201,
  "message": "Product created successfully",
  "error_id": null,
  "data": {
    "product_id": "PRD-ABC123",
    "basic_info": {...},
    "measurement_points": [...]
  }
}
```

---

#### **2. Create Product Measurement (Monthly Target)**

```http
POST /api/v1/product-measurement
Authorization: Bearer {token}
```

**Request:**
```json
{
  "product_id": "PRD-ABC123",
  "batch_number": "BATCH-20251206-001",
  "measurement_type": "FULL_MEASUREMENT",
  "sample_count": 5,
  "measured_at": "2025-12-06T10:00:00"
}
```

**Response:**
```json
{
  "http_code": 201,
  "data": {
    "measurement_id": "MSR-XYZ789",
    "status": "IN_PROGRESS"
  }
}
```

---

#### **3. Measure Item (Hit /samples/check)**

**⚠️ IMPORTANT:** Setiap kali user input/change data, WAJIB hit endpoint ini!

```http
POST /api/v1/product-measurement/{measurement_id}/samples/check
Authorization: Bearer {token}
```

**Request Example - thickness_a:**
```json
{
  "measurement_item_name_id": "thickness_a",
  "samples": [
    {"sample_index": 1, "single_value": 10},
    {"sample_index": 2, "single_value": 10},
    {"sample_index": 3, "single_value": 10},
    {"sample_index": 4, "single_value": 10},
    {"sample_index": 5, "single_value": 10}
  ]
}
```

**Response (200 OK):**
```json
{
  "http_code": 200,
  "message": "Samples processed successfully",
  "data": {
    "measurement_item_name_id": "thickness_a",
    "status": true,
    "samples": [
      {
        "sample_index": 1,
        "status": true,
        "single_value": 10
      },
      {
        "sample_index": 2,
        "status": true,
        "single_value": 10
      },
      ...
    ],
    "variable_values": []
  }
}
```

**Request Example - room_temp (with formula):**
```json
{
  "measurement_item_name_id": "room_temp",
  "samples": [
    {"sample_index": 1, "single_value": 25},
    {"sample_index": 2, "single_value": 25},
    {"sample_index": 3, "single_value": 25},
    {"sample_index": 4, "single_value": 25},
    {"sample_index": 5, "single_value": 25}
  ]
}
```

**Response (200 OK):**
```json
{
  "http_code": 200,
  "message": "Samples processed successfully",
  "data": {
    "measurement_item_name_id": "room_temp",
    "status": true,
    "samples": [...],
    "variable_values": [
      {
        "name": "CROSS_SECTION",
        "value": 10.0,
        "status": "OK"
      }
    ]
  }
}
```

---

#### **4. Save Progress**

**⚠️ CRITICAL:** Endpoint ini akan return **400 Bad Request** jika ada validation error!

```http
POST /api/v1/product-measurement/{measurement_id}/save-progress
Authorization: Bearer {token}
```

**Request (Valid Save):**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a",
      "status": true,
      "samples": [
        {"sample_index": 1, "single_value": 10},
        {"sample_index": 2, "single_value": 10},
        {"sample_index": 3, "single_value": 10},
        {"sample_index": 4, "single_value": 10},
        {"sample_index": 5, "single_value": 10}
      ],
      "variable_values": []
    },
    {
      "measurement_item_name_id": "room_temp",
      "status": true,
      "samples": [
        {"sample_index": 1, "single_value": 25},
        {"sample_index": 2, "single_value": 25},
        {"sample_index": 3, "single_value": 25},
        {"sample_index": 4, "single_value": 25},
        {"sample_index": 5, "single_value": 25}
      ],
      "variable_values": [
        {"name_id": "CROSS_SECTION", "value": 10.0}
      ]
    }
  ]
}
```

**Response Success (200 OK):**
```json
{
  "http_code": 200,
  "message": "Progress saved successfully",
  "data": {
    "measurement_id": "MSR-XYZ789",
    "status": "IN_PROGRESS",
    "saved_items": 2,
    "total_saved_items": 2,
    "progress": {
      "completed_items": 2,
      "total_items": 4,
      "percentage": 50
    }
  }
}
```

**Response Error (400 Bad Request):**
```json
{
  "http_code": 400,
  "message": "Tidak dapat menyimpan progress karena ada data yang perlu di-validate ulang: 1 item dengan raw data berubah belum di-check ulang",
  "error_id": "VALIDATION_REQUIRED",
  "data": {
    "warnings": [
      {
        "measurement_item_name_id": "thickness_a",
        "level": "CRITICAL",
        "reason": "Raw data berubah dari hasil check terakhir tetapi belum di-validate ulang",
        "action": "Silakan hit endpoint /samples/check untuk item ini terlebih dahulu",
        "last_check_values": [10, 10, 10, 10, 10],
        "current_values": [50, 10, 30, 10, 20],
        "type": "RAW_DATA_CHANGED_NOT_VALIDATED"
      }
    ],
    "critical_count": 1,
    "dependency_count": 0
  }
}
```

---

#### **5. Error Handling (Flutter)**

**Handle 400 Validation Error:**

```dart
try {
  final response = await saveProgress(measurementResults);
  
  if (response.statusCode == 200) {
    // Success
    showSuccess("Data saved successfully");
  }
  
} catch (e) {
  if (e is DioError && e.response?.statusCode == 400) {
    final data = e.response?.data['data'];
    final warnings = data['warnings'] as List;
    
    // Show error dialog
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Validation Required'),
        content: Column(
          children: [
            Text('Cannot save progress. Please re-check:'),
            ...warnings.map((w) => ListTile(
              leading: Icon(
                w['level'] == 'CRITICAL' 
                  ? Icons.error 
                  : Icons.warning,
                color: w['level'] == 'CRITICAL' 
                  ? Colors.red 
                  : Colors.orange,
              ),
              title: Text(w['measurement_item_name_id']),
              subtitle: Text(w['reason']),
            )).toList(),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              // Navigate to re-check screen
              navigateToReCheck(warnings);
            },
            child: Text('Re-check Now'),
          ),
        ],
      ),
    );
  }
}
```

---

#### **6. Complete Flow Example**

**Scenario: User measure thickness_a, then change data without re-check**

```
Step 1: User input thickness_a [10,10,10,10,10]
  → Hit /samples/check ✅
  → Store response in state

Step 2: User click "Save Progress"
  → Hit /save-progress ✅
  → Success: Data saved

Step 3: User change thickness_a to [50,10,30,10,20]
  → User SKIP /samples/check ❌ (THIS IS WRONG!)

Step 4: User click "Save Progress"
  → Hit /save-progress
  → Get 400 Bad Request ❌
  → Show error: "thickness_a needs re-check"

Step 5: User re-hit "Measure" button
  → Hit /samples/check with new data ✅
  → Get new response

Step 6: User click "Save Progress"
  → Hit /save-progress ✅
  → Success: Data saved
```

---

#### **7. Flutter State Management**

**Recommended State Structure:**

```dart
class MeasurementState {
  String measurementId;
  Map<String, MeasurementItemData> items;
  
  // Track last checked data (jejak)
  Map<String, DateTime> lastCheckedAt;
}

class MeasurementItemData {
  String nameId;
  List<Sample> samples;
  List<VariableValue> variableValues;
  bool status;
  bool needsRecheck; // Flag when data changed
}

// When user changes data
void onDataChanged(String itemNameId, List<Sample> newSamples) {
  state.items[itemNameId].samples = newSamples;
  state.items[itemNameId].needsRecheck = true; // Set flag
  
  // Clear dependent items
  clearDependentItems(itemNameId);
}

// When user hits "Measure" button
Future<void> measureItem(String itemNameId) async {
  final response = await api.checkSamples(
    measurementId: state.measurementId,
    itemNameId: itemNameId,
    samples: state.items[itemNameId].samples,
  );
  
  if (response.success) {
    state.items[itemNameId] = MeasurementItemData(
      nameId: itemNameId,
      samples: response.data.samples,
      variableValues: response.data.variableValues,
      status: response.data.status,
      needsRecheck: false, // Clear flag
    );
    state.lastCheckedAt[itemNameId] = DateTime.now();
  }
}

// Before save-progress
bool canSaveProgress() {
  return !state.items.values.any((item) => item.needsRecheck);
}
```

---

#### **8. API Endpoints Summary**

| Endpoint | Method | Purpose | Save to DB? |
|----------|--------|---------|-------------|
| `/products` | POST | Create product | Yes |
| `/product-measurement` | POST | Create target | Yes |
| `/samples/check` | POST | Validate & calculate | No (only jejak) |
| `/save-progress` | POST | Save progress | Yes (if valid) |
| `/submit` | POST | Final submit | Yes (if valid) |

---

## 📋 **Summary - What You Need to Know**

### **For Backend Developers:**

**Changed Files:**
1. Migration: `2025_12_06_212507_add_last_check_data_to_product_measurements_table.php`
2. Model: `ProductMeasurement.php` → added `last_check_data` field
3. Controller: `ProductMeasurementController.php` → validation logic
4. OpenAPI: `openapi.yaml` → updated with detailed examples

**Key Changes:**
- ✅ Added `last_check_data` column (JSON)
- ✅ `/samples/check` saves jejak automatically
- ✅ `/save-progress` validates before save (returns 400 if invalid)
- ✅ Chain dependencies auto-detected

**To Deploy:**
```bash
php artisan migrate
```

---

### **For Frontend Developers:**

**⚠️ BREAKING CHANGE:**
- `/save-progress` sekarang bisa return **400 Bad Request**
- Harus update error handling untuk handle validation failures

**New Error Response Structure:**
```json
{
  "http_code": 400,
  "error_id": "VALIDATION_REQUIRED",
  "message": "Tidak dapat menyimpan progress...",
  "data": {
    "warnings": [
      {
        "measurement_item_name_id": "thickness_a",
        "level": "CRITICAL",  // or "WARNING"
        "reason": "...",
        "action": "Silakan hit endpoint /samples/check...",
        "last_check_values": [10, 10, 10],
        "current_values": [50, 10, 30],
        "dependencies_changed": ["thickness_a"],
        "type": "RAW_DATA_CHANGED_NOT_VALIDATED"  // or "DEPENDENCY_CHANGED"
      }
    ],
    "critical_count": 1,
    "dependency_count": 0
  }
}
```

**Flow Changes:**
```
OLD:
1. User hit /save-progress
2. Always get 200 OK (even if data salah)
3. Check warnings (optional)

NEW:
1. User hit /save-progress
2. Get 200 OK (data valid & saved) OR 400 Bad Request (invalid)
3. If 400 → show error, prompt user to re-check
4. User must re-hit /samples/check for affected items
5. User can try /save-progress again
```

**Reference:**
- API docs: `openapi.yaml`
- Complete guide: `MEASUREMENT_VALIDATION_GUIDE.md`

---

### **For QA/Testing:**

**Test Scenarios:**

**✅ Happy Path:**
1. Hit `/samples/check` → 200 OK
2. Hit `/save-progress` (same data) → 200 OK ✅

**❌ Raw Data Changed:**
1. Hit `/samples/check` with [10,10,10] → 200 OK
2. Hit `/save-progress` with [50,10,30] → **400 Bad Request** ❌
3. Hit `/samples/check` with [50,10,30] → 200 OK
4. Hit `/save-progress` with [50,10,30] → 200 OK ✅

**❌ Chain Dependencies:**
1. Measure thickness_a, room_temp, fix_temp → all OK
2. Change thickness_a (skip re-check)
3. Hit `/save-progress` → **400 Bad Request** with 3+ warnings ❌
   - CRITICAL: thickness_a
   - WARNING: room_temp (depends on thickness_a)
   - WARNING: fix_temp (depends on room_temp)

**Expected Results:**
- Data hanya tersimpan kalau tidak ada warnings
- Warnings harus jelas menunjukkan item mana yang perlu di-check ulang

---

## 🧪 **Unit Tests**

### **Test File:** `tests/Feature/MeasurementJejahValidationTest.php`

**Run Tests:**
```bash
# Run all jejak validation tests
php artisan test --filter MeasurementJejahValidationTest

# Run specific test
php artisan test --filter test_save_progress_fails_when_raw_data_changed_without_recheck
```

**Test Coverage:**

| Test | Description | Expected Result |
|------|-------------|-----------------|
| `test_samples_check_saves_jejak_to_last_check_data` | Verify /samples/check saves to last_check_data | Jejak tersimpan di DB |
| `test_save_progress_succeeds_when_data_matches_jejak` | Save with same data as jejak | 200 OK, data saved |
| `test_save_progress_fails_when_raw_data_changed_without_recheck` | Save with changed data (no re-check) | 400 Bad Request, CRITICAL warning |
| `test_save_progress_succeeds_after_recheck_changed_data` | Re-check then save changed data | 200 OK, data saved |
| `test_save_progress_detects_dependency_changes` | Change thickness_a, old room_temp | 400 with 2 warnings (CRITICAL + WARNING) |
| `test_save_progress_detects_chain_dependencies` | Change thickness_a affects room_temp, final_temp, fix_temp | 400 with multiple warnings |
| `test_jejak_persists_across_multiple_checks` | Multiple /samples/check calls | Jejak updated each time |
| `test_no_warnings_when_all_data_valid` | Valid flow | 200 OK, no warnings |

**Test Assertions:**
- ✅ Jejak saved after /samples/check
- ✅ Save succeeds when data matches jejak
- ✅ Save fails (400) when data changed without re-check
- ✅ CRITICAL warning includes last_check_values and current_values
- ✅ WARNING includes dependencies_changed array
- ✅ Chain dependencies detected automatically
- ✅ Data NOT saved to DB when validation fails
- ✅ Data saved to DB when validation succeeds

---

**Last Updated:** December 6, 2025

