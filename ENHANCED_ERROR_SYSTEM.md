# Enhanced Error System Documentation

## Overview

Sistem error yang ditingkatkan untuk memberikan feedback yang lebih informatif dan mudah dipahami oleh user/frontend saat terjadi validasi error dalam pembuatan atau update product.

## Changes Summary

### 1. Endpoint Updates

#### a. `GET /api/v1/product-measurement/available-products`
**Added field:** `product_spec_name`

**Response format:**
```json
{
  "success": true,
  "message": "Available products retrieved successfully",
  "data": [
    {
      "id": "PRD-ABC12345",
      "product_category_id": 1,
      "product_category_name": "Cable",
      "product_name": "AVSSH",
      "product_spec_name": "AVSSH 0.75 Black",  // ✅ NEW
      "ref_spec_number": "REF-001",
      "nom_size_vo": "0.75",
      "article_code": "ART-001",
      "no_document": "DOC-001",
      "no_doc_reference": "DOCREF-001",
      "color": "Black",
      "size": "0.75"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_page": 5,
    "limit": 10,
    "total_docs": 45
  }
}
```

---

## 2. Enhanced Error Response Format

### Error Response Structure

```typescript
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR" | "FORMULA_VALIDATION_ERROR",
  "data": {
    "error_id": string,
    "data": {
      "basic_info": BasicInfoError[],
      "measurement_points": MeasurementPointError[]
    }
  }
}
```

### Basic Info Error Format

```typescript
interface BasicInfoError {
  field: string;                    // Field name yang error
  code: BASIC_INFO_ERROR_ENUM;     // Error code
  message: string;                  // User-friendly message
}
```

### Measurement Point Error Format

```typescript
interface MeasurementPointError {
  measurement_item: {
    id: string | null;              // name_id dari measurement item
    name: string | null;            // Display name
  };
  context: {
    section: MEASUREMENT_POINT_SECTION_ENUM;  // Section yang bermasalah
    entity_name: string | null;               // Nama entity spesifik (variable, formula, dll)
  };
  code: MEASUREMENT_POINT_ERROR_ENUM;         // Error code
  message: string;                             // User-friendly message
}
```

---

## 3. Error Enums

### BASIC_INFO_ERROR_ENUM

| Code | Description | Example |
|------|-------------|---------|
| `REQUIRED` | Field wajib diisi | "Nama produk wajib diisi" |
| `INVALID_FORMAT` | Format tidak valid | "Format tidak valid" |
| `INVALID_VALUE` | Nilai tidak valid | "Nilai tidak valid" |
| `INVALID_CATEGORY` | Kategori tidak valid | "Kategori produk tidak ditemukan" |
| `INVALID_PRODUCT_NAME` | Nama produk tidak valid untuk kategori | "Nama produk 'AVSSH' tidak valid untuk kategori 'Wire'" |
| `ALREADY_EXISTS` | Data sudah ada | "Produk dengan spesifikasi ini sudah ada" |
| `TOO_LONG` | Nilai terlalu panjang | "Warna terlalu panjang (maksimal 50 karakter)" |
| `TOO_SHORT` | Nilai terlalu pendek | "Nama terlalu pendek" |
| `INVALID_SPEC_NUMBER` | Spec number tidak valid | "Spec number tidak valid" |
| `INVALID_ARTICLE_CODE` | Article code tidak valid | "Article code tidak valid" |
| `INVALID_COLOR` | Warna tidak valid | "Warna tidak valid" |
| `INVALID_SIZE` | Size tidak valid | "Size tidak valid" |

### MEASUREMENT_POINT_ERROR_ENUM

| Code | Description | Example |
|------|-------------|---------|
| `REQUIRED` | Field wajib diisi | "Nama measurement item wajib diisi" |
| `INVALID_NAME` | Nama tidak valid | "nama 'Thickness A' tidak valid karena mengandung spasi" |
| `INVALID_FORMAT` | Format tidak valid | "Format tidak sesuai" |
| `INVALID_FORMULA` | Formula tidak valid | "Formula harus diawali dengan '='" |
| `NOT_FOUND` | Tidak ditemukan | "Measurement point tidak ditemukan" |
| `DUPLICATE` | Duplikat | "Nama sudah digunakan" |
| `LOGICAL_CONFLICT` | Konflik logika | "Type BEFORE_AFTER tidak bisa menggunakan raw data" |
| `OUT_OF_RANGE` | Nilai di luar range | "Sample amount harus >= 0" |
| `MISSING_DEPENDENCY` | Dependensi tidak ditemukan | "Formula 'normalized' tidak valid karena 'cross_section' tidak ditemukan" |
| `CIRCULAR_DEPENDENCY` | Circular dependency | "Circular dependency terdeteksi" |
| `INVALID_TYPE` | Tipe tidak valid | "Tipe tidak valid" |
| `INVALID_SOURCE` | Source tidak valid | "Source tidak valid" |
| `INVALID_NATURE` | Nature tidak valid | "Nature harus QUALITATIVE atau QUANTITATIVE" |
| `INVALID_EVALUATION_TYPE` | Evaluation type tidak valid | "Evaluation type tidak valid" |
| `MISSING_CONFIGURATION` | Konfigurasi tidak lengkap | "Konfigurasi tidak lengkap" |
| `INVALID_RULE` | Rule tidak valid | "Rule harus MIN, MAX, atau BETWEEN" |
| `INVALID_TOLERANCE` | Tolerance tidak valid | "Tolerance minus harus berupa angka" |
| `INVALID_SAMPLE_AMOUNT` | Sample amount tidak valid | "Sample amount tidak valid" |
| `SPECIAL_CHARACTER` | Mengandung karakter spesial | "nama mengandung karakter spesial" |
| `CONTAINS_SPACE` | Mengandung spasi | "Variable 'cross section' tidak valid karena mengandung spasi" |
| `UPPERCASE_NOT_ALLOWED` | Huruf besar tidak diizinkan | "nama 'ThicknessA' tidak valid karena mengandung huruf besar" |

