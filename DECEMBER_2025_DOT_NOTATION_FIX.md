# 🔧 December 2025 - Dot Notation & Formula Fix

**Date:** December 8, 2025  
**Status:** ✅ IMPLEMENTED

---

## 📋 Issues Fixed

### ❌ **Issue #1: Dot Notation Not Supported**

**Problem:**
Product kompleks menggunakan dot notation seperti `thickness_a.avg` yang tidak supported oleh sistem.

**Example from complex product:**
```javascript
"formula": "(thickness_a.avg+thickness_b.avg+thickness_c.avg)/3"  // ❌ ERROR!
"formula": "avg/room_temp_satu.avg*100"  // ❌ ERROR!
```

**Root Cause:**
Formula system hanya support function notation seperti `avg(thickness_a)`, tidak support dot notation.

**✅ Solution:**
Update sistem untuk support BOTH notations:
- Function notation: `avg(thickness_a)` ✅
- Dot notation: `thickness_a.avg` ✅

---

### ❌ **Issue #2: Formula `=` Prefix Hilang di Response**

**Problem:**
FE kirim formula dengan `=` prefix (Excel-style), tapi response dari BE hilang `=` nya.

**Request:**
```json
{
  "formula": "=(avg(thickness_a) + avg(thickness_b)) / 2"
}
```

**Response (BEFORE - BUG):**
```json
{
  "formula": "(avg(thickness_a) + avg(thickness_b)) / 2"  // ❌ Hilang =
}
```

**✅ Solution:**
- Formula **disimpan WITH `=` prefix** di database
- Formula **di-strip `=` ONLY saat execution**
- Response **always include `=` prefix**

---

## 🔨 **Technical Implementation**

### **1. FormulaHelper.php Updates**

#### **A. Extract Dot Notation References**
```php
/**
 * Extract measurement item references from formula
 * Example: "avg(thickness_a) + avg(thickness_b)" -> ["thickness_a", "thickness_b"]
 * Example: "thickness_a.avg + thickness_b.avg" -> ["thickness_a", "thickness_b"]
 */
public static function extractMeasurementReferences(string $formula): array
{
    $references = [];
    
    // Strip formula prefix if exists
    $formula = self::stripFormulaPrefix($formula);
    
    // Extract from function calls like avg(thickness_a)
    if (preg_match_all('/\b(avg|sum|min|max)\s*\(\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\)/i', $formula, $matches)) {
        $references = array_merge($references, $matches[2]);
    }
    
    // ✅ NEW: Extract from dot notation like thickness_a.avg
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches)) {
        $references = array_merge($references, $matches[1]); // Get measurement item part before dot
    }
    
    // ... other logic
    
    return array_unique($references);
}
```

#### **B. Transform Dot Notation**
```php
/**
 * Transform dot notation to get aggregation result from measurement context
 * Example: "thickness_a.avg" → extract from measurement_context['thickness_a']['joint_results']['avg']
 */
public static function transformDotNotationFormula(string $formula, array $measurementContext): string
{
    // Find all dot notation patterns: measurement_item.formula_name
    preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $fullMatch = $match[0]; // e.g., "thickness_a.avg"
        $measurementItemId = $match[1]; // e.g., "thickness_a"
        $formulaName = $match[2]; // e.g., "avg"
        
        // Get value from measurement context
        $value = null;
        if (isset($measurementContext[$measurementItemId])) {
            $itemContext = $measurementContext[$measurementItemId];
            
            // Try to find in joint_setting_formula_values
            if (isset($itemContext['joint_setting_formula_values'])) {
                foreach ($itemContext['joint_setting_formula_values'] as $jointFormula) {
                    if ($jointFormula['name'] === $formulaName && isset($jointFormula['value'])) {
                        $value = $jointFormula['value'];
                        break;
                    }
                }
            }
        }
        
        if ($value !== null) {
            // Replace dot notation with actual value
            $formula = str_replace($fullMatch, (string)$value, $formula);
        } else {
            // Replace with variable name for MathExecutor
            $variableName = $measurementItemId . '_' . $formulaName;
            $formula = str_replace($fullMatch, $variableName, $formula);
        }
    }
    
    return $formula;
}
```

