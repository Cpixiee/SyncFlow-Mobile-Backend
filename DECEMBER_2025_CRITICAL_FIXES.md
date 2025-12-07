# 🔧 Critical Fixes - December 2025

**Date:** December 8, 2025  
**Version:** 1.2.0  
**Test Status:** ✅ All Tests Passed

---

## 📋 Issues Fixed

### 🐛 **Issue #1: Status NEED_TO_MEASURE Setelah Set Batch Number**

**❌ PROBLEM:**
Setelah user set batch number, status product measurement langsung berubah menjadi `NEED_TO_MEASURE`, padahal harusnya masih di `ONGOING`.

**Behavior Yang Benar:**
- **TODO** → Measurement baru dibuat (status: PENDING)
- **ONGOING** → Setelah set batch number (status: IN_PROGRESS) dan belum pernah submit
- **NEED_TO_MEASURE** → Setelah pernah submit dan ada data NG (perlu diukur ulang)
- **OK/NG** → Setelah submit final (status: COMPLETED)

**✅ SOLUTION:**
Update logic status untuk cek apakah measurement pernah submit dengan hasil NG. Sekarang sistem akan:
1. Cek apakah measurement pernah di-submit (ada `measured_at`)
2. Cek apakah ada sample/result yang NG
3. Jika pernah submit dan ada NG → `NEED_TO_MEASURE`
4. Jika belum pernah submit → `ONGOING`

**Files Changed:**
- `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

---

### 🐛 **Issue #2: Joint Setting Formula Value NULL**

**❌ PROBLEM:**
Ketika check samples dengan formula `avg(single_value)`, value di `joint_setting_formula_values` hasilnya `null`, padahal data single_value sudah ada.

**Payload yang Dikirim:**
```json
{
  "measurement_item_name_id": "inside_diameter",
  "samples": [
    { "sample_index": 1, "single_value": 14 },
    { "sample_index": 2, "single_value": 14 },
    { "sample_index": 3, "single_value": 14 },
    { "sample_index": 4, "single_value": 14 },
    { "sample_index": 5, "single_value": 14 }
  ]
}
```

**Response (BEFORE - BUG):**
```json
{
  "joint_setting_formula_values": [
    {
      "name": "avg",
      "formula": "avg(single_value)",
      "is_final_value": true,
      "value": null  // ❌ NULL!
    }
  ]
}
```

**Root Cause:**
Function `evaluateJointItem()` hanya mengambil data dari `pre_processing_formula_values`, tidak mengambil **raw values** (`single_value`, `before`, `after`) dari samples.

Formula `avg(single_value)` butuh akses ke raw `single_value` array dari semua samples.

**✅ SOLUTION:**
Update function `evaluateJointItem()` untuk:
1. Extract **raw values** dari samples (`single_value`, `before`, `after`)
2. Set raw values sebagai variables dalam MathExecutor
3. Formula `avg(single_value)` bisa akses array `[14, 14, 14, 14, 14]`
4. Calculate average correctly → hasil `14`

**Response (AFTER - FIXED):**
```json
{
  "joint_setting_formula_values": [
    {
      "name": "avg",
      "formula": "avg(single_value)",
      "is_final_value": true,
      "value": 14  // ✅ CORRECT!
    }
  ]
}
```

**Files Changed:**
- `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

---

### 🐛 **Issue #3: Name Validation & Auto-Generate name_id**

**❌ PROBLEM:**
1. Frontend & Backend perlu sinkron untuk auto-generate `name_id` dari `name`
2. Validation rules untuk nama harus jelas dan konsisten
3. Error validation kurang descriptive

**Requirements:**
1. **name_id tidak perlu dikirim** dari FE, akan auto-generated di BE
2. **Format name_id:** `name.toLowerCase().toSnakeCase()`
3. **Validation rules:**
   - Harus lowercase
   - Dimulai dengan huruf (a-z)
   - Bisa mengandung angka dan underscore
   - **TIDAK boleh ada spasi**
   - **TIDAK boleh uppercase**

---

