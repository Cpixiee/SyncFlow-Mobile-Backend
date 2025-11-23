# 🚀 SyncFlow API - Changes & Integration Guide for Frontend/Mobile

**Version:** 1.1.0  
**Last Updated:** November 23, 2024  
**Target Audience:** Frontend & Mobile Developers

---

## 📋 Table of Contents

1. [What's Changed](#whats-changed)
2. [Breaking Changes](#breaking-changes)
3. [Product Creation API](#product-creation-api)
4. [Measurement Instruments API](#measurement-instruments-api)
5. [Product Categories API](#product-categories-api)
6. [Formula System](#formula-system)
7. [Raw Data Access](#raw-data-access)
8. [Common Errors & Solutions](#common-errors--solutions)
9. [Complete Examples](#complete-examples)

---

## 🎯 What's Changed

### ✅ Fixed Issues

| Issue | Status | Impact |
|-------|--------|--------|
| QUALITATIVE measurements required `source` & `type` | ✅ FIXED | Now optional for QUALITATIVE |
| `source_instrument_id` confusion with tool models | ✅ DOCUMENTED | Clear distinction added |
| No active quarter error | ✅ FIXED | Auto-activated in deployment |
| `measurement_groups` null error | ✅ FIXED | Now handles null properly |
| Raw data access from other items | ✅ CONFIRMED | Already supported |

### 🆕 New Features

- ✅ Quarter activation command
- ✅ Enhanced validation for QUANTITATIVE vs QUALITATIVE
- ✅ Comprehensive seeding system
- ✅ Better error messages

---

## ⚠️ Breaking Changes

### None! 

All changes are **backward compatible**. Existing payloads will still work.

**New capabilities added (optional):**
- QUALITATIVE measurements can now omit `source` and `type`
- Better validation error messages

---

## 📡 Product Creation API

### Endpoint
```
POST /api/v1/products
```

### Headers
```json
{
  "Authorization": "Bearer {your_jwt_token}",
  "Content-Type": "application/json"
}
```

### Authentication
Required: **Admin** or **SuperAdmin** role

---

## 📦 Product Creation - Payload Structure

### 1. **Basic Info**

```json
{
  "basic_info": {
    "product_category_id": 93,        // REQUIRED - Integer ID (from /products/categories)
    "product_name": "COTO",           // REQUIRED - String (must be in category's products array)
    "ref_spec_number": "SPEC-001",    // OPTIONAL
    "nom_size_vo": "12mm",            // OPTIONAL
    "article_code": "ART-001",        // OPTIONAL
    "no_document": "DOC-001",         // OPTIONAL
    "no_doc_reference": "REF-001"     // OPTIONAL
  }
}
```

### 2. **Measurement Points - QUANTITATIVE**

```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",           // REQUIRED - Display name
        "name_id": "thickness",                    // REQUIRED - Unique identifier (alphanumeric + underscore)
        "sample_amount": 3,                        // REQUIRED - Integer, min: 1
        "source": "INSTRUMENT",                    // REQUIRED for QUANTITATIVE
        "source_instrument_id": 1,                 // REQUIRED if source = INSTRUMENT (Integer ID)
        "source_tool_model": null,                 // REQUIRED if source = TOOL (String model)
        "source_derived_name_id": null,            // REQUIRED if source = DERIVED (String name_id)
        "type": "SINGLE",                          // REQUIRED for QUANTITATIVE (SINGLE or BEFORE_AFTER)
        "nature": "QUANTITATIVE"                   // REQUIRED
      },
      "variables": null,                           // OPTIONAL - Array of variables
      "pre_processing_formulas": null,             // OPTIONAL - Array of formulas
      "evaluation_type": "PER_SAMPLE",             // REQUIRED (PER_SAMPLE, JOINT, or SKIP_CHECK)
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true,                     // REQUIRED - true for raw, false for processed
          "pre_processing_formula_name": null      // REQUIRED if is_raw_data = false
        },
        "joint_setting": null,
        "qualitative_setting": null
      },
      "rule_evaluation_setting": {                 // REQUIRED for QUANTITATIVE
        "rule": "BETWEEN",                         // REQUIRED (MIN, MAX, or BETWEEN)
        "unit": "mm",                              // REQUIRED
        "value": 2.5,                              // REQUIRED - Numeric
        "tolerance_minus": 0.1,                    // REQUIRED if rule = BETWEEN
        "tolerance_plus": 0.1                      // REQUIRED if rule = BETWEEN
      }
    }
  ]
}
```

### 3. **Measurement Points - QUALITATIVE** (NEW!)

```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Visual Check",              // REQUIRED
        "name_id": "visual_check",           // REQUIRED
        "sample_amount": 5,                  // REQUIRED
        "nature": "QUALITATIVE"              // REQUIRED
        // ❌ NO source required!
        // ❌ NO type required!
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "SKIP_CHECK",       // MUST be SKIP_CHECK for qualitative
      "evaluation_setting": {
        "qualitative_setting": {             // REQUIRED for QUALITATIVE
          "label": "Visual Quality",
          "options": ["Good", "Fair", "Poor"],
          "passing_criteria": "Must be Good or Fair"
        },
        "per_sample_setting": null,
        "joint_setting": null
      },
      "rule_evaluation_setting": null        // MUST be null for qualitative
    }
  ]
}
```

---

## 🎛️ Source Types - Complete Guide

### Frontend Flow: How to Handle Sources

```javascript
// 1. User selects source type
const sourceType = userSelectedSource; // "INSTRUMENT", "TOOL", "MANUAL", "DERIVED"

// 2. Show appropriate dropdown based on source
switch(sourceType) {
  case "INSTRUMENT":
    // Fetch instruments and show dropdown
    const instruments = await fetchInstruments();
    showDropdown(instruments, 'id', 'display_name');
    payload.source_instrument_id = selectedInstrument.id; // Send INTEGER ID
    break;
    
  case "TOOL":
    // Fetch tool models and show dropdown
    const toolModels = await fetchToolModels();
    showDropdown(toolModels);
    payload.source_tool_model = selectedModel; // Send STRING model
    break;
    
  case "MANUAL":
    // No dropdown needed
    payload.source_instrument_id = null;
    payload.source_tool_model = null;
    break;
    
  case "DERIVED":
    // Show dropdown of existing measurement items
    const measurementItems = await fetchMeasurementItems(productId);
    showDropdown(measurementItems, 'name_id', 'name');
    payload.source_derived_name_id = selectedItem.name_id; // Send STRING name_id
    break;
}
```

### Source Type Comparison Table

| Source Type | Field Name | Value Type | How to Get | Example |
|-------------|-----------|------------|------------|---------|
| **INSTRUMENT** | `source_instrument_id` | Integer (ID) | `GET /api/v1/measurement-instruments` | `1`, `2`, `3` |
| **TOOL** | `source_tool_model` | String (Model) | `GET /api/v1/tools/models` | `"MITUTOYO-CD-15CPX"` |
| **MANUAL** | - | - | No API call needed | - |
| **DERIVED** | `source_derived_name_id` | String (name_id) | From product's measurement_points | `"thickness_a"` |

---

## 🔧 Measurement Instruments API

### Get All Instruments

**Endpoint:**
```
GET /api/v1/measurement-instruments
```

**Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Measurement instruments retrieved successfully",
  "error_id": null,
  "data": {
    "instruments": [
      {
        "id": 1,                              // ← Use this for source_instrument_id
        "name": "Digital Caliper",
        "model": "DC-150",
        "serial_number": "DC001-2024",
        "manufacturer": "Mitutoyo",
        "status": "ACTIVE",
        "description": "High precision digital caliper",
        "display_name": "Digital Caliper (DC001-2024)",  // ← Show this to user
        "needs_calibration": true,
        "last_calibration": "2024-01-15",
        "next_calibration": "2025-01-15"
      },
      {
        "id": 2,
        "name": "Micrometer",
        "model": "MC-25",
        "serial_number": "MC002-2024",
        "display_name": "Micrometer (MC002-2024)",
        "status": "ACTIVE"
      }
    ]
  }
}
```

**Usage in Frontend:**
```javascript
// Fetch instruments
const response = await fetch('/api/v1/measurement-instruments', {
  headers: { 'Authorization': 'Bearer ' + token }
});
const data = await response.json();

// Populate dropdown
const instruments = data.data.instruments;
instruments.forEach(instrument => {
  dropdown.addOption({
    value: instrument.id,              // Send this to backend
    label: instrument.display_name     // Show this to user
  });
});

// When user selects
const payload = {
  setup: {
    source: "INSTRUMENT",
    source_instrument_id: selectedInstrument.id  // INTEGER!
  }
};
```

---

## 📂 Product Categories API

### Get All Categories

**Endpoint:**
```
GET /api/v1/products/categories
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Categories retrieved successfully",
  "error_id": null,
  "data": [
    {
      "id": 93,                              // ← Use this for product_category_id
      "name": "Tube Test",                   // ← Show this to user
      "products": [                          // ← Use for product_name dropdown
        "VO",
        "COTO",
        "COT",
        "COTO-FR"
      ],
      "description": "Kategori untuk testing tube"
    },
    {
      "id": 94,
      "name": "Wire Test Reguler",
      "products": [
        "CAVS",
        "ACCAVS",
        "CIVUS"
      ]
    }
  ]
}
```

**Frontend Flow:**
```javascript
// 1. Fetch categories
const categories = await fetchCategories();

// 2. User selects category
onCategorySelect(category => {
  // Show product name dropdown from category.products
  showProductDropdown(category.products);
  
  // Save category ID for payload
  selectedCategoryId = category.id;
});

// 3. Build payload
const payload = {
  basic_info: {
    product_category_id: selectedCategoryId,  // INTEGER ID
    product_name: selectedProductName         // STRING from products array
  }
};
```

---

## 🧮 Formula System

### Formula Syntax

All formulas **MUST** start with `=` (like Excel)

```javascript
// ✅ CORRECT
"=avg(thickness_a)"
"=(avg(thickness_a) + avg(thickness_b)) / 2"
"=sqrt(pow(x, 2) + pow(y, 2))"

// ❌ WRONG
"avg(thickness_a)"  // Missing =
"AVG(thickness_a)"  // Wrong (will be auto-converted, but use lowercase)
```

### Variables with Formulas

```json
{
  "variables": [
    {
      "type": "FIXED",
      "name": "tolerance",
      "value": 0.1,
      "is_show": true
    },
    {
      "type": "MANUAL",
      "name": "temperature",
      "is_show": true
    },
    {
      "type": "FORMULA",
      "name": "avg_thickness",
      "formula": "=(avg(thickness_a) + avg(thickness_b)) / 2",
      "is_show": true
    }
  ]
}
```

### Cross-Reference Other Measurement Items

**✅ Supported!** You can reference other measurement items in formulas.

```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "name_id": "thickness_a"  // ← Item 1
      }
    },
    {
      "setup": {
        "name": "Thickness B",
        "name_id": "thickness_b"  // ← Item 2
      }
    },
    {
      "setup": {
        "name": "Average Thickness",
        "name_id": "avg_thickness"  // ← Item 3
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "calculated",
          "formula": "=(avg(thickness_a) + avg(thickness_b)) / 2"  // ← References Item 1 & 2
        }
      ]
    }
  ]
}
```

**⚠️ Important:** Referenced items MUST be defined **before** the formula (order matters!)

---

## 📊 Raw Data Access

### Question: Can formulas access RAW DATA from other measurement items?

**Answer: YES! ✅**

### How It Works

```
IoT/Instrument → Frontend → Backend
      ↓
  Raw data stored in: measurement_results.samples.raw_values
      ↓
  Formula AVG(name_id) reads from: raw_values.single_value
      ↓
  Result used in other measurement items
```

### Data Structure

**When you submit measurement:**
```json
{
  "measurement_results": [
    {
      "measurement_item_name_id": "thickness_a",
      "samples": [
        {
          "sample_index": 0,
          "single_value": 2.5  // ← Your raw data from IoT/Manual
        }
      ]
    }
  ]
}
```

**Backend automatically stores as:**
```json
{
  "samples": [
    {
      "sample_index": 0,
      "raw_values": {
        "single_value": 2.5  // ← Raw data preserved here
      },
      "processed_values": {
        "calculated": 2.3    // ← If pre-processing formulas exist
      },
      "evaluated_value": 2.5,
      "status": true
    }
  ]
}
```

### Formula Accesses Raw Data

```javascript
// Formula: =avg(thickness_a)
// Will read from: raw_values.single_value
// NOT from: processed_values

// Example with 3 samples:
// Sample 1: raw_values.single_value = 2.5
// Sample 2: raw_values.single_value = 2.6  
// Sample 3: raw_values.single_value = 2.4
// Result: avg(thickness_a) = (2.5 + 2.6 + 2.4) / 3 = 2.5
```

### Get Raw Data from API

**Endpoint:**
```
GET /api/v1/product-measurement/{measurementId}
```

**Response includes raw data:**
```json
{
  "data": {
    "measurement_results": [
      {
        "measurement_item_name_id": "thickness_a",
        "samples": [
          {
            "sample_index": 0,
            "raw_values": {
              "single_value": 2.5  // ← RAW DATA HERE
            },
            "processed_values": {
              "some_calculation": 2.3
            },
            "evaluated_value": 2.5,
            "status": true
          }
        ]
      }
    ]
  }
}
```

---

## 🔴 Common Errors & Solutions

### Error 1: "Tidak ada quarter aktif"

**HTTP Code:** 400  
**Error ID:** `NO_ACTIVE_QUARTER`

**Cause:** No active quarter in database

**Solution:** Contact backend team to activate quarter. This is handled automatically in deployment now.

```json
{
  "http_code": 400,
  "message": "Tidak ada quarter aktif",
  "error_id": "NO_ACTIVE_QUARTER",
  "data": null
}
```

---

### Error 2: "source_instrument_id must be integer"

**HTTP Code:** 400  
**Error ID:** `VALIDATION_ERROR`

**Cause:** Sending tool model (string) instead of instrument ID (integer)

**Wrong:**
```json
{
  "source": "INSTRUMENT",
  "source_instrument_id": "MITUTOYO-DC-150"  // ❌ This is a model name!
}
```

**Correct:**
```json
{
  "source": "INSTRUMENT",
  "source_instrument_id": 1  // ✅ This is an ID from measurement_instruments table
}
```

**How to fix:**
1. Call `GET /api/v1/measurement-instruments`
2. Use the `id` field (not `model` field)

---

### Error 3: "Setup.source wajib diisi" (for QUALITATIVE)

**HTTP Code:** 400

**Cause:** Old validation (FIXED in v1.1.0)

**Solution:** Update your payload. For QUALITATIVE, `source` and `type` are now **optional**.

**Before (caused error):**
```json
{
  "setup": {
    "nature": "QUALITATIVE"
    // Missing source & type → ERROR!
  }
}
```

**After (works now!):**
```json
{
  "setup": {
    "nature": "QUALITATIVE"
    // source & type are optional ✅
  }
}
```

---

### Error 4: "Invalid product category id"

**HTTP Code:** 400  
**Error ID:** `VALIDATION_ERROR`

**Cause:** Category ID doesn't exist or wrong ID

**Solution:**
1. Fetch categories first: `GET /api/v1/products/categories`
2. Use the correct `id` from response

```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "basic_info.product_category_id": [
      "The selected basic info.product category id is invalid."
    ]
  }
}
```

---

## 📝 Complete Examples

### Example 1: QUANTITATIVE with INSTRUMENT

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO",
    "ref_spec_number": "SPEC-001"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Inside Diameter",
        "name_id": "inside_diameter",
        "sample_amount": 3,
        "source": "INSTRUMENT",
        "source_instrument_id": 1,
        "source_derived_name_id": null,
        "source_tool_model": null,
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
        "value": 14.4,
        "tolerance_minus": 0.3,
        "tolerance_plus": 0.3
      }
    }
  ],
  "measurement_groups": null
}
```