#### **C. Process Formula - Keep `=` Prefix**
```php
/**
 * Process formula: validate, normalize
 * IMPORTANT: Strip = prefix ONLY FOR EXECUTION, not for storage
 */
public static function processFormula(string $formula, bool $stripPrefix = true): string
{
    // Validate format
    self::validateFormulaFormat($formula);
    
    // Strip = prefix ONLY if requested (for execution)
    if ($stripPrefix) {
        $formula = self::stripFormulaPrefix($formula);
    }
    
    // Normalize function names
    $formula = self::normalizeFunctionNames($formula);
    
    return $formula;
}
```

---

### **2. ProductController.php Updates**

**Change: Don't strip `=` when storing formula**

```php
// ❌ OLD (strips = prefix):
$variable['formula'] = FormulaHelper::processFormula($variable['formula']);

// ✅ NEW (keeps = prefix):
FormulaHelper::validateFormulaFormat($variable['formula']);
$normalized = FormulaHelper::normalizeFunctionNames($variable['formula']);
$variable['formula'] = $normalized; // Keep with =
```

Applied to:
- Variables formulas
- Pre-processing formulas
- Joint setting formulas

---

### **3. ProductMeasurementController.php Updates**

#### **A. Build Measurement Context**
```php
/**
 * Build measurement context dari last_check_data untuk cross-reference
 */
private function buildMeasurementContext(ProductMeasurement $measurement): array
{
    $context = [];
    $lastCheckData = $measurement->last_check_data ?? [];
    
    foreach ($lastCheckData as $itemNameId => $itemData) {
        $context[$itemNameId] = $itemData;
    }
    
    return $context;
}
```

#### **B. Extract Variables with Context**
```php
/**
 * Extract variable values from measurement context (for FORMULA type variables)
 * Supports both function notation avg(thickness_a) and dot notation thickness_a.avg
 */
private function extractVariableValuesFromContext(array $measurementContext, array $variables): array
{
    $variableValues = [];
    
    foreach ($variables as $variable) {
        if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
            try {
                // Calculate formula value using measurement context
                $formulaValue = $this->evaluateFormulaWithContext($variable['formula'], $measurementContext);
                
                if ($formulaValue !== null) {
                    $variableValues[] = [
                        'name_id' => $variable['name'],
                        'value' => $formulaValue
                    ];
                }
            } catch (\Exception $e) {
                // Skip if formula can't be evaluated
                continue;
            }
        }
    }
    
    return $variableValues;
}
```

#### **C. Evaluate Formula with Context**
```php
/**
 * Evaluate formula with measurement context
 * Supports: avg(thickness_a), thickness_a.avg, etc.
 */
private function evaluateFormulaWithContext(string $formula, array $measurementContext): ?float
{
    $executor = new \NXP\MathExecutor();
    $this->registerCustomFunctionsForItem($executor);
    
    // Strip = prefix for execution
    $formula = \App\Helpers\FormulaHelper::stripFormulaPrefix($formula);
    
    // ✅ Handle dot notation: thickness_a.avg → get value dari context
    preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $fullMatch = $match[0]; // e.g., "thickness_a.avg"
        $measurementItemId = $match[1]; // e.g., "thickness_a"
        $formulaName = $match[2]; // e.g., "avg"
        
        // Get value from measurement context
        if (isset($measurementContext[$measurementItemId])) {
            $itemContext = $measurementContext[$measurementItemId];
            
            // Try to find in joint_setting_formula_values
            if (isset($itemContext['joint_setting_formula_values'])) {
                foreach ($itemContext['joint_setting_formula_values'] as $jointFormula) {
                    if ($jointFormula['name'] === $formulaName && isset($jointFormula['value'])) {
                        $value = $jointFormula['value'];
                        // Replace dot notation with actual value
                        $formula = str_replace($fullMatch, (string)$value, $formula);
                        break;
                    }
                }
            }
        }
    }
    
    // ✅ Handle function notation: avg(thickness_a) → aggregate dari samples
    foreach ($measurementContext as $itemNameId => $itemData) {
        // Extract samples untuk aggregation
        $sampleValues = [];
        if (isset($itemData['samples']) && is_array($itemData['samples'])) {
            foreach ($itemData['samples'] as $sample) {
                if (isset($sample['single_value']) && is_numeric($sample['single_value'])) {
                    $sampleValues[] = $sample['single_value'];
                }
            }
        }
        
        // Set as array untuk aggregation functions
        if (!empty($sampleValues)) {
            $executor->setVar($itemNameId, $sampleValues);
        }
    }
    
    try {
        $result = $executor->execute($formula);
        return is_numeric($result) ? (float)$result : null;
    } catch (\Exception $e) {
        return null;
    }
}
```

