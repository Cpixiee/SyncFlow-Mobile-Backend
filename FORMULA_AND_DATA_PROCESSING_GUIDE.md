# 📐 SyncFlow - Formula & Data Processing Complete Guide

**Version:** 2.0.0  
**Last Updated:** November 23, 2024  
**Target Audience:** Frontend, Backend Developers & QA Team

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Processing Flow](#processing-flow)
3. [Type SINGLE - Raw Data Access](#type-single---raw-data-access)
4. [Type BEFORE_AFTER - Raw Data Access](#type-before_after---raw-data-access)
5. [Step 2: Setup Variables](#step-2-setup-variables)
6. [Step 3: Pre-processing Formulas](#step-3-pre-processing-formulas)
7. [Step 4: Evaluation (OK/NG Determination)](#step-4-evaluation-okng-determination)
8. [Step 5: Aggregation Formulas (Joint Setting)](#step-5-aggregation-formulas-joint-setting)
9. [Database Storage](#database-storage)
10. [Frontend Integration](#frontend-integration)
11. [Complete Examples](#complete-examples)
12. [API Reference](#api-reference)

---

## 🎯 Overview

SyncFlow memiliki sistem processing data yang terstruktur dalam 5 tahap (Step):

```
┌─────────────┐
│   INPUT     │  User/Alat input raw data
│  Raw Data   │  (single_value atau before_after_value)
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   STEP 2    │  Setup Variables (FIXED, MANUAL, FORMULA)
│  Variables  │  Nilai-nilai yang digunakan dalam formula
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   STEP 3    │  Pre-processing Formulas
│Pre-process  │  Formula dijalankan PER SAMPLE (setiap raw data)
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   STEP 4    │  Evaluation
│  OK/NG      │  Tentukan nilai mana yang jadi acuan OK/NG
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   STEP 5    │  Aggregation Formulas (hanya untuk JOINT)
│Aggregation  │  Gabungkan semua sample jadi 1 nilai final
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  DATABASE   │  Semua hasil disimpan dalam JSON
│   Storage   │  (measurement_results field)
└─────────────┘
```

**Key Points:**
- ✅ **Step 3** berjalan **PER SAMPLE** (setiap raw data diproses)
- ✅ **Step 5** berjalan **AGGREGATE** (gabungan semua sample)
- ✅ **Semua hasil disimpan** ke database
- ✅ **Field `is_show`** mengontrol apa yang ditampilkan di Frontend

---

## 🔄 Processing Flow

### Per Sample (PER_SAMPLE Evaluation)

```
Sample 1: raw_data=100 → Step 3 formula → processed_value=10000 → Step 4 evaluate → OK/NG
Sample 2: raw_data=200 → Step 3 formula → processed_value=20000 → Step 4 evaluate → OK/NG
Sample 3: raw_data=300 → Step 3 formula → processed_value=30000 → Step 4 evaluate → OK/NG
                                                                                    ↓
                                                                      Overall: OK if all OK
```

### Joint (JOINT Evaluation)

```
Sample 1: raw_data=100 → Step 3 formula → processed_value=10000 ┐
Sample 2: raw_data=200 → Step 3 formula → processed_value=20000 ├→ Step 5 aggregate
Sample 3: raw_data=300 → Step 3 formula → processed_value=30000 ┘     ↓
                                                                  final_value=15000
                                                                       ↓
                                                                 Step 4 evaluate → OK/NG
```

---

## 📊 Type SINGLE - Raw Data Access

### Data Structure

**Input dari User/Alat:**
```json
{
  "samples": [
    {
      "sample_index": 1,
      "single_value": 100
    },
    {
      "sample_index": 2,
      "single_value": 200
    },
    {
      "sample_index": 3,
      "single_value": 300
    }
  ]
}
```

**Disimpan di Database sebagai:**
```json
{
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {
        "single_value": 100  // ← Raw data tersimpan di sini
      },
      "processed_values": {},
      "variables": {}
    }
  ]
}
```

### Formula Variable Names

Untuk type `SINGLE`, gunakan variable name berikut dalam formula:

| Variable Name | Description | Example Value |
|--------------|-------------|---------------|
| `single_value` | Nilai raw data | `100` |
| `raw_data` | **TIDAK BISA DIPAKAI** | ❌ |

⚠️ **IMPORTANT:** Gunakan `single_value`, bukan `raw_data`

### Formula Examples

```javascript
// ✅ BENAR - menggunakan single_value
"=single_value * 100"           // Result: 10000
"=single_value + 50"            // Result: 150
"=single_value / 2"             // Result: 50

// ❌ SALAH - menggunakan raw_data
"=raw_data * 100"               // ERROR: variable not found
```

### Complete Product Setup Example

```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "type": "SINGLE",           // ← Type SINGLE
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "raw_times_100",
          "formula": "=single_value * 100",  // ← Gunakan single_value
          "is_show": true
        },
        {
          "name": "raw_plus_offset",
          "formula": "=single_value + 10",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true       // true = pakai single_value
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 100,
        "tolerance_minus": 10,
        "tolerance_plus": 10
      }
    }
  ]
}
```

---

## 🔀 Type BEFORE_AFTER - Raw Data Access

### Data Structure

**Input dari User/Alat:**
```json
{
  "samples": [
    {
      "sample_index": 1,
      "before_after_value": {
        "before": 100,
        "after": 95
      }
    },
    {
      "sample_index": 2,
      "before_after_value": {
        "before": 200,
        "after": 190
      }
    }
  ]
}
```

**Disimpan di Database sebagai:**
```json
{
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {
        "before_after_value": {
          "before": 100,     // ← Before value
          "after": 95        // ← After value
        }
      },
      "processed_values": {},
      "variables": {}
    }
  ]
}
```

### Formula Variable Names

Untuk type `BEFORE_AFTER`, sistem **OTOMATIS** pecah object `before_after_value` menjadi 2 variable:

| Variable Name | Description | Example Value |
|--------------|-------------|---------------|
| `before` | Nilai sebelum treatment | `100` |
| `after` | Nilai setelah treatment | `95` |
| `raw_data_before` | **TIDAK BISA DIPAKAI** | ❌ |
| `raw_data_after` | **TIDAK BISA DIPAKAI** | ❌ |

⚠️ **IMPORTANT:** Gunakan `before` dan `after`, bukan `raw_data_before`/`raw_data_after`

### Formula Examples

```javascript
// ✅ BENAR - menggunakan before dan after
"=(before - after) / before * 100"      // Persentase perubahan
"=after * 100"                          // After dikali 100
"=before + after"                       // Jumlah before dan after
"=abs(before - after)"                  // Selisih absolut

// ❌ SALAH - menggunakan raw_data_before/after
"=(raw_data_before - raw_data_after)"   // ERROR: variable not found
"=before_after_value.before"            // ERROR: invalid syntax
```

### Complete Product Setup Example

```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "type": "BEFORE_AFTER",     // ← Type BEFORE_AFTER
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "selisih_persen",
          "formula": "=(before - after) / before * 100",  // ← Gunakan before dan after
          "is_show": true
        },
        {
          "name": "after_times_100",
          "formula": "=after * 100",
          "is_show": true
        },
        {
          "name": "total",
          "formula": "=before + after",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,                      // false = pakai formula result
          "pre_processing_formula_name": "selisih_persen"  // ← Pilih formula ini
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0
      }
    }
  ]
}
```

---

## 🎛️ Step 2: Setup Variables

Variables adalah nilai-nilai yang dapat digunakan dalam formula. Ada 3 tipe:

### Variable Types

| Type | Description | Value Source | Example |
|------|-------------|--------------|---------|
| **FIXED** | Nilai tetap yang ditentukan saat setup product | Dari config | `tolerance = 0.1` |
| **MANUAL** | Nilai yang diinput user saat measurement | User input | `room_temp = 25.5` |
| **FORMULA** | Nilai hasil kalkulasi dari formula | Dihitung otomatis | `avg_value = avg(thickness)` |

### Variable Setup in Product Configuration

```json
{
  "variables": [
    {
      "type": "FIXED",
      "name": "tolerance",
      "value": 0.1,
      "is_show": true      // ← Tampilkan di Frontend
    },
    {
      "type": "MANUAL",
      "name": "room_temp",
      "is_show": true
    },
    {
      "type": "FORMULA",
      "name": "calculated_offset",
      "formula": "=tolerance * 10",
      "is_show": false     // ← Tidak ditampilkan di Frontend
    }
  ]
}
```

### Variable Input During Measurement

```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness",
      "variable_values": [
        {
          "name_id": "room_temp",
          "value": 25.5       // ← User input untuk MANUAL variable
        }
      ],
      "samples": [...]
    }
  ]
}
```

### Using Variables in Formulas

Variables dapat digunakan di:
- ✅ Step 3: Pre-processing formulas
- ✅ Step 5: Aggregation formulas

```javascript
// Pre-processing formula menggunakan variables
"=single_value + tolerance"              // Gunakan FIXED variable
"=single_value * room_temp / 100"        // Gunakan MANUAL variable
"=single_value + calculated_offset"      // Gunakan FORMULA variable
```

### Storage in Database

```json
{
  "samples": [
    {
      "variables": {
        "tolerance": 0.1,              // FIXED
        "room_temp": 25.5,             // MANUAL (user input)
        "calculated_offset": 1.0       // FORMULA (auto-calculated)
      }
    }
  ]
}
```

---

## 🔧 Step 3: Pre-processing Formulas

**Key Concept:** Pre-processing formulas dijalankan **PER SAMPLE** (untuk setiap raw data).

### How It Works

```
Input:
  Sample 1: single_value = 100
  Sample 2: single_value = 200
  Sample 3: single_value = 300

Formula: =single_value * 100

Processing:
  Sample 1: 100 * 100 = 10000
  Sample 2: 200 * 100 = 20000
  Sample 3: 300 * 100 = 30000

Result:
  3 nilai terpisah (PER SAMPLE)
```

### Formula Configuration

```json
{
  "pre_processing_formulas": [
    {
      "name": "formula_1",
      "formula": "=single_value * 100",
      "is_show": true       // ← Show this in Frontend
    },
    {
      "name": "formula_2",
      "formula": "=single_value / 2",
      "is_show": false      // ← Don't show in Frontend
    },
    {
      "name": "formula_3",
      "formula": "=formula_1 + formula_2",  // ← Can reference previous formulas
      "is_show": true
    }
  ]
}
```

### Formula Execution Order

**PENTING:** Formulas dieksekusi **berurutan** (sequential), jadi formula berikutnya bisa menggunakan hasil formula sebelumnya.

```javascript
// Formula 1 (dieksekusi pertama)
"=single_value * 100"          // Result: 10000

// Formula 2 (bisa pakai hasil Formula 1)
"=formula_1 / 2"               // Result: 5000 (menggunakan hasil 10000)

// Formula 3 (bisa pakai hasil Formula 1 dan 2)
"=formula_1 + formula_2"       // Result: 15000
```

### Available Variables in Pre-processing

Dalam pre-processing formula, Anda bisa menggunakan:

1. **Raw data variables**
   - `single_value` (untuk type SINGLE)
   - `before` dan `after` (untuk type BEFORE_AFTER)

2. **Setup variables** (dari Step 2)
   - FIXED variables
   - MANUAL variables (user input)
   - FORMULA variables (calculated)

3. **Previous formula results**
   - Hasil formula sebelumnya dalam array yang sama

4. **Cross-reference measurement items** (advanced)
   - `avg(other_measurement_item_name_id)`

### Example: Type SINGLE

```json
{
  "setup": {
    "type": "SINGLE"
  },
  "variables": [
    {
      "type": "FIXED",
      "name": "multiplier",
      "value": 100
    }
  ],
  "pre_processing_formulas": [
    {
      "name": "raw_times_multiplier",
      "formula": "=single_value * multiplier",
      "is_show": true
    },
    {
      "name": "raw_squared",
      "formula": "=single_value * single_value",
      "is_show": false
    },
    {
      "name": "combined",
      "formula": "=raw_times_multiplier + raw_squared",
      "is_show": true
    }
  ]
}
```

**Processing untuk Sample dengan single_value = 10:**
```
raw_times_multiplier = 10 * 100 = 1000
raw_squared = 10 * 10 = 100
combined = 1000 + 100 = 1100
```

### Example: Type BEFORE_AFTER

```json
{
  "setup": {
    "type": "BEFORE_AFTER"
  },
  "pre_processing_formulas": [
    {
      "name": "selisih_persen",
      "formula": "=(before - after) / before * 100",
      "is_show": true
    },
    {
      "name": "rata_rata",
      "formula": "=(before + after) / 2",
      "is_show": true
    },
    {
      "name": "after_times_100",
      "formula": "=after * 100",
      "is_show": false
    }
  ]
}
```

**Processing untuk Sample dengan before=100, after=95:**
```
selisih_persen = (100 - 95) / 100 * 100 = 5.0
rata_rata = (100 + 95) / 2 = 97.5
after_times_100 = 95 * 100 = 9500
```

### Storage in Database

**SEMUA formula results disimpan** dalam `processed_values`:

```json
{
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {
        "single_value": 10
      },
      "processed_values": {
        "raw_times_multiplier": 1000,    // ← Formula 1 result
        "raw_squared": 100,               // ← Formula 2 result
        "combined": 1100                  // ← Formula 3 result
      }
    }
  ]
}
```

---

## ✅ Step 4: Evaluation (OK/NG Determination)

Step 4 menentukan **nilai mana** yang digunakan untuk evaluasi OK/NG, dan **bagaimana** evaluasi dilakukan.

### Evaluation Types

| Type | Description | When to Use |
|------|-------------|-------------|
| **PER_SAMPLE** | Evaluasi per sample, semua sample harus OK | Quality check per item |
| **JOINT** | Gabungkan semua sample, evaluasi nilai final | Statistical analysis |
| **SKIP_CHECK** | Tidak ada evaluasi OK/NG | Qualitative/informational only |

### Per Sample Evaluation

#### Configuration

```json
{
  "evaluation_type": "PER_SAMPLE",
  "evaluation_setting": {
    "per_sample_setting": {
      "is_raw_data": false,                         // false = use formula result
      "pre_processing_formula_name": "selisih_persen"  // ← Which formula to evaluate
    }
  },
  "rule_evaluation_setting": {
    "rule": "MAX",          // Rules: MIN, MAX, BETWEEN
    "unit": "%",
    "value": 5.0,
    "tolerance_minus": null,
    "tolerance_plus": null
  }
}
```

#### Options for `is_raw_data`

| Value | Description | Evaluated Value |
|-------|-------------|----------------|
| `true` | Gunakan raw data | `single_value` atau nilai dari formula yang dipilih |
| `false` | Gunakan pre-processing formula result | Nilai dari `pre_processing_formula_name` |

#### Evaluation Rules

**Rule: MIN**
```json
{
  "rule": "MIN",
  "unit": "mm",
  "value": 30
}
```
- **OK** jika: `evaluated_value >= 30`
- **NG** jika: `evaluated_value < 30`

**Rule: MAX**
```json
{
  "rule": "MAX",
  "unit": "mm",
  "value": 50
}
```
- **OK** jika: `evaluated_value <= 50`
- **NG** jika: `evaluated_value > 50`

**Rule: BETWEEN**
```json
{
  "rule": "BETWEEN",
  "unit": "mm",
  "value": 40,
  "tolerance_minus": 5,
  "tolerance_plus": 5
}
```
- **OK** jika: `35 <= evaluated_value <= 45` (40 ± 5)
- **NG** jika: nilai di luar range

#### Example: Using Raw Data

```json
{
  "setup": {
    "type": "SINGLE"
  },
  "pre_processing_formulas": [
    {
      "name": "times_100",
      "formula": "=single_value * 100",
      "is_show": true
    }
  ],
  "evaluation_type": "PER_SAMPLE",
  "evaluation_setting": {
    "per_sample_setting": {
      "is_raw_data": true,    // ← Pakai raw data (single_value)
      "pre_processing_formula_name": null
    }
  },
  "rule_evaluation_setting": {
    "rule": "BETWEEN",
    "unit": "mm",
    "value": 10,
    "tolerance_minus": 1,
    "tolerance_plus": 1
  }
}
```

**Processing:**
```
Sample 1: single_value = 10.5 → evaluated_value = 10.5 → OK (dalam range 9-11)
Sample 2: single_value = 10.2 → evaluated_value = 10.2 → OK
Sample 3: single_value = 11.5 → evaluated_value = 11.5 → NG (lebih dari 11)

Overall Result: NG (karena ada 1 sample NG)
```

#### Example: Using Formula Result

```json
{
  "setup": {
    "type": "BEFORE_AFTER"
  },
  "pre_processing_formulas": [
    {
      "name": "selisih_persen",
      "formula": "=(before - after) / before * 100",
      "is_show": true
    },
    {
      "name": "after_times_100",
      "formula": "=after * 100",
      "is_show": false
    }
  ],
  "evaluation_type": "PER_SAMPLE",
  "evaluation_setting": {
    "per_sample_setting": {
      "is_raw_data": false,                          // ← Pakai formula result
      "pre_processing_formula_name": "selisih_persen"  // ← Pakai formula ini
    }
  },
  "rule_evaluation_setting": {
    "rule": "MAX",
    "unit": "%",
    "value": 5.0
  }
}
```

**Processing:**
```
Sample 1: before=100, after=95 → selisih_persen=5.0 → evaluated_value=5.0 → OK (≤5.0)
Sample 2: before=100, after=94 → selisih_persen=6.0 → evaluated_value=6.0 → NG (>5.0)
Sample 3: before=100, after=96 → selisih_persen=4.0 → evaluated_value=4.0 → OK

Overall Result: NG (karena Sample 2 NG)
```

### Storage in Database

```json
{
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {...},
      "processed_values": {
        "selisih_persen": 5.0,
        "after_times_100": 9500
      },
      "evaluated_value": 5.0,     // ← Nilai yang dievaluasi
      "status": true              // ← OK/NG status (true=OK, false=NG)
    }
  ],
  "status": true  // ← Overall status (semua sample OK)
}
```

---

## 📊 Step 5: Aggregation Formulas (Joint Setting)

**Only for `evaluation_type: "JOINT"`**

Step 5 menggabungkan (aggregate) **semua sample** menjadi **1 nilai final** yang kemudian dievaluasi.

### When to Use JOINT

- Statistical analysis (rata-rata, standar deviasi, dll)
- Total dari semua sample
- Perhitungan yang membutuhkan data dari semua sample sekaligus

### Flow Comparison

**PER_SAMPLE:**
```
Sample 1 → Process → Evaluate → OK/NG ┐
Sample 2 → Process → Evaluate → OK/NG ├→ Overall: OK if all OK
Sample 3 → Process → Evaluate → OK/NG ┘
```

**JOINT:**
```
Sample 1 → Process ┐
Sample 2 → Process ├→ Aggregate → Final Value → Evaluate → OK/NG
Sample 3 → Process ┘
```

### Configuration

```json
{
  "evaluation_type": "JOINT",
  "evaluation_setting": {
    "joint_setting": {
      "formulas": [
        {
          "name": "average",
          "formula": "=avg(processed_value_name)",
          "is_show": true,
          "is_final_value": false    // ← Bukan nilai final
        },
        {
          "name": "force",
          "formula": "=average * 9.80665",
          "is_show": true,
          "is_final_value": true     // ← INI nilai final untuk evaluasi
        }
      ]
    }
  },
  "rule_evaluation_setting": {
    "rule": "BETWEEN",
    "unit": "N",
    "value": 100,
    "tolerance_minus": 5,
    "tolerance_plus": 5
  }
}
```

### Formula Execution

1. **Pre-processing** dijalankan per sample (Step 3)
2. **Aggregation formulas** dijalankan setelah semua sample diproses
3. Aggregation formula bisa menggunakan:
   - Hasil pre-processing formula (dari semua sample)
   - Hasil aggregation formula sebelumnya
   - Variables

### Aggregation Functions

| Function | Description | Example |
|----------|-------------|---------|
| `avg(x)` | Rata-rata dari semua sample | `avg(processed_value)` |
| `sum(x)` | Total dari semua sample | `sum(processed_value)` |
| `min(x)` | Nilai minimum | `min(processed_value)` |
| `max(x)` | Nilai maksimum | `max(processed_value)` |
| `count(x)` | Jumlah sample | `count(processed_value)` |

### Complete Example

```json
{
  "setup": {
    "name": "Weight to Force",
    "name_id": "weight_to_force",
    "sample_amount": 3,
    "type": "SINGLE",
    "nature": "QUANTITATIVE"
  },
  "pre_processing_formulas": [
    {
      "name": "weight_kg",
      "formula": "=single_value",  // Raw weight in kg
      "is_show": true
    }
  ],
  "evaluation_type": "JOINT",
  "evaluation_setting": {
    "joint_setting": {
      "formulas": [
        {
          "name": "avg_weight",
          "formula": "=avg(weight_kg)",    // ← Aggregate semua sample
          "is_show": true,
          "is_final_value": false
        },
        {
          "name": "force_newton",
          "formula": "=avg_weight * 9.80665",  // ← Convert to Newton
          "is_show": true,
          "is_final_value": true           // ← NILAI INI yang dievaluasi
        }
      ]
    }
  },
  "rule_evaluation_setting": {
    "rule": "BETWEEN",
    "unit": "N",
    "value": 100,
    "tolerance_minus": 5,
    "tolerance_plus": 5
  }
}
```

### Processing Flow

**Input:**
```json
{
  "samples": [
    {"sample_index": 1, "single_value": 10.2},
    {"sample_index": 2, "single_value": 10.0},
    {"sample_index": 3, "single_value": 10.1}
  ]
}
```

**Step 3 (Pre-processing) - Per Sample:**
```
Sample 1: weight_kg = 10.2
Sample 2: weight_kg = 10.0
Sample 3: weight_kg = 10.1
```

**Step 5 (Aggregation) - All Samples Combined:**
```
avg_weight = (10.2 + 10.0 + 10.1) / 3 = 10.1
force_newton = 10.1 * 9.80665 = 99.047
```

**Step 4 (Evaluation) - Final Value:**
```
final_value = 99.047 (dari force_newton karena is_final_value=true)
rule: BETWEEN 95 - 105 (100 ± 5)
result: OK (99.047 dalam range)
```

### Storage in Database

```json
{
  "measurement_item_name_id": "weight_to_force",
  "status": true,
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {"single_value": 10.2},
      "processed_values": {"weight_kg": 10.2}
    },
    {
      "sample_index": 2,
      "raw_values": {"single_value": 10.0},
      "processed_values": {"weight_kg": 10.0}
    },
    {
      "sample_index": 3,
      "raw_values": {"single_value": 10.1},
      "processed_values": {"weight_kg": 10.1}
    }
  ],
  "joint_results": [              // ← Step 5 results
    {
      "name": "avg_weight",
      "value": 10.1,
      "formula": "=avg(weight_kg)",
      "is_final_value": false,
      "is_show": true
    },
    {
      "name": "force_newton",
      "value": 99.047,
      "formula": "=avg_weight * 9.80665",
      "is_final_value": true,
      "is_show": true
    }
  ],
  "final_value": 99.047,          // ← Evaluated value
  "evaluation_details": []
}
```

---

## 💾 Database Storage

### Table: `product_measurements`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `measurement_id` | string | Unique ID (MSR-XXXXXXXX) |
| `product_id` | bigint | Foreign key to products |
| `batch_number` | string | Batch production number |
| `sample_count` | integer | Number of samples |
| `status` | enum | PENDING, IN_PROGRESS, COMPLETED, CANCELLED |
| `overall_result` | boolean | Overall OK (true) or NG (false) |
| **`measurement_results`** | **JSON** | **ALL processed data stored here** |
| `measured_by` | bigint | User ID who performed measurement |
| `measured_at` | timestamp | When measurement completed |
| `notes` | text | Additional notes |

### JSON Structure in `measurement_results`

```json
[
  {
    "measurement_item_name_id": "thickness_test",
    "status": true,
    "samples": [
      {
        "sample_index": 1,
        "raw_values": {
          "single_value": 100
          // or
          "before_after_value": {"before": 100, "after": 95}
        },
        "processed_values": {          // ← STEP 3 results
          "formula_1": 10000,
          "formula_2": 200,
          "formula_3": 10200
        },
        "variables": {                 // ← STEP 2 variables
          "tolerance": 0.1,
          "room_temp": 25.5,
          "calculated": 100.5
        },
        "evaluated_value": 10000,      // ← STEP 4: value used for evaluation
        "status": true                 // ← STEP 4: OK/NG per sample
      }
      // ... more samples
    ],
    "final_value": 9800,               // ← STEP 5: final value (for JOINT only)
    "joint_results": [                 // ← STEP 5: aggregation results
      {
        "name": "average",
        "value": 98.5,
        "formula": "=avg(processed_value)",
        "is_final_value": false,
        "is_show": true
      },
      {
        "name": "force",
        "value": 9800,
        "formula": "=average * 9.80665",
        "is_final_value": true,
        "is_show": true
      }
    ],
    "evaluation_details": []
  }
  // ... more measurement items
]
```

### What Gets Saved

| Step | Data | Location in JSON | Always Saved? |
|------|------|------------------|---------------|
| **Input** | Raw data | `samples[].raw_values` | ✅ Yes |
| **Step 2** | Variables | `samples[].variables` | ✅ Yes |
| **Step 3** | Pre-processing results | `samples[].processed_values` | ✅ Yes (all formulas) |
| **Step 4** | Evaluated value | `samples[].evaluated_value` | ✅ Yes |
| **Step 4** | OK/NG status | `samples[].status` | ✅ Yes |
| **Step 5** | Aggregation results | `joint_results[]` | ✅ Yes (if JOINT type) |
| **Step 5** | Final value | `final_value` | ✅ Yes (if JOINT type) |

### Laravel Model Cast

```php
protected $casts = [
    'measurement_results' => 'array',  // Auto convert JSON ↔ Array
    'overall_result' => 'boolean',
    'measured_at' => 'datetime',
];
```

---

## 🎨 Frontend Integration

### Mengambil Data dari Database

**Endpoint:**
```
GET /api/v1/product-measurement/{measurement_id}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Success",
  "error_id": null,
  "data": {
    "measurement_id": "MSR-ABC12345",
    "product_id": "PRD-XYZ98765",
    "batch_number": "BATCH-001",
    "sample_count": 3,
    "measurement_status": "COMPLETED",
    "overall_result": true,
    "measurement_results": [
      // ... all processed data
    ],
    "measured_by": {
      "username": "operator1",
      "employee_id": "EMP001"
    },
    "measured_at": "2024-11-23T10:30:00+07:00"
  }
}
```

### Display Logic - Filtering by `is_show`

#### Product Configuration (dari GET /api/v1/products/{product_id})

```json
{
  "measurement_points": [
    {
      "setup": {
        "name_id": "thickness"
      },
      "variables": [
        {"name": "tolerance", "is_show": true},      // ← Show
        {"name": "offset", "is_show": false}         // ← Hide
      ],
      "pre_processing_formulas": [
        {"name": "times_100", "is_show": true},      // ← Show
        {"name": "internal_calc", "is_show": false}  // ← Hide
      ]
    }
  ]
}
```

#### Measurement Results (dari GET /api/v1/product-measurement/{id})

```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness",
      "samples": [
        {
          "variables": {
            "tolerance": 0.1,     // ← Check is_show in product config
            "offset": 10          // ← Check is_show in product config
          },
          "processed_values": {
            "times_100": 10000,   // ← Check is_show in product config
            "internal_calc": 50   // ← Check is_show in product config
          }
        }
      ]
    }
  ]
}
```

### Frontend Display Code Example (JavaScript/React)

```javascript
// Get product configuration
const product = await fetch(`/api/v1/products/${productId}`);
const productConfig = await product.json();

// Get measurement results
const measurement = await fetch(`/api/v1/product-measurement/${measurementId}`);
const measurementData = await measurement.json();

// Filter function
function shouldShow(itemNameId, fieldName, fieldType) {
  const measurementPoint = productConfig.data.measurement_points.find(
    mp => mp.setup.name_id === itemNameId
  );
  
  if (!measurementPoint) return false;
  
  if (fieldType === 'variable') {
    const variable = measurementPoint.variables?.find(v => v.name === fieldName);
    return variable?.is_show || false;
  }
  
  if (fieldType === 'pre_processing_formula') {
    const formula = measurementPoint.pre_processing_formulas?.find(f => f.name === fieldName);
    return formula?.is_show || false;
  }
  
  if (fieldType === 'joint_formula') {
    const jointSetting = measurementPoint.evaluation_setting?.joint_setting;
    const formula = jointSetting?.formulas?.find(f => f.name === fieldName);
    return formula?.is_show || false;
  }
  
  return false;
}

// Display results
measurementData.data.measurement_results.forEach(item => {
  const itemNameId = item.measurement_item_name_id;
  
  // Display variables
  item.samples[0].variables && Object.entries(item.samples[0].variables).forEach(([key, value]) => {
    if (shouldShow(itemNameId, key, 'variable')) {
      console.log(`Variable ${key}: ${value}`);  // Show this
    }
  });
  
  // Display pre-processing results
  item.samples.forEach(sample => {
    Object.entries(sample.processed_values || {}).forEach(([key, value]) => {
      if (shouldShow(itemNameId, key, 'pre_processing_formula')) {
        console.log(`Formula ${key}: ${value}`);  // Show this
      }
    });
  });
  
  // Display joint results
  item.joint_results?.forEach(result => {
    if (shouldShow(itemNameId, result.name, 'joint_formula')) {
      console.log(`Aggregation ${result.name}: ${result.value}`);  // Show this
    }
  });
});
```

### Display Example

**Product Config:**
```json
{
  "pre_processing_formulas": [
    {"name": "raw_times_100", "is_show": true},
    {"name": "internal_calc", "is_show": false},
    {"name": "final_result", "is_show": true}
  ]
}
```

**Measurement Results:**
```json
{
  "processed_values": {
    "raw_times_100": 10000,
    "internal_calc": 50,
    "final_result": 10050
  }
}
```

**Frontend Display:**
```
✅ raw_times_100: 10000     (is_show: true → displayed)
❌ internal_calc: 50         (is_show: false → hidden)
✅ final_result: 10050       (is_show: true → displayed)
```

---

## 📚 Complete Examples

### Example 1: Type SINGLE - Per Sample Evaluation

**Scenario:** Thickness measurement, evaluate raw data directly

**Product Configuration:**
```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "INSTRUMENT",
        "source_instrument_id": 1,
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "multiplier",
          "value": 100,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "raw_times_100",
          "formula": "=single_value * multiplier",
          "is_show": true
        },
        {
          "name": "raw_squared",
          "formula": "=single_value * single_value",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true,  // Evaluate raw data
          "pre_processing_formula_name": null
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ]
}
```

**Measurement Input:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness",
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "single_value": 10.5},
        {"sample_index": 2, "single_value": 10.2},
        {"sample_index": 3, "single_value": 9.8}
      ]
    }
  ]
}
```

**Stored in Database:**
```json
{
  "measurement_item_name_id": "thickness",
  "status": true,
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {"single_value": 10.5},
      "processed_values": {
        "raw_times_100": 1050,
        "raw_squared": 110.25
      },
      "variables": {"multiplier": 100},
      "evaluated_value": 10.5,
      "status": true  // OK: 9 ≤ 10.5 ≤ 11
    },
    {
      "sample_index": 2,
      "raw_values": {"single_value": 10.2},
      "processed_values": {
        "raw_times_100": 1020,
        "raw_squared": 104.04
      },
      "variables": {"multiplier": 100},
      "evaluated_value": 10.2,
      "status": true  // OK: 9 ≤ 10.2 ≤ 11
    },
    {
      "sample_index": 3,
      "raw_values": {"single_value": 9.8},
      "processed_values": {
        "raw_times_100": 980,
        "raw_squared": 96.04
      },
      "variables": {"multiplier": 100},
      "evaluated_value": 9.8,
      "status": true  // OK: 9 ≤ 9.8 ≤ 11
    }
  ]
}
```

**Frontend Display (only is_show: true):**
```
Sample 1:
  ✅ raw_times_100: 1050
  Status: OK

Sample 2:
  ✅ raw_times_100: 1020
  Status: OK

Sample 3:
  ✅ raw_times_100: 980
  Status: OK

Overall: OK
```

---

### Example 2: Type BEFORE_AFTER - Evaluate Formula Result

**Scenario:** Shrinkage test, evaluate percentage change

**Product Configuration:**
```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": [
        {
          "name": "selisih_persen",
          "formula": "=(before - after) / before * 100",
          "is_show": true
        },
        {
          "name": "after_times_100",
          "formula": "=after * 100",
          "is_show": true
        },
        {
          "name": "rata_rata",
          "formula": "=(before + after) / 2",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,  // Use formula result
          "pre_processing_formula_name": "selisih_persen"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0
      }
    }
  ]
}
```

**Measurement Input:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "shrinkage_test",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "before_after_value": {"before": 100, "after": 95}
        },
        {
          "sample_index": 2,
          "before_after_value": {"before": 100, "after": 94}
        },
        {
          "sample_index": 3,
          "before_after_value": {"before": 100, "after": 96}
        }
      ]
    }
  ]
}
```

**Processing Details:**

```
Sample 1: before=100, after=95
  Step 3:
    selisih_persen = (100-95)/100*100 = 5.0
    after_times_100 = 95*100 = 9500
    rata_rata = (100+95)/2 = 97.5
  Step 4:
    evaluated_value = 5.0 (from selisih_persen)
    status = OK (5.0 ≤ 5.0)

Sample 2: before=100, after=94
  Step 3:
    selisih_persen = (100-94)/100*100 = 6.0
    after_times_100 = 94*100 = 9400
    rata_rata = (100+94)/2 = 97.0
  Step 4:
    evaluated_value = 6.0 (from selisih_persen)
    status = NG (6.0 > 5.0)

Sample 3: before=100, after=96
  Step 3:
    selisih_persen = (100-96)/100*100 = 4.0
    after_times_100 = 96*100 = 9600
    rata_rata = (100+96)/2 = 98.0
  Step 4:
    evaluated_value = 4.0 (from selisih_persen)
    status = OK (4.0 ≤ 5.0)

Overall: NG (because Sample 2 is NG)
```

**Stored in Database:**
```json
{
  "measurement_item_name_id": "shrinkage_test",
  "status": false,  // NG
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {
        "before_after_value": {"before": 100, "after": 95}
      },
      "processed_values": {
        "selisih_persen": 5.0,
        "after_times_100": 9500,
        "rata_rata": 97.5
      },
      "variables": {},
      "evaluated_value": 5.0,
      "status": true
    },
    {
      "sample_index": 2,
      "raw_values": {
        "before_after_value": {"before": 100, "after": 94}
      },
      "processed_values": {
        "selisih_persen": 6.0,
        "after_times_100": 9400,
        "rata_rata": 97.0
      },
      "variables": {},
      "evaluated_value": 6.0,
      "status": false  // NG
    },
    {
      "sample_index": 3,
      "raw_values": {
        "before_after_value": {"before": 100, "after": 96}
      },
      "processed_values": {
        "selisih_persen": 4.0,
        "after_times_100": 9600,
        "rata_rata": 98.0
      },
      "variables": {},
      "evaluated_value": 4.0,
      "status": true
    }
  ]
}
```

**Frontend Display (only is_show: true):**
```
Sample 1:
  Before: 100
  After: 95
  ✅ selisih_persen: 5.0%
  ✅ after_times_100: 9500
  Status: OK

Sample 2:
  Before: 100
  After: 94
  ✅ selisih_persen: 6.0%  ← Failed here
  ✅ after_times_100: 9400
  Status: NG ❌

Sample 3:
  Before: 100
  After: 96
  ✅ selisih_persen: 4.0%
  ✅ after_times_100: 9600
  Status: OK

Overall: NG ❌
```

---

### Example 3: Type SINGLE - Joint Evaluation with Aggregation

**Scenario:** Weight measurement, calculate average and convert to force

**Product Configuration:**
```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Weight to Force",
        "name_id": "weight_to_force",
        "sample_amount": 3,
        "source": "INSTRUMENT",
        "source_instrument_id": 2,
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "gravity",
          "value": 9.80665,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "weight_kg",
          "formula": "=single_value",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_weight",
              "formula": "=avg(weight_kg)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "min_weight",
              "formula": "=min(weight_kg)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "max_weight",
              "formula": "=max(weight_kg)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "force_newton",
              "formula": "=avg_weight * gravity",
              "is_show": true,
              "is_final_value": true  // ← This is evaluated
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "N",
        "value": 100,
        "tolerance_minus": 5,
        "tolerance_plus": 5
      }
    }
  ]
}
```

**Measurement Input:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "weight_to_force",
      "variable_values": [],
      "samples": [
        {"sample_index": 1, "single_value": 10.2},
        {"sample_index": 2, "single_value": 10.0},
        {"sample_index": 3, "single_value": 10.1}
      ]
    }
  ]
}
```

**Processing Details:**

```
Step 3 (Pre-processing) - Per Sample:
  Sample 1: weight_kg = 10.2
  Sample 2: weight_kg = 10.0
  Sample 3: weight_kg = 10.1