### ⚠️ **PENTING: Perbedaan `name` vs `name_id`**

| Field | Purpose | Rules | Example |
|-------|---------|-------|---------|
| **`name`** | Display name / Label untuk user | ✅ **BEBAS** - Boleh spasi, uppercase, special chars | `"Inside Diameter"`, `"Room Temperature"`, `"AVG Value 1"` |
| **`name_id`** | Identifier / Variable name untuk formula | ❌ **STRICT** - Lowercase, no space, start with letter | `"inside_diameter"`, `"room_temperature"`, `"avg_value_1"` |

**Kesimpulan:**
- ✅ **`name` BEBAS** - User bisa input apa saja, ini untuk display di UI
- ❌ **`name_id` STRICT** - Auto-generated dari `name`, untuk digunakan di formula
- 🔄 **Conversion otomatis:** `"Inside Diameter"` → `"inside_diameter"`

**✅ SOLUTION:**

#### 1. Update Validation Rules
Semua validasi nama sekarang menggunakan regex: `^[a-z][a-z0-9_]*$`
- Lowercase only
- Start with letter
- Can contain numbers and underscores
- No spaces, no special chars

#### 2. Enhanced Error Messages
Error message sekarang lebih descriptive dengan contoh valid names.

#### 3. Auto-Generate name_id Rules
Function `FormulaHelper::generateNameId()` akan auto-generate jika `name_id` tidak dikirim:
- "Room Temp" → `room_temp`
- "Thickness A" → `thickness_a`
- "Inside Diameter" → `inside_diameter`
- "AVG Value 1" → `avg_value_1`

**Files Changed:**
- `app/Http/Controllers/Api/V1/ProductController.php`

---

## ✅ Validation Rules - Field-Specific

### 📋 **Rules Berlaku Untuk Field Identifier:**
1. ✅ **Measurement Item** (`setup.name_id`) - **AUTO-GENERATED dari `name`**
2. ✅ **Variable Names** (`variables[].name`) - **STRICT RULES**
3. ✅ **Pre-processing Formula Names** (`pre_processing_formulas[].name`) - **STRICT RULES**
4. ✅ **Aggregation Formula Names** (`joint_setting.formulas[].name`) - **STRICT RULES**

### 🆓 **Field Display (BEBAS - No Rules):**
- ✅ **Measurement Item Display Name** (`setup.name`) - **BEBAS**
- ✅ Product Name - **BEBAS**
- ✅ Batch Number - **BEBAS**
- ✅ Notes - **BEBAS**

---

### 📝 **Field `name` (Display Name) vs `name_id` (Identifier)**

| Measurement Item | Field `name` (Display) | Field `name_id` (Auto-generated) |
|------------------|------------------------|----------------------------------|
| **Rules** | ✅ Bebas (spasi, uppercase OK) | ❌ Strict (lowercase, no space) |
| **Example 1** | `"Inside Diameter"` | `"inside_diameter"` |
| **Example 2** | `"Room Temperature (°C)"` | `"room_temperature_c"` |
| **Example 3** | `"AVG Value 1"` | `"avg_value_1"` |
| **Example 4** | `"Before/After Check"` | `"before_after_check"` |
| **Example 5** | `"Thickness-A (mm)"` | `"thickness_a_mm"` |

**FE Action Required:**
- ✅ Kirim `name` dengan format bebas (untuk display)
- ❌ **JANGAN kirim** `name_id` (biarkan BE auto-generate)
- ✅ Tampilkan `name` di UI (user friendly)
- ✅ Gunakan `name_id` untuk reference di formula

---

### 🎯 **Universal Naming Rules**

| Rule | Description | Example Valid ✅ | Example Invalid ❌ |
|------|-------------|------------------|-------------------|
| **Lowercase only** | Harus huruf kecil semua | `thickness_a` | ~~`Thickness_A`~~ |
| **Start with letter** | Dimulai dengan huruf (a-z) | `avg_value` | ~~`1_avg`~~ |
| **Can have numbers** | Boleh ada angka | `thickness_1` | ~~`thickness 1`~~ |
| **Can have underscore** | Boleh ada underscore | `room_temp` | ~~`room-temp`~~ |
| **No spaces** | Tidak boleh spasi | `inside_diameter` | ~~`inside diameter`~~ |
| **No special chars** | Tidak boleh karakter khusus | `avg_value` | ~~`avg@value`~~ |
| **No uppercase** | Tidak boleh huruf besar | `test_result` | ~~`Test_Result`~~ |