### MEASUREMENT_POINT_SECTION_ENUM

| Code | Description |
|------|-------------|
| `setup` | Setup measurement point (name, name_id, sample_amount, nature, source, type) |
| `variable` | Variables (FIXED, MANUAL, FORMULA) |
| `pre_processing_formula` | Pre-processing formulas |
| `evaluation` | Evaluation settings |
| `rule_evaluation` | Rule/Standard evaluation settings |
| `group` | Grouping measurement items |
| `joint_formula` | Joint setting formulas |
| `qualitative_setting` | Qualitative evaluation settings |

---

## 4. Error Examples

### Example 1: Invalid Measurement Item Name

**Request:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness (A)",  // ❌ Contains special characters
        "name_id": "thickness_a",
        "sample_amount": 5,
        "nature": "QUANTITATIVE"
      }
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {
            "id": "thickness_a",
            "name": "Thickness (A)"
          },
          "context": {
            "section": "setup",
            "entity_name": "name"
          },
          "code": "SPECIAL_CHARACTER",
          "message": "Measurement Item \"Thickness (A)\" tidak valid karena mengandung karakter spesial"
        }
      ]
    }
  }
}
```

### Example 2: Formula Missing Dependency

**Request:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Room Temperature",
        "name_id": "room_temp",
        "sample_amount": 3,
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=cross_section*single_value"  // ❌ cross_section not defined
        }
      ]
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Formula validation failed",
  "error_code": "FORMULA_VALIDATION_ERROR",
  "data": {
    "error_id": "FORMULA_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {
            "id": "room_temp",
            "name": "Room Temperature"
          },
          "context": {
            "section": "pre_processing_formula",
            "entity_name": "normalized"
          },
          "code": "MISSING_DEPENDENCY",
          "message": "Formula \"normalized\" tidak valid karena \"cross_section\" tidak ditemukan. Pastikan \"cross_section\" sudah didefinisikan sebelumnya sebagai variable atau measurement item."
        }
      ]
    }
  }
}
```

### Example 3: Variable Name Contains Space

**Request:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness",
        "name_id": "thickness",
        "sample_amount": 5,
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FIXED",
          "name": "cross section",  // ❌ Contains space
          "value": 2.5
        }
      ]
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {
            "id": "thickness",
            "name": "Thickness"
          },
          "context": {
            "section": "variable",
            "entity_name": "cross section"
          },
          "code": "CONTAINS_SPACE",
          "message": "Variable \"cross section\" tidak valid karena mengandung spasi. Gunakan underscore (_) sebagai pengganti"
        }
      ]
    }
  }
}
```

### Example 4: Invalid Product Name for Category

**Request:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "INVALID_PRODUCT"  // ❌ Not in category's product list
  },
  "measurement_points": [...]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [
        {
          "field": "product_name",
          "code": "INVALID_PRODUCT_NAME",
          "message": "Nama produk \"INVALID_PRODUCT\" tidak valid untuk kategori \"Cable\". Nama yang tersedia: AVSSH, AVSS, CAVUS"
        }
      ],
      "measurement_points": []
    }
  }
}
```

### Example 5: Missing Required Rule Evaluation

**Request:**
```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "AVSSH"
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
      // ❌ Missing rule_evaluation_setting
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {
            "id": "thickness",
            "name": "Thickness"
          },
          "context": {
            "section": "rule_evaluation",
            "entity_name": "rule"
          },
          "code": "REQUIRED",
          "message": "Measurement item \"Thickness\" harus memiliki rule evaluasi (MIN, MAX, atau BETWEEN)"
        }
      ]
    }
  }
}
```

### Example 6: Invalid Tolerance for BETWEEN Rule