Step 5 (Aggregation) - All Samples:
  avg_weight = (10.2 + 10.0 + 10.1) / 3 = 10.1
  min_weight = min(10.2, 10.0, 10.1) = 10.0
  max_weight = max(10.2, 10.0, 10.1) = 10.2
  force_newton = 10.1 * 9.80665 = 99.047265

Step 4 (Evaluation) - Final Value:
  evaluated_value = 99.047265 (from force_newton)
  rule: BETWEEN 95 - 105 (100 ± 5)
  status = OK (95 ≤ 99.047 ≤ 105)
```

**Stored in Database:**
```json
{
  "measurement_item_name_id": "weight_to_force",
  "status": true,
  "samples": [
    {
      "sample_index": 1,
      "raw_values": {"single_value": 10.2},
      "processed_values": {"weight_kg": 10.2},
      "variables": {"gravity": 9.80665}
    },
    {
      "sample_index": 2,
      "raw_values": {"single_value": 10.0},
      "processed_values": {"weight_kg": 10.0},
      "variables": {"gravity": 9.80665}
    },
    {
      "sample_index": 3,
      "raw_values": {"single_value": 10.1},
      "processed_values": {"weight_kg": 10.1},
      "variables": {"gravity": 9.80665}
    }
  ],
  "joint_results": [
    {
      "name": "avg_weight",
      "value": 10.1,
      "formula": "=avg(weight_kg)",
      "is_final_value": false,
      "is_show": true
    },
    {
      "name": "min_weight",
      "value": 10.0,
      "formula": "=min(weight_kg)",
      "is_final_value": false,
      "is_show": true
    },
    {
      "name": "max_weight",
      "value": 10.2,
      "formula": "=max(weight_kg)",
      "is_final_value": false,
      "is_show": true
    },
    {
      "name": "force_newton",
      "value": 99.047265,
      "formula": "=avg_weight * gravity",
      "is_final_value": true,
      "is_show": true
    }
  ],
  "final_value": 99.047265
}
```

**Frontend Display:**
```
Sample Data:
  Sample 1: 10.2 kg
  Sample 2: 10.0 kg
  Sample 3: 10.1 kg