**Regex Pattern:** `^[a-z][a-z0-9_]*$`

---

### ✅ **Valid Name Examples (All Levels)**

```
✅ thickness_a
✅ inside_diameter  
✅ room_temp
✅ avg_value
✅ measurement_1
✅ outer_dia_before
✅ test_result_2
✅ min_val
✅ max_val
✅ calculated_range
✅ temp_celsius
✅ weight_kg
✅ pressure_bar
```

---

### ❌ **Invalid Name Examples**

| Invalid Name | Error Reason | Should Be |
|--------------|--------------|-----------|
| `Thickness_A` | Uppercase letters | `thickness_a` |
| `thickness A` | Contains space | `thickness_a` |
| `1_thickness` | Starts with number | `thickness_1` |
| `thickness-a` | Contains dash | `thickness_a` |
| `thickness@a` | Special character | `thickness_a` |
| `THICKNESS_A` | All uppercase | `thickness_a` |
| `Avg Value` | Space + uppercase | `avg_value` |
| `test.value` | Contains dot | `test_value` |
| `min-max` | Contains dash | `min_max` |
| `#value` | Starts with special char | `value` |

---

## 🧪 Testing Payloads - Name Validation

### **📌 IMPORTANT: Field `name` vs `name_id`**

```
┌──────────────────────────────────────────────────────────────┐
│  Field "name" (Display Name)                                 │
│  ✅ BOLEH SPASI, UPPERCASE, SPECIAL CHARS                   │
│  → Untuk display di UI, user-friendly                       │
│                                                               │
│  Field "name_id" (Identifier)                                │
│  ❌ STRICT: lowercase, no space, start with letter          │
│  → Untuk digunakan di formula, auto-generated dari name     │
└──────────────────────────────────────────────────────────────┘
```

---

### **Test 1: Measurement Item - Display Name BEBAS ✅**

**Endpoint:**
```
POST /api/v1/products
Authorization: Bearer {token}
```

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Inside Diameter (mm)",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "setup": {
          "name": "Inside Diameter (mm)",
          "name_id": "inside_diameter_mm"
        }
      }
    ]
  }
}
```

**✅ Key Points:**
- `name` bisa pakai spasi, uppercase, special chars: `"Inside Diameter (mm)"`
- `name_id` auto-generated lowercase, no space: `"inside_diameter_mm"`

---

### **Test 2: Multiple Display Names dengan Spasi & Special Chars ✅**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "Complex Product"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Room Temperature (°C)",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "°C",
        "value": 0
      }
    },
    {
      "setup": {
        "name": "Before/After Check",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    },
    {
      "setup": {
        "name": "Thickness-A [Sample]",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "setup": {
          "name": "Room Temperature (°C)",
          "name_id": "room_temperature_c"
        }
      },
      {
        "setup": {
          "name": "Before/After Check",
          "name_id": "before_after_check"
        }
      },
      {
        "setup": {
          "name": "Thickness-A [Sample]",
          "name_id": "thickness_a_sample"
        }
      }
    ]
  }
}
```

**✅ Conversion Examples:**
```
"Room Temperature (°C)"   → "room_temperature_c"
"Before/After Check"      → "before_after_check"
"Thickness-A [Sample]"    → "thickness_a_sample"
"AVG Value 1"             → "avg_value_1"
"Test-Result #2"          → "test_result_2"
```

---

### **Test 3: Variable Names - STRICT RULES ❌ (No Spasi)**