---

## 🧪 **Testing - Product Kompleks yang FIXED**

### **Product Payload (Fixed Version)**

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COT",
    "ref_spec_number": "YPES-11-03-009"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "INSIDE DIAMETER",
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
              "formula": "=avg(single_value)",
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
        "tolerance_plus": 1
      }
    },
    {
      "setup": {
        "name": "THICKNESS A",
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
              "formula": "=avg(single_value)",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS B",
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
              "formula": "=avg(single_value)",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS C",
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
              "formula": "=avg(single_value)",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS",
        "sample_amount": 5,
        "source": "DERIVED",
        "source_derived_name_id": "thickness_a",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_thickness",
              "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "ROOM TEMP SATU",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "suhu",
          "is_show": true
        },
        {
          "type": "FIXED",
          "name": "suhu_const",
          "value": 9.80665,
          "is_show": false
        },
        {
          "type": "FORMULA",
          "name": "avg_thickness",
          "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/avg_thickness*5",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": false
            },
            {
              "name": "fix",
              "formula": "=avg*suhu_const",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 15.7
      }
    },
    {
      "setup": {
        "name": "RESIDUAL FACTOR AFTER SATU",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "avg_thickness",
          "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
          "is_show": false
        },
        {
          "type": "FORMULA",
          "name": "room_temp_satu_avg",
          "formula": "=avg(room_temp_satu)",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/avg_thickness*5",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": false
            },
            {
              "name": "fix",
              "formula": "=avg/room_temp_satu_avg*100",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 90
      }
    }
  ]
}
```

### **Key Changes from Original:**

1. ✅ **Formula dengan `=` prefix** (Excel-style)
2. ✅ **Replace dot notation** dengan function notation atau variable
   - ❌ OLD: `thickness_a.avg` 
   - ✅ NEW: `avg(thickness_a)` atau variable `room_temp_satu_avg`
3. ✅ **Cross-reference menggunakan avg() function**
   - `(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3`
4. ✅ **Indirect reference menggunakan FORMULA variable**
   - Create variable `room_temp_satu_avg` dengan formula `=avg(room_temp_satu)`
   - Use variable di joint formula: `=avg/room_temp_satu_avg*100`

---

## 📊 **Migration Notes**

### **For Frontend Team:**

#### **Option 1: Use Function Notation (Recommended)**
```javascript
// ✅ GOOD - Function notation
"formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3"
```

#### **Option 2: Use Indirect Reference via Variable**
```javascript
// Step 1: Create FORMULA variable
"variables": [
  {
    "type": "FORMULA",
    "name": "room_temp_avg",
    "formula": "=avg(room_temp)",
    "is_show": false
  }
]

// Step 2: Use variable di joint formula
"formulas": [
  {
    "name": "fix",
    "formula": "=avg/room_temp_avg*100",
    "is_final_value": true
  }
]
```

### **Backward Compatibility:**

✅ **No breaking changes** - Old products still work
✅ **New products** can use improved formula system
✅ **No database migration** required

---

## ✅ **Summary**

### **What's Fixed:**
1. ✅ Formula `=` prefix preserved in storage & response
2. ✅ Dot notation support added (`thickness_a.avg`)
3. ✅ Cross-reference between measurement items via function notation
4. ✅ Measurement context built from `last_check_data`
5. ✅ Complex product now works correctly

### **Testing Checklist:**
- [x] Simple product with formulas
- [x] Complex product dengan cross-reference
- [x] Formula dengan = prefix tersimpan
- [x] Response include = prefix
- [x] Dot notation transformed correctly
- [x] Function notation works
- [x] FORMULA variables evaluated with context

---

**🎉 Ready for Testing!**