**Request:**
```json
{
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness",
        "name_id": "thickness"
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 2.5
        // ❌ Missing tolerance_minus and tolerance_plus
      }
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {
            "id": "thickness",
            "name": "Thickness"
          },
          "context": {
            "section": "rule_evaluation",
            "entity_name": "tolerance_minus"
          },
          "code": "INVALID_TOLERANCE",
          "message": "Measurement item \"Thickness\" dengan rule BETWEEN harus memiliki tolerance_minus yang valid"
        },
        {
          "measurement_item": {
            "id": "thickness",
            "name": "Thickness"
          },
          "context": {
            "section": "rule_evaluation",
            "entity_name": "tolerance_plus"
          },
          "code": "INVALID_TOLERANCE",
          "message": "Measurement item \"Thickness\" dengan rule BETWEEN harus memiliki tolerance_plus yang valid"
        }
      ]
    }
  }
}
```

---

## 5. Implementation Details

### New Files Created

1. **`app/Enums/BasicInfoErrorEnum.php`**
   - Enum untuk basic info error codes

2. **`app/Enums/MeasurementPointErrorEnum.php`**
   - Enum untuk measurement point error codes

3. **`app/Enums/MeasurementPointSectionEnum.php`**
   - Enum untuk measurement point sections

4. **`app/Helpers/ValidationErrorHelper.php`**
   - Helper class untuk generate structured error messages
   - Methods:
     - `createBasicInfoError()` - Create basic info error
     - `createMeasurementPointError()` - Create measurement point error
     - `formatErrorResponse()` - Format complete error response
     - `generateInvalidNameMessage()` - Generate user-friendly name error message
     - `generateInvalidFormulaMessage()` - Generate formula error message
     - `generateVariableErrorMessage()` - Generate variable error message
     - `validateNameFormat()` - Validate name format
     - `extractMissingDependencies()` - Extract missing dependencies from formula

### Modified Files

1. **`app/Http/Controllers/Api/V1/ProductController.php`**
   - Added imports for new enums and helper
   - Added `validateProductEnhanced()` method
   - Added `validateAndProcessFormulasEnhanced()` method
   - Added `validateSingleFormulaEnhanced()` method
   - Modified `store()` method to use enhanced validation

2. **`app/Http/Controllers/Api/V1/ProductMeasurementController.php`**
   - Updated `getAvailableProducts()` to include `product_spec_name`

---

## 6. Benefits

### For Frontend/Flutter Developers

1. **Contextual Information**
   - Know exactly which measurement item has the error
   - Know which section (setup, variable, formula, etc.) is problematic
   - Know the specific entity (variable name, formula name, etc.)

2. **User-Friendly Messages**
   - Clear explanation of what went wrong
   - Actionable guidance on how to fix it
   - In Indonesian language for better UX

3. **Structured Data**
   - Can programmatically parse and display errors
   - Can highlight specific fields in the UI
   - Can group errors by measurement item or section

### For Backend Developers

1. **Maintainable**
   - Centralized error message generation
   - Consistent error format across the system
   - Easy to add new error types

2. **Extensible**
   - Easy to add new error codes
   - Easy to add new sections
   - Helper methods can be reused

3. **Type-Safe**
   - Using enums ensures consistency
   - IDE autocomplete support
   - Prevents typos in error codes

---

## 7. Migration Notes

### Backward Compatibility

The old error format is still supported but deprecated. It's recommended to update frontend code to handle the new structured format.

### Old Format (Deprecated)
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "data": {
    "measurement_points.0": ["Name is required"]
  }
}
```

### New Format (Recommended)
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "PRODUCT_VALIDATION_ERROR",
  "data": {
    "error_id": "PRODUCT_VALIDATION_ERROR",
    "data": {
      "basic_info": [],
      "measurement_points": [
        {
          "measurement_item": {...},
          "context": {...},
          "code": "REQUIRED",
          "message": "Nama measurement item wajib diisi"
        }
      ]
    }
  }
}
```

---

## 8. Testing

### Test Cases

1. **Basic Info Validation**
   - Invalid product name
   - Missing required fields
   - Field too long

2. **Measurement Point Name Validation**
   - Name with special characters
   - Name with spaces
   - Name with uppercase
   - Name starting with number

3. **Variable Validation**
   - Variable name with space
   - Variable name with uppercase
   - FIXED variable without value

4. **Formula Validation**
   - Formula without '=' prefix
   - Formula referencing undefined variable
   - Formula referencing future measurement item

5. **Rule Evaluation Validation**
   - Missing rule
   - Missing value
   - BETWEEN without tolerance

---

## 9. Future Enhancements

1. Add validation for circular dependencies
2. Add suggestions for similar valid names when invalid name is provided
3. Add error severity levels (warning, error, critical)
4. Add multi-language support
5. Add error documentation links

---

## Contact

For questions or issues, please contact the backend team or create an issue in the project repository.