**Payload (VALID):**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Temperature Measurement",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "temp_offset",
          "value": 2.5,
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "room_temp",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "°C",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "setup": {
          "name": "Temperature Measurement",
          "name_id": "temperature_measurement"
        },
        "variables": [
          {
            "name": "temp_offset"
          },
          {
            "name": "room_temp"
          }
        ]
      }
    ]
  }
}
```

**✅ Key Points:**
- Variable `name` HARUS lowercase: `"temp_offset"`, `"room_temp"`
- TIDAK boleh spasi: ~~`"temp offset"`~~
- TIDAK boleh uppercase: ~~`"Temp_Offset"`~~

---

### **Test 4: Variable Name - INVALID (Ada Spasi) ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "temp offset",
          "value": 2.5,
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: 'temp offset'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

---

### **Test 5: Pre-processing Formula Names - STRICT RULES ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Outer Diameter",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "before_value",
          "formula": "before",
          "is_show": true
        },
        {
          "name": "after_value",
          "formula": "after",
          "is_show": true
        },
        {
          "name": "difference",
          "formula": "after_value - before_value",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "difference"
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 0,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "pre_processing_formulas": [
          {
            "name": "before_value"  // ✅ Valid
          },
          {
            "name": "after_value"  // ✅ Valid
          },
          {
            "name": "difference"  // ✅ Valid
          }
        ]
      }
    ]
  }
}
```

---

### **Test 4: Aggregation Formula Name - Valid ✅**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_thickness",
              "formula": "avg(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "min_thickness",
              "formula": "min(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "max_thickness",
              "formula": "max(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "thickness_range",
              "formula": "max_thickness - min_thickness",
              "is_show": true,
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "mm",
        "value": 2
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "evaluation_setting": {
          "joint_setting": {
            "formulas": [
              {
                "name": "avg_thickness"  // ✅ Valid
              },
              {
                "name": "min_thickness"  // ✅ Valid
              },
              {
                "name": "max_thickness"  // ✅ Valid
              },
              {
                "name": "thickness_range"  // ✅ Valid
              }
            ]
          }
        }
      }
    ]
  }
}
```

---

### **Test 5: INVALID - Uppercase in Variable Name ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "Temp_Offset",
          "value": 2.5,
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: 'Temp_Offset'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

---

### **Test 6: INVALID - Space in Pre-processing Formula Name ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "before value",
          "formula": "before",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: 'before value'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

---

### **Test 7: INVALID - Starts with Number in Aggregation ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "1_avg_value",
              "formula": "avg(single_value)",
              "is_show": true,
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: '1_avg_value'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

---

### **Test 8: INVALID - Special Character (Dash) ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "temp-offset",
          "value": 2.5,
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: 'temp-offset'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

---

### **Test 9: INVALID - Duplicate Names in Same Measurement Point ❌**

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "temp_value",
          "value": 2.5,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "temp_value",
          "formula": "single_value",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

**Expected Response (400):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Duplicate names found: temp_value"
  }
}
```

---

### **Test 10: VALID - Multiple Levels with Correct Names ✅**

**Payload (Complex - All Levels):**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COMPLEX_PRODUCT"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Outer Diameter Measurement",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "correction_factor",
          "value": 1.05,
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "temp_celsius",
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "before_corrected",
          "formula": "before * correction_factor",
          "is_show": true
        },
        {
          "name": "after_corrected",
          "formula": "after * correction_factor",
          "is_show": true
        },
        {
          "name": "diameter_change",
          "formula": "after_corrected - before_corrected",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_change",
              "formula": "avg(diameter_change)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "max_change",
              "formula": "max(diameter_change)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "change_range",
              "formula": "max_change - avg_change",
              "is_show": true,
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "mm",
        "value": 1.5
      }
    }
  ]
}
```

**Expected Response (200):**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "setup": {
          "name": "Outer Diameter Measurement",
          "name_id": "outer_diameter_measurement"  // ✅ Auto-generated
        },
        "variables": [
          {
            "name": "correction_factor"  // ✅ Valid
          },
          {
            "name": "temp_celsius"  // ✅ Valid
          }
        ],
        "pre_processing_formulas": [
          {
            "name": "before_corrected"  // ✅ Valid
          },
          {
            "name": "after_corrected"  // ✅ Valid
          },
          {
            "name": "diameter_change"  // ✅ Valid
          }
        ],
        "evaluation_setting": {
          "joint_setting": {
            "formulas": [
              {
                "name": "avg_change"  // ✅ Valid
              },
              {
                "name": "max_change"  // ✅ Valid
              },
              {
                "name": "change_range"  // ✅ Valid
              }
            ]
          }
        }
      }
    ]
  }
}
```