**Success Response:**
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "error_id": null,
  "data": {
    "product_id": "PRD-ABC123XY",
    "basic_info": {
      "product_category_id": 93,
      "product_name": "COTO",
      "ref_spec_number": "SPEC-001",
      "nom_size_vo": null,
      "article_code": null,
      "no_document": null,
      "no_doc_reference": null
    },
    "measurement_points": [
      {
        "setup": {
          "name": "Inside Diameter",
          "name_id": "inside_diameter",
          "sample_amount": 3,
          "source": "INSTRUMENT",
          "source_instrument_id": 1,
          "type": "SINGLE",
          "nature": "QUANTITATIVE"
        },
        "evaluation_type": "PER_SAMPLE",
        "evaluation_setting": { ... },
        "rule_evaluation_setting": { ... }
      }
    ],
    "measurement_groups": null,
    "product_category": {
      "id": 93,
      "name": "Tube Test"
    }
  }
}
```

---

### Example 2: QUANTITATIVE with MANUAL

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Manual Measurement",
        "name_id": "manual_meas",
        "sample_amount": 5,
        "source": "MANUAL",
        "source_instrument_id": null,
        "source_derived_name_id": null,
        "source_tool_model": null,
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
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 30,
        "tolerance_minus": null,
        "tolerance_plus": null
      }
    }
  ],
  "measurement_groups": null
}
```