Aggregation Results:
  ✅ Average Weight: 10.1 kg
  ✅ Min Weight: 10.0 kg
  ✅ Max Weight: 10.2 kg
  ✅ Force (Newton): 99.047 N

Evaluation:
  Final Value: 99.047 N
  Rule: BETWEEN 95 - 105 N
  Status: OK ✅
```

---

## 📡 API Reference

### Submit Measurement Results

**Endpoint:**
```
POST /api/v1/product-measurement/{measurement_id}/submit
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json"
}
```

**Request Body (Type SINGLE):**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness",
      "variable_values": [
        {
          "name_id": "room_temp",
          "value": 25.5
        }
      ],
      "samples": [
        {
          "sample_index": 1,
          "single_value": 10.5
        },
        {
          "sample_index": 2,
          "single_value": 10.2
        }
      ]
    }
  ]
}
```

**Request Body (Type BEFORE_AFTER):**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "shrinkage_test",
      "variable_values": [],
      "samples": [
        {
          "sample_index": 1,
          "before_after_value": {
            "before": 100,
            "after": 95
          }
        }
      ]
    }
  ]
}
```

**Success Response:**
```json
{
  "http_code": 200,
  "message": "Measurement results processed successfully",
  "error_id": null,
  "data": {
    "status": true,
    "overall_result": "OK",
    "evaluation_summary": {
      "total_items": 1,
      "passed_items": 1,
      "failed_items": 0
    },
    "samples": [
      // ... processed measurement results
    ]
  }
}
```

### Get Measurement by ID

**Endpoint:**
```
GET /api/v1/product-measurement/{measurement_id}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Success",
  "error_id": null,
  "data": {
    "measurement_id": "MSR-ABC12345",
    "product_id": "PRD-XYZ98765",
    "batch_number": "BATCH-001",
    "sample_count": 3,
    "measurement_type": "MANUAL",
    "product_status": "ACTIVE",
    "measurement_status": "COMPLETED",
    "sample_status": "COMPLETE",
    "overall_result": true,
    "measurement_results": [
      // ... all processed data including:
      // - raw_values
      // - processed_values (Step 3 results)
      // - variables (Step 2 values)
      // - evaluated_value (Step 4)
      // - status (OK/NG per sample)
      // - joint_results (Step 5 for JOINT type)
      // - final_value (Step 5 for JOINT type)
    ],
    "measured_by": {
      "username": "operator1",
      "employee_id": "EMP001"
    },
    "measured_at": "2024-11-23T10:30:00+07:00",
    "notes": null,
    "created_at": "2024-11-23T09:00:00+07:00"
  }
}
```

---

## 🎓 Summary & Best Practices

### Quick Reference

| Aspect | Key Points |
|--------|-----------|
| **Raw Data Variable Names** | Type SINGLE: `single_value`<br>Type BEFORE_AFTER: `before`, `after` |
| **Step 3 Execution** | Per sample (each raw data processed separately) |
| **Step 5 Execution** | Aggregate (all samples combined into final value) |
| **Database Storage** | ALL values saved in JSON field `measurement_results` |
| **Frontend Display** | Filter by `is_show: true` from product configuration |
| **Formula Dependencies** | Can reference previous formulas in same array |
| **Cross-reference** | Can use `avg(other_item_name_id)` in formulas |

### Best Practices

1. **Naming Conventions**
   - Use descriptive formula names: `selisih_persen`, `avg_weight`, not `formula_1`
   - Use snake_case for consistency
   - Avoid reserved keywords

2. **Formula Organization**
   - Put intermediate calculations in pre-processing (Step 3)
   - Put aggregations in joint setting (Step 5)
   - Set `is_show: false` for internal calculations

3. **Evaluation Strategy**
   - Use PER_SAMPLE for quality checks per item
   - Use JOINT for statistical analysis
   - Choose raw data or formula result wisely

4. **Performance**
   - Complex formulas run per sample, keep them simple
   - Use variables to avoid repetitive calculations
   - Limit cross-references when possible

5. **Testing**
   - Test with edge cases (min/max values)
   - Verify formula execution order
   - Check display logic with is_show filter

---

## 🐛 Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| `Variable not found: raw_data` | Using wrong variable name for SINGLE | Use `single_value` instead |
| `Variable not found: raw_data_before` | Using wrong variable name for BEFORE_AFTER | Use `before` and `after` instead |
| Formula result is 0 | Variable not set correctly | Check variable values in Step 2 |
| Formula not evaluated | Wrong formula name in evaluation_setting | Match exact formula name |
| Values not showing in Frontend | `is_show: false` in config | Set `is_show: true` for display |
| Joint formulas not running | evaluation_type is not JOINT | Set `evaluation_type: "JOINT"` |

---

## 🧪 Postman Testing - Complete Payload Collection

Section ini berisi semua payload untuk testing di Postman, baik yang **CORRECT** (berhasil) maupun **INCORRECT** (error dengan penjelasan).

### Endpoint
```
POST /api/v1/products
```

### Headers
```json
{
  "Authorization": "Bearer {your_token}",
  "Content-Type": "application/json"
```

---

### ✅ Payload CORRECT

#### 1. Type SINGLE - Tanpa Step 3 (Hanya Raw Data)

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true,
          "pre_processing_formula_name": null
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `201 Created`

---

#### 2. Type SINGLE - Dengan Step 3, Pilih Formula

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "multiplier",
          "value": 100,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "times_100",
          "formula": "=single_value * multiplier",
          "is_show": true
        },
        {
          "name": "squared",
          "formula": "=single_value * single_value",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "times_100"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1000,
        "tolerance_minus": 100,
        "tolerance_plus": 100
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `201 Created`

---

#### 3. Type SINGLE - Dengan Step 3, Pilih Raw Data

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "multiplier",
          "value": 100,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "times_100",
          "formula": "=single_value * multiplier",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true,
          "pre_processing_formula_name": null
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `201 Created`

---

#### 4. Type BEFORE_AFTER - Dengan Step 3, Pilih Formula

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": [
        {
          "name": "selisih_persen",
          "formula": "=(before - after) / before * 100",
          "is_show": true
        },
        {
          "name": "after_times_100",
          "formula": "=after * 100",
          "is_show": true
        },
        {
          "name": "rata_rata",
          "formula": "=(before + after) / 2",
          "is_show": false
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "selisih_persen"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0,
        "tolerance_minus": null,
        "tolerance_plus": null
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `201 Created`

---

#### 5. Type SINGLE - Joint Evaluation dengan Step 3

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Weight to Force",
        "name_id": "weight_to_force",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "gravity",
          "value": 9.80665,
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "weight_kg",
          "formula": "=single_value",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "per_sample_setting": null,
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_weight",
              "formula": "=avg(weight_kg)",
              "is_show": true,
              "is_final_value": false
            },
            {
              "name": "force_newton",
              "formula": "=avg_weight * gravity",
              "is_show": true,
              "is_final_value": true
            }
          ]
        },
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "N",
        "value": 100,
        "tolerance_minus": 5,
        "tolerance_plus": 5
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `201 Created`

---

### ❌ Payload INCORRECT (Error Cases)

#### 1. Type SINGLE - Tanpa Step 3 tapi Pilih Formula

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "times_100"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `400 Bad Request`  
**Error Message:** `"Tidak bisa menggunakan pre-processing formula karena tidak ada pre-processing formulas yang didefinisikan. Gunakan is_raw_data = true atau tambahkan pre-processing formulas"`

---

#### 2. Type SINGLE - Dengan Step 3, Pilih Formula yang Tidak Ada

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "thickness",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": [
        {
          "name": "times_100",
          "formula": "=single_value * 100",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "formula_salah"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 10,
        "tolerance_minus": 1,
        "tolerance_plus": 1
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `400 Bad Request`  
**Error Message:** `"Pre-processing formula 'formula_salah' tidak ditemukan dalam pre_processing_formulas. Formula yang tersedia: times_100"`

---

#### 3. Type BEFORE_AFTER - Tanpa Step 3

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "selisih_persen"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0,
        "tolerance_minus": null,
        "tolerance_plus": null
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `400 Bad Request`  
**Error Message:** `"Pre-processing formulas wajib diisi untuk type BEFORE_AFTER"`

---

#### 4. Type BEFORE_AFTER - Dengan Step 3 tapi Pilih Raw Data

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": [
        {
          "name": "selisih_persen",
          "formula": "=(before - after) / before * 100",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true,
          "pre_processing_formula_name": null
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0,
        "tolerance_minus": null,
        "tolerance_plus": null
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `400 Bad Request`  
**Error Message:** `"Type BEFORE_AFTER tidak bisa menggunakan raw data untuk evaluation, harus menggunakan pre-processing formula"`

---

#### 5. Type BEFORE_AFTER - Dengan Step 3, Pilih Formula yang Tidak Ada

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Shrinkage Test",
        "name_id": "shrinkage_test",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": [
        {
          "name": "selisih_persen",
          "formula": "=(before - after) / before * 100",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "formula_salah"
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 5.0,
        "tolerance_minus": null,
        "tolerance_plus": null
      }
    }
  ],
  "measurement_groups": null
}
```

**Expected:** `400 Bad Request`  
**Error Message:** `"Pre-processing formula 'formula_salah' tidak ditemukan dalam pre_processing_formulas. Formula yang tersedia: selisih_persen"`

---

### 📊 Testing Summary Table

| No | Type | Step 3 | Step 4 Choice | Status | Expected HTTP Code |
|----|------|--------|--------------|--------|-------------------|
| 1 | SINGLE | ❌ No | Raw data | ✅ CORRECT | 201 |
| 2 | SINGLE | ✅ Yes | Formula | ✅ CORRECT | 201 |
| 3 | SINGLE | ✅ Yes | Raw data | ✅ CORRECT | 201 |
| 4 | BEFORE_AFTER | ✅ Yes | Formula | ✅ CORRECT | 201 |
| 5 | SINGLE | ✅ Yes | JOINT | ✅ CORRECT | 201 |
| 6 | SINGLE | ❌ No | Formula | ❌ ERROR | 400 |
| 7 | SINGLE | ✅ Yes | Formula salah | ❌ ERROR | 400 |
| 8 | BEFORE_AFTER | ❌ No | - | ❌ ERROR | 400 |
| 9 | BEFORE_AFTER | ✅ Yes | Raw data | ❌ ERROR | 400 |
| 10 | BEFORE_AFTER | ✅ Yes | Formula salah | ❌ ERROR | 400 |

---

### 🔍 Testing Checklist

Saat testing di Postman, pastikan:

1. ✅ **Headers sudah benar:**
   - `Authorization: Bearer {token}`
   - `Content-Type: application/json`

2. ✅ **product_category_id valid:**
   - Ambil dari `GET /api/v1/products/categories`
   - Pastikan ID ada di database

3. ✅ **product_name valid:**
   - Harus ada dalam array `products` dari category yang dipilih

4. ✅ **Name format valid:**
   - Harus mulai dengan huruf atau underscore
   - Bisa mengandung angka setelah karakter pertama
   - Contoh: `times_100`, `formula1`, `_temp` ✅
   - Contoh salah: `100times`, `test-name` ❌

5. ✅ **Formula syntax:**
   - Harus dimulai dengan `=`
   - Variable names sesuai type (SINGLE: `single_value`, BEFORE_AFTER: `before`, `after`)

6. ✅ **Validation rules:**
   - BEFORE_AFTER wajib ada pre_processing_formulas
   - BEFORE_AFTER tidak bisa `is_raw_data = true`
   - Formula name harus ada di pre_processing_formulas jika dipilih

---

**Document End**

For questions or support, contact the development team.