---

## 📊 Summary untuk Frontend Developer

### ✅ **Fields yang BEBAS (Boleh Spasi & Uppercase)**

| Field | Location | Rules | Example |
|-------|----------|-------|---------|
| `setup.name` | Measurement Item Display | ✅ **BEBAS** | `"Inside Diameter (mm)"`, `"Room Temp"` |
| `product_name` | Basic Info | ✅ **BEBAS** | `"CORUTUBE"`, `"Test Product"` |
| `batch_number` | Measurement | ✅ **BEBAS** | `"BATCH-001"`, `"Test Batch"` |
| `notes` | Measurement | ✅ **BEBAS** | `"Sample dari shift pagi"` |
| `issue_name` | Issue | ✅ **BEBAS** | `"Material Defect Found"` |
| `description` | Issue | ✅ **BEBAS** | Any text |

### ❌ **Fields yang STRICT (Lowercase, No Space)**

| Field | Location | Rules | Example Valid | Example Invalid |
|-------|----------|-------|---------------|-----------------|
| `variables[].name` | Variables | ❌ **STRICT** | `"temp_offset"` | ~~`"Temp Offset"`~~ |
| `pre_processing_formulas[].name` | Pre-processing | ❌ **STRICT** | `"before_value"` | ~~`"Before Value"`~~ |
| `joint_setting.formulas[].name` | Aggregation | ❌ **STRICT** | `"avg_thickness"` | ~~`"AVG Thickness"`~~ |

### 🔄 **Field yang AUTO-GENERATED (FE Tidak Perlu Kirim)**

| Field | Location | Auto-generated From | Example |
|-------|----------|---------------------|---------|
| `setup.name_id` | Measurement Item | `setup.name` | `"Inside Diameter"` → `"inside_diameter"` |

---

## 🎯 Frontend Action Items

### ✅ **DO's**

1. **Measurement Item `name`:**
   - ✅ Biarkan user input bebas (spasi, uppercase OK)
   - ✅ Kirim ke BE apa adanya
   - ✅ Tampilkan di UI untuk user-friendly
   
2. **Variable, Formula Names:**
   - ❌ Validasi di FE: lowercase only, no space
   - ❌ Convert otomatis atau show error jika ada uppercase/space
   - ✅ Tampilkan error message jika format salah

3. **name_id:**
   - ❌ **JANGAN kirim** dari FE
   - ✅ Gunakan `name_id` dari BE response untuk formula reference

### ❌ **DON'Ts**

1. ❌ Jangan kirim `name_id` di payload create product
2. ❌ Jangan biarkan user input space di variable/formula names
3. ❌ Jangan biarkan user input uppercase di variable/formula names

---

## 💡 FE Validation Examples

### **Example 1: Validation di Input Variable Name**

```typescript
// FE validation function
function validateVariableName(name: string): boolean {
  const regex = /^[a-z][a-z0-9_]*$/;
  return regex.test(name);
}

// Usage
const varName = userInput; // "Temp Offset"

if (!validateVariableName(varName)) {
  showError("Nama variable harus lowercase, tanpa spasi. Contoh: temp_offset");
  return;
}
```

### **Example 2: Auto-convert atau Show Error**

**Option A: Auto-convert (Recommended)**
```typescript
function sanitizeVariableName(name: string): string {
  return name
    .toLowerCase()
    .replace(/\s+/g, '_')  // Replace spaces with underscore
    .replace(/[^a-z0-9_]/g, '');  // Remove special chars
}

// Usage
const userInput = "Temp Offset";
const sanitized = sanitizeVariableName(userInput); // "temp_offset"
```