---

### Example 3: QUALITATIVE (Simplified!)

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "VO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Visual Check",
        "name_id": "visual_check",
        "sample_amount": 5,
        "nature": "QUALITATIVE"
      },
      "variables": null,
      "pre_processing_formulas": null,
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Visual Quality",
          "options": ["Good", "Fair", "Poor"],
          "passing_criteria": "Must be Good or Fair"
        }
      },
      "rule_evaluation_setting": null
    }
  ],
  "measurement_groups": null
}
```

---

### Example 4: QUANTITATIVE with Cross-Reference Formula

```json
{
  "basic_info": {
    "product_category_id": 93,
    "product_name": "COTO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "name_id": "thickness_a",
        "sample_amount": 3,
        "source": "INSTRUMENT",
        "source_instrument_id": 1,
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
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
    },
    {
      "setup": {
        "name": "Thickness B",
        "name_id": "thickness_b",
        "sample_amount": 3,
        "source": "INSTRUMENT",
        "source_instrument_id": 2,
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
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
    },
    {
      "setup": {
        "name": "Average Thickness",
        "name_id": "avg_thickness",
        "sample_amount": 3,
        "source": "DERIVED",
        "source_derived_name_id": "thickness_a",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "calculated_avg",
          "formula": "=(avg(thickness_a) + avg(thickness_b)) / 2",
          "is_show": true
        }
      ],
      "pre_processing_formulas": null,
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
  ],
  "measurement_groups": null
}
```

---

## 🎯 Validation Rules Summary

### QUANTITATIVE Requirements

| Field | Required? | Notes |
|-------|-----------|-------|
| `setup.source` | ✅ Yes | Must be: INSTRUMENT, TOOL, MANUAL, or DERIVED |
| `setup.type` | ✅ Yes | Must be: SINGLE or BEFORE_AFTER |
| `rule_evaluation_setting` | ✅ Yes | Cannot be null |
| `qualitative_setting` | ❌ No | Must be null |

### QUALITATIVE Requirements

| Field | Required? | Notes |
|-------|-----------|-------|
| `setup.source` | ❌ No | Optional (can be omitted) |
| `setup.type` | ❌ No | Optional (can be omitted) |
| `evaluation_type` | ✅ Yes | Must be: SKIP_CHECK |
| `qualitative_setting` | ✅ Yes | Cannot be null |
| `rule_evaluation_setting` | ❌ No | Must be null |

---

## 🔗 Related Endpoints

### Authentication
```
POST /api/v1/login
```

### Products
```
POST   /api/v1/products
GET    /api/v1/products
GET    /api/v1/products/{productId}
GET    /api/v1/products/categories
```

### Instruments
```
GET    /api/v1/measurement-instruments
GET    /api/v1/measurement-instruments/{id}
```

### Tools
```
GET    /api/v1/tools
GET    /api/v1/tools/models
GET    /api/v1/tools/by-model?model={modelName}
```

### Measurements
```
POST   /api/v1/product-measurement
GET    /api/v1/product-measurement
GET    /api/v1/product-measurement/{id}
POST   /api/v1/product-measurement/{id}/submit
```

---

## 📞 Support & Questions

**Backend API Base URL:**
- Development: `http://localhost:8000/api/v1`
- Production: `http://103.236.140.19:2020/api/v1`

**Need Help?**
- Check error messages in response `data` field
- Refer to troubleshooting section above
- Contact backend team for API issues

---

## 📌 Quick Reference

### Must Remember

1. **source_instrument_id** = INTEGER ID (from `/measurement-instruments`)
2. **source_tool_model** = STRING model (from `/tools/models`)
3. **product_category_id** = INTEGER ID (from `/products/categories`)
4. **QUALITATIVE** doesn't need `source` & `type`
5. **QUANTITATIVE** requires `source`, `type`, and `rule_evaluation_setting`
6. **Formulas** access RAW DATA from other measurement items
7. **Cross-reference** works (but order matters!)

---

**Document Version:** 1.0  
**Last Updated:** November 23, 2024  
**API Version:** 1.1.0

