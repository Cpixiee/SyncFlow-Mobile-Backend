# Test Payloads - Enhanced Error System

## Overview

File ini berisi payload-payload untuk testing sistem enhanced error yang baru. Setiap payload dirancang untuk menguji skenario error tertentu.

---

## Prerequisites

Sebelum testing, pastikan:
1. Product category dengan ID `1` sudah ada di database
2. Product category tersebut memiliki products list (misal: `["AVSSH", "AVSS", "CAVUS"]`)
3. API endpoint: `POST /api/v1/products`

---

## Test Case 1: Measurement Item Name dengan Special Character

**Expected Error:** `SPECIAL_CHARACTER` - Name tidak valid karena mengandung karakter spesial

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness (A)",
        "name_id": "thickness_a",
        "sample_amount": 5,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
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
    }
  ]
}
```

---

## Test Case 2: Formula dengan Missing Dependency

**Expected Error:** `MISSING_DEPENDENCY` - Formula tidak valid karena dependency tidak ditemukan

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Room Temperature",
        "name_id": "room_temp",
        "sample_amount": 3,
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "type": "SINGLE"
      },
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=cross_section*single_value",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "normalized"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "mm",
        "value": 10
      }
    }
  ]
}
```

---

## Test Case 3: Variable Name dengan Space

**Expected Error:** `CONTAINS_SPACE` - Variable tidak valid karena mengandung spasi

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
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
      "variables": [
        {
          "type": "FIXED",
          "name": "cross section",
          "value": 2.5,
          "is_show": true
        }
      ],
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
    }
  ]
}
```

---

## Test Case 4: Variable Name dengan Uppercase

**Expected Error:** `UPPERCASE_NOT_ALLOWED` - Variable tidak valid karena mengandung huruf besar

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
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
      "variables": [
        {
          "type": "FIXED",
          "name": "CrossSection",
          "value": 2.5,
          "is_show": true
        }
      ],
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
    }
  ]
}
```

---

## Test Case 5: Missing Rule Evaluation

**Expected Error:** `REQUIRED` - Rule evaluation wajib diisi

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
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
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      }
    }
  ]
}
```

---

## Test Case 6: Missing Tolerance untuk BETWEEN Rule

**Expected Error:** `INVALID_TOLERANCE` - Tolerance wajib diisi untuk BETWEEN rule

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75"
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
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.5
      }
    }
  ]
}
```

---

## Test Case 7: Invalid Product Name untuk Category

**Expected Error:** `INVALID_PRODUCT_NAME` - Product name tidak valid untuk category

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "INVALID_PRODUCT",
    "color": "Black",
    "size": "0.75"
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
    }
  ]
}
```

---

## Test Case 8: Valid Payload (Success Case)

**Expected:** Product berhasil dibuat

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH",
    "color": "Black",
    "size": "0.75",
    "ref_spec_number": "REF-001",
    "article_code": "ART-001"
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
      "variables": [
        {
          "type": "FIXED",
          "name": "cross_section",
          "value": 2.5,
          "is_show": true
        }
      ],
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
    }
  ]
}
```

---

## Quick Test Collection (Postman/Insomnia)

### Setup
- **Base URL:** `http://your-domain/api/v1`
- **Endpoint:** `POST /products`
- **Headers:**
  - `Content-Type: application/json`
  - `Authorization: Bearer {token}`

### Test All Cases

1. **Copy payload dari test case yang diinginkan**
2. **Paste ke request body**
3. **Send request**
4. **Verify response sesuai expected error format**

---

## Notes

1. **Product Category ID:** Ganti `1` dengan ID yang sesuai di database
2. **Product Name:** Gunakan nama produk yang valid untuk category tersebut
3. **Token:** Pastikan menggunakan valid JWT token
4. **Base URL:** Sesuaikan dengan environment (local/staging/production)

---

**Last Updated:** 2026-01-11