**Option B: Show Error**
```typescript
function validateVariableName(name: string): string | null {
  const regex = /^[a-z][a-z0-9_]*$/;
  
  if (!regex.test(name)) {
    if (/[A-Z]/.test(name)) {
      return "Tidak boleh huruf besar. Gunakan lowercase.";
    }
    if (/\s/.test(name)) {
      return "Tidak boleh spasi. Gunakan underscore (_).";
    }
    if (/^[0-9]/.test(name)) {
      return "Harus dimulai dengan huruf, bukan angka.";
    }
    return "Format tidak valid. Gunakan lowercase, angka, dan underscore.";
  }
  
  return null; // Valid
}
```

---

## 🧪 FE Testing Checklist

### **Test Case untuk FE Developer:**

- [ ] User input `"Inside Diameter"` di measurement name → ✅ Diterima
- [ ] User input `"Temp Offset"` di variable name → ❌ Show error atau auto-convert
- [ ] User input `"temp_offset"` di variable name → ✅ Diterima
- [ ] Response dari BE ada `name_id` → ✅ Simpan untuk formula reference
- [ ] Formula reference pakai `name_id` bukan `name` → ✅ Correct
- [ ] Display di UI pakai `name` bukan `name_id` → ✅ User-friendly

---

## 📊 Quick Reference - Name Validation

### ✅ **DO's**
```
✅ Use lowercase letters (a-z)
✅ Start with a letter
✅ Use numbers after first character
✅ Use underscores for separation
✅ Keep names descriptive but concise
```

### ❌ **DON'Ts**
```
❌ Don't use uppercase letters
❌ Don't use spaces
❌ Don't start with numbers
❌ Don't use special characters (@, #, -, ., etc)
❌ Don't use reserved keywords (if, for, while, etc)
```

### 🔄 **Name Conversion Examples**
```
"Room Temperature"     → room_temperature
"Inside Diameter"      → inside_diameter
"AVG Value 1"          → avg_value_1
"Before/After Check"   → before_after_check
"Test-Result"          → test_result
"Thickness_A"          → thickness_a
"OUTER DIA"            → outer_dia
```

---

## 🧪 Testing Payloads

### **Test 1: Create Product dengan Auto-Generated name_id**

**Endpoint:**
```
POST /api/v1/products
Authorization: Bearer {token}
```

**Payload:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "CORUTUBE"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Inside Diameter",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "avg(single_value)",
              "is_show": true,
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 14,
        "tolerance_minus": 0.5,
        "tolerance_plus": 0.5
      }
    }
  ]
}
```

**Expected Response:**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "measurement_points": [
      {
        "setup": {
          "name": "Inside Diameter",
          "name_id": "inside_diameter",  // ✅ Auto-generated!
          "sample_amount": 5,
          "source": "MANUAL",
          "type": "SINGLE",
          "nature": "QUANTITATIVE"
        },
        "evaluation_setting": {
          "joint_setting": {
            "formulas": [
              {
                "name": "avg",
                "formula": "avg(single_value)",
                "is_show": true,
                "is_final_value": true
              }
            ]
          }
        }
      }
    ]
  }
}
```

---

### **Test 2: Check Samples dengan Joint Formula**

**Endpoint:**
```
POST /api/v1/product-measurement/{measurement_id}/check-samples
Authorization: Bearer {token}
```

**Payload:**
```json
{
  "measurement_item_name_id": "inside_diameter",
  "samples": [
    { "sample_index": 1, "single_value": 14 },
    { "sample_index": 2, "single_value": 14.2 },
    { "sample_index": 3, "single_value": 13.8 },
    { "sample_index": 4, "single_value": 14.1 },
    { "sample_index": 5, "single_value": 13.9 }
  ]
}
```

**Expected Response:**
```json
{
  "http_code": 200,
  "message": "Samples processed successfully",
  "data": {
    "variable_values": [],
    "samples": [
      { "sample_index": 1, "single_value": 14 },
      { "sample_index": 2, "single_value": 14.2 },
      { "sample_index": 3, "single_value": 13.8 },
      { "sample_index": 4, "single_value": 14.1 },
      { "sample_index": 5, "single_value": 13.9 }
    ],
    "joint_setting_formula_values": [
      {
        "name": "avg",
        "formula": "avg(single_value)",
        "is_final_value": true,
        "value": 14  // ✅ CALCULATED! (14+14.2+13.8+14.1+13.9)/5 = 14
      }
    ],
    "status": true  // ✅ OK karena 14 dalam range [13.5, 14.5]
  }
}
```

---

### **Test 3: Set Batch Number → Status ONGOING**

**Step 1: Create Measurement**
```
POST /api/v1/product-measurement
Authorization: Bearer {token}

{
  "product_id": "PRD-XXXXXXXX",
  "measurement_type": "FULL_MEASUREMENT",
  "due_date": "2025-12-31"
}
```

**Response:**
```json
{
  "http_code": 201,
  "data": {
    "product_measurement_id": "MSR-YYYYYYYY"
  }
}
```

**Step 2: Get Detail → Status TODO**
```
GET /api/v1/product-measurement?query=PRD-XXXXXXXX
```

**Response:**
```json
{
  "data": [
    {
      "product_measurement_id": "MSR-YYYYYYYY",
      "status": "TODO",  // ✅ Status PENDING → TODO
      "batch_number": null
    }
  ]
}
```

**Step 3: Set Batch Number**
```
POST /api/v1/product-measurement/MSR-YYYYYYYY/set-batch-number

{
  "batch_number": "BATCH-20251208-001"
}
```

**Response:**
```json
{
  "http_code": 200,
  "data": {
    "measurement_id": "MSR-YYYYYYYY",
    "batch_number": "BATCH-20251208-001",
    "status": "IN_PROGRESS"
  }
}
```

**Step 4: Get Detail → Status ONGOING**
```
GET /api/v1/product-measurement?query=PRD-XXXXXXXX
```

**Response:**
```json
{
  "data": [
    {
      "product_measurement_id": "MSR-YYYYYYYY",
      "status": "ONGOING",  // ✅ IN_PROGRESS + belum pernah submit = ONGOING
      "batch_number": "BATCH-20251208-001"
    }
  ]
}
```

---

### **Test 4: Invalid Name Validation**

**Payload (Invalid - Uppercase):**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "TEST"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "Test_Variable",  // ❌ Uppercase!
          "value": 10,
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": { "_skip": true }
    }
  ]
}
```

**Expected Response (400 Error):**
```json
{
  "http_code": 400,
  "message": "Name validation failed",
  "error_id": "NAME_UNIQUENESS_ERROR",
  "data": {
    "measurement_point_0": "Invalid name format: 'Test_Variable'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'"
  }
}
```

**Payload (Valid - Corrected):**
```json
{
  "variables": [
    {
      "type": "FIXED",
      "name": "test_variable",  // ✅ Lowercase!
      "value": 10,
      "is_show": true
    }
  ]
}
```

---

### **Test 5: Formula dengan Multiple Aggregations**

**Create Product:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COMPLEX_TEST"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_val",
              "formula": "avg(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "min_val",
              "formula": "min(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "max_val",
              "formula": "max(single_value)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "range",
              "formula": "max_val - min_val",
              "is_show": true,
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "mm",
        "value": 2
      }
    }
  ]
}
```

**Check Samples:**
```json
{
  "measurement_item_name_id": "thickness_measurement",
  "samples": [
    { "sample_index": 1, "single_value": 10 },
    { "sample_index": 2, "single_value": 11 },
    { "sample_index": 3, "single_value": 9.5 },
    { "sample_index": 4, "single_value": 10.5 },
    { "sample_index": 5, "single_value": 10.2 }
  ]
}
```

**Expected Response:**
```json
{
  "joint_setting_formula_values": [
    {
      "name": "avg_val",
      "formula": "avg(single_value)",
      "is_final_value": false,
      "value": 10.24  // ✅ (10+11+9.5+10.5+10.2)/5
    },
    {
      "name": "min_val",
      "formula": "min(single_value)",
      "is_final_value": false,
      "value": 9.5  // ✅ Min value
    },
    {
      "name": "max_val",
      "formula": "max(single_value)",
      "is_final_value": false,
      "value": 11  // ✅ Max value
    },
    {
      "name": "range",
      "formula": "max_val - min_val",
      "is_final_value": true,
      "value": 1.5  // ✅ 11 - 9.5 = 1.5
    }
  ],
  "status": true  // ✅ OK karena range (1.5) <= 2
}
```

---

## 🎯 Breaking Changes

### ⚠️ **Frontend Adjustments Required**

#### 1. **name_id Optional di Payload**
**BEFORE (Required):**
```json
{
  "setup": {
    "name": "Inside Diameter",
    "name_id": "inside_diameter"  // ❌ Wajib kirim
  }
}
```

**AFTER (Optional):**
```json
{
  "setup": {
    "name": "Inside Diameter"
    // name_id akan auto-generated oleh BE
  }
}
```

**✅ Recommendation:** FE bisa tetap kirim `name_id` jika mau custom, atau biarkan kosong untuk auto-generate.

#### 2. **Name Validation - Lowercase Only**
**BEFORE (Case Insensitive):**
```json
{
  "variables": [
    { "name": "Test_Variable" }  // ✅ Valid (dulu)
  ]
}
```

**AFTER (Lowercase Only):**
```json
{
  "variables": [
    { "name": "test_variable" }  // ✅ Valid (sekarang)
    // { "name": "Test_Variable" }  // ❌ Invalid!
  ]
}
```

**✅ Recommendation:** FE convert semua nama ke lowercase sebelum kirim, atau tampilkan error message jika user input uppercase.

#### 3. **Status Display Logic**
**BEFORE:**
```
PENDING → "ONGOING"
IN_PROGRESS → "NEED_TO_MEASURE"
```

**AFTER:**
```
PENDING → "TODO"
IN_PROGRESS (belum pernah submit) → "ONGOING"
IN_PROGRESS (pernah submit NG) → "NEED_TO_MEASURE"
COMPLETED (OK) → "OK"
COMPLETED (NG) → "NG"
```

**✅ Recommendation:** Update status mapping di FE untuk reflect logic baru.

---

## 📝 Migration Notes

### **No Database Migration Required**
✅ Semua changes adalah **logic fixes only**, tidak ada perubahan database schema.

### **No Breaking API Changes**
✅ API endpoints tetap sama, hanya behavior yang diperbaiki.

### **Backward Compatible**
✅ Frontend yang masih kirim `name_id` tetap akan berfungsi normal.

---

## ✅ Testing Checklist

- [x] Status PENDING → TODO
- [x] Status IN_PROGRESS (belum submit) → ONGOING
- [x] Status IN_PROGRESS (pernah submit NG) → NEED_TO_MEASURE
- [x] Status COMPLETED OK → OK
- [x] Status COMPLETED NG → NG
- [x] Joint formula `avg(single_value)` return correct value
- [x] Joint formula `min(single_value)` return correct value
- [x] Joint formula `max(single_value)` return correct value
- [x] Joint formula chaining (avg_val → range) work correctly
- [x] Name validation reject uppercase
- [x] Name validation reject spaces
- [x] Name validation reject special chars
- [x] Name validation reject start with number
- [x] Auto-generate name_id from name
- [x] Error messages descriptive and helpful

---

## 🚀 Deployment Instructions

### 1. Pull Latest Code
```bash
git pull origin main
```

### 2. Run Tests
```bash
php artisan test
```

Expected: ✅ All tests pass

### 3. Clear Cache (Optional but Recommended)
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 4. Deploy
```bash
# No migration needed
# Just deploy the code changes
```

---

## 📞 Support

Jika ada issue atau pertanyaan terkait fixes ini:
1. Check error message yang descriptive
2. Verify payload format sesuai examples di atas
3. Verify name format lowercase dan sesuai rules
4. Check documentation di `MEASUREMENT_VALIDATION_GUIDE.md`

---

**🎉 Happy Testing!**

