# Product Measurement Formula Fix - December 2025

## 📋 Ringkasan

Dokumentasi ini menjelaskan perbaikan yang dilakukan untuk mengatasi 5 masalah terkait perhitungan formula pada endpoint `/samples/check` dan `/save-progress` untuk Product Measurement.

## 🐛 Masalah yang Dilaporkan

### 1. Variable `cross_section` dengan formula `=thickness.average*5` tidak muncul di `variable_values`
- **Masalah**: Formula variable yang menggunakan dot notation tidak terhitung otomatis
- **Dampak**: Variable `cross_section` tidak muncul di response

### 2. Pre-processing formula values tidak muncul
- **Masalah**: `pre_processing_formula_values.value` tidak muncul meskipun datanya sudah lengkap
- **Dampak**: User tidak bisa melihat hasil perhitungan pre-processing formulas

### 3. Joint setting formula values tidak muncul
- **Masalah**: `joint_setting_formula_values.value` tidak muncul meskipun datanya sudah lengkap
- **Dampak**: User tidak bisa melihat hasil perhitungan joint formulas

### 4. FIXED variables tidak di-include dalam response
- **Masalah**: FIXED variables (seperti `temp_const = 9.80665`) tidak muncul di `variable_values`
- **Dampak**: Formula yang membutuhkan FIXED variables tidak bisa dieksekusi

### 5. Manual input variable_values membuat point 2 muncul, tapi point 3 masih belum muncul
- **Masalah**: Ketika user manual input `variable_values`, pre-processing formulas muncul tapi joint formulas masih belum muncul karena FIXED variables tidak tersedia

## ✅ Perbaikan yang Dilakukan

### 1. Fungsi Baru: `buildCompleteVariableValues()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Fungsi**: Membangun `variable_values` lengkap yang mencakup semua tipe variables (FIXED, MANUAL, FORMULA)

**Implementasi**:
```php
private function buildCompleteVariableValues(array $measurementPoint, array $manualVariables, array $measurementContext): array
{
    $variableValues = [];
    $variables = $measurementPoint['variables'] ?? [];
    
    // Convert manual variables to map for easy lookup
    $manualVariablesMap = [];
    foreach ($manualVariables as $var) {
        $manualVariablesMap[$var['name_id']] = $var['value'];
    }
    
    foreach ($variables as $variable) {
        $varName = $variable['name'];
        $varType = $variable['type'];
        
        if ($varType === 'FIXED') {
            // ✅ Include FIXED variables
            $variableValues[] = [
                'name_id' => $varName,
                'value' => $variable['value']
            ];
        } elseif ($varType === 'MANUAL') {
            // Include MANUAL variables if provided
            if (isset($manualVariablesMap[$varName])) {
                $variableValues[] = [
                    'name_id' => $varName,
                    'value' => $manualVariablesMap[$varName]
                ];
            }
        } elseif ($varType === 'FORMULA' && isset($variable['formula'])) {
            // ✅ Calculate FORMULA variables with dot notation support
            try {
                $formulaValue = $this->evaluateFormulaWithContext($variable['formula'], $measurementContext, $manualVariablesMap);
                
                if ($formulaValue !== null) {
                    $variableValues[] = [
                        'name_id' => $varName,
                        'value' => $formulaValue
                    ];
                }
            } catch (\Exception $e) {
                // Skip if formula can't be evaluated (dependencies not ready)
                continue;
            }
        }
    }
    
    return $variableValues;
}
```

**Perubahan di `checkSamples()`**:
```php
// Sebelum:
$variableValues = $request->variable_values ?? [];
if (empty($variableValues) && isset($measurementPoint['variables'])) {
    $variableValues = $this->extractVariableValuesFromContext($measurementContext, $measurementPoint['variables']);
}

// Sesudah:
$variableValues = $this->buildCompleteVariableValues($measurementPoint, $variableValues, $measurementContext);
```

### 2. Perbaikan: `buildMeasurementContext()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Masalah**: Hanya mengambil dari `last_check_data`, tidak mengambil dari `measurement_results` yang sudah tersimpan

**Perbaikan**: Mengambil dari kedua sumber dengan prioritas `last_check_data` (lebih recent)

```php
private function buildMeasurementContext(ProductMeasurement $measurement): array
{
    $context = [];
    
    // ✅ FIX: First, get from measurement_results (saved data)
    $measurementResults = $measurement->measurement_results ?? [];
    foreach ($measurementResults as $result) {
        $itemNameId = $result['measurement_item_name_id'] ?? null;
        if ($itemNameId) {
            $context[$itemNameId] = [
                'samples' => $result['samples'] ?? [],
                'variable_values' => $result['variable_values'] ?? [],
                'status' => $result['status'] ?? null,
                'joint_setting_formula_values' => $result['joint_setting_formula_values'] ?? null,
            ];
        }
    }
    
    // ✅ FIX: Then, override with last_check_data (more recent checked data)
    $lastCheckData = $measurement->last_check_data ?? [];
    foreach ($lastCheckData as $itemNameId => $itemData) {
        $context[$itemNameId] = $itemData;
    }
    
    return $context;
}
```

### 3. Perbaikan: `evaluateFormulaWithContext()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**:
- Menambahkan parameter `$manualVariables` untuk mendukung perhitungan formula yang membutuhkan manual variables
- Memperbaiki handling dot notation (`thickness.average`)

```php
private function evaluateFormulaWithContext(string $formula, array $measurementContext, array $manualVariables = []): ?float
{
    $executor = new \NXP\MathExecutor();
    $this->registerCustomFunctionsForItem($executor);
    
    // Strip = prefix for execution
    $formula = \App\Helpers\FormulaHelper::stripFormulaPrefix($formula);
    
    // ✅ FIX: Handle dot notation: thickness.average → get value dari context
    preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $fullMatch = $match[0]; // e.g., "thickness.average"
        $measurementItemId = $match[1]; // e.g., "thickness"
        $formulaName = $match[2]; // e.g., "average"
        
        // Get value from measurement context
        if (isset($measurementContext[$measurementItemId])) {
            $itemContext = $measurementContext[$measurementItemId];
            
            // Try to find in joint_setting_formula_values
            if (isset($itemContext['joint_setting_formula_values'])) {
                foreach ($itemContext['joint_setting_formula_values'] as $jointFormula) {
                    if ($jointFormula['name'] === $formulaName && isset($jointFormula['value']) && is_numeric($jointFormula['value'])) {
                        $value = $jointFormula['value'];
                        // Replace dot notation with actual value
                        $formula = str_replace($fullMatch, (string)$value, $formula);
                        break;
                    }
                }
            }
        }
    }
    
    // ✅ FIX: Set manual variables first (for use in formulas)
    foreach ($manualVariables as $name => $value) {
        if (is_numeric($value)) {
            $executor->setVar($name, $value);
        }
    }
    
    // Handle function notation: avg(thickness_a) → aggregate dari samples
    foreach ($measurementContext as $itemNameId => $itemData) {
        $sampleValues = [];
        if (isset($itemData['samples']) && is_array($itemData['samples'])) {
            foreach ($itemData['samples'] as $sample) {
                if (isset($sample['single_value']) && is_numeric($sample['single_value'])) {
                    $sampleValues[] = $sample['single_value'];
                }
            }
        }
        
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

### 4. Perbaikan: `processPreProcessingFormulasForItem()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**:
- Menyertakan FIXED variables dalam perhitungan
- Menyertakan semua variable_values (FIXED, MANUAL, FORMULA)
- Menambahkan support untuk dot notation dalam pre-processing formulas

```php
private function processPreProcessingFormulasForItem(array $formulas, array $rawValues, array $variables, array $measurementContext = []): array
{
    $results = [];
    $executor = new \NXP\MathExecutor();
    
    // Register custom functions
    $this->registerCustomFunctionsForItem($executor);
    
    // ✅ FIX: Set measurement context values for cross-reference (dot notation support)
    foreach ($measurementContext as $itemNameId => $itemData) {
        // For function notation like avg(thickness_a)
        $sampleValues = [];
        if (isset($itemData['samples']) && is_array($itemData['samples'])) {
            foreach ($itemData['samples'] as $sample) {
                if (isset($sample['single_value']) && is_numeric($sample['single_value'])) {
                    $sampleValues[] = $sample['single_value'];
                }
            }
        }
        if (!empty($sampleValues)) {
            $executor->setVar($itemNameId, $sampleValues);
        }
        
        // ✅ FIX: Also set joint_setting_formula_values for dot notation (thickness.average)
        if (isset($itemData['joint_setting_formula_values'])) {
            foreach ($itemData['joint_setting_formula_values'] as $jointFormula) {
                if (isset($jointFormula['name']) && isset($jointFormula['value']) && is_numeric($jointFormula['value'])) {
                    // Set as itemNameId_formulaName for dot notation support
                    $varName = $itemNameId . '_' . $jointFormula['name'];
                    $executor->setVar($varName, $jointFormula['value']);
                }
            }
        }
    }
    
    // Set raw values as variables
    foreach ($rawValues as $key => $value) {
        if (is_numeric($value)) {
            $executor->setVar($key, $value);
        } elseif (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                if (is_numeric($subValue)) {
                    $executor->setVar($subKey, $subValue);
                }
            }
        }
    }
    
    // ✅ FIX: Set variable values as variables (includes FIXED, MANUAL, FORMULA)
    foreach ($variables as $variable) {
        if (isset($variable['name_id']) && isset($variable['value']) && is_numeric($variable['value'])) {
            $executor->setVar($variable['name_id'], $variable['value']);
        }
    }
    
    // Execute each formula
    foreach ($formulas as $formula) {
        try {
            // ✅ FIX: Strip = prefix before execution
            $formulaToExecute = \App\Helpers\FormulaHelper::stripFormulaPrefix($formula['formula']);
            
            // ✅ FIX: Handle dot notation in pre-processing formulas
            preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formulaToExecute, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $fullMatch = $match[0]; // e.g., "thickness.average"
                $varName = $match[1] . '_' . $match[2]; // e.g., "thickness_average"
                $formulaToExecute = str_replace($fullMatch, $varName, $formulaToExecute);
            }
            
            $result = $executor->execute($formulaToExecute);
            $results[] = $result;
            
            // Set result untuk formula berikutnya
            $executor->setVar($formula['name'], $result);
        } catch (\Exception $e) {
            // If formula fails (missing variable), return null instead of 0
            $results[] = null;
        }
    }
    
    return $results;
}
```

### 5. Perbaikan: `evaluateJointItem()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**:
- Menyertakan FIXED variables sebelum eksekusi formula
- Menyertakan semua variable_values dalam executor
- Memastikan nilai selalu disertakan dalam response (meskipun null)

```php
private function evaluateJointItem(array $result, array $measurementPoint, array $processedSamples, array $measurementContext = [], array $variableValues = []): array
{
    $jointSetting = $measurementPoint['evaluation_setting']['joint_setting'];
    $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
    
    // Process joint formulas
    $jointResults = [];
    $executor = new \NXP\MathExecutor();
    
    // Register custom functions
    $this->registerCustomFunctionsForItem($executor);
    
    // ✅ FIX: Set variable values first (includes FIXED, MANUAL, FORMULA)
    foreach ($variableValues as $variable) {
        if (isset($variable['name_id']) && isset($variable['value']) && is_numeric($variable['value'])) {
            $executor->setVar($variable['name_id'], $variable['value']);
        }
    }
    
    // ... (rest of the function)
    
    // Execute each joint formula
    foreach ($jointSetting['formulas'] as $formula) {
        try {
            $formulaToExecute = \App\Helpers\FormulaHelper::stripFormulaPrefix($formula['formula']);
            
            // ✅ FIX: Transform dot notation to variable names (thickness.average → thickness_average)
            preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formulaToExecute, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $fullMatch = $match[0]; // e.g., "thickness.average"
                $varName = $match[1] . '_' . $match[2]; // e.g., "thickness_average"
                $formulaToExecute = str_replace($fullMatch, $varName, $formulaToExecute);
            }
            
            $calculatedValue = $executor->execute($formulaToExecute);
            
            // ✅ FIX: Ensure value is always included, even if null
            $jointResults[] = [
                'name' => $formula['name'],
                'formula' => $formula['formula'],
                'is_final_value' => $formula['is_final_value'] ?? false,
                'value' => $calculatedValue !== null ? (is_numeric($calculatedValue) ? (float)$calculatedValue : $calculatedValue) : null
            ];
            
            // Set result untuk formula berikutnya
            $executor->setVar($formula['name'], $calculatedValue);
        } catch (\Exception $e) {
            // ✅ FIX: If formula fails, return null value with error message
            $jointResults[] = [
                'name' => $formula['name'],
                'formula' => $formula['formula'],
                'is_final_value' => $formula['is_final_value'] ?? false,
                'value' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ... (rest of the function)
}
```

### 6. Perbaikan: `evaluateSampleItem()`

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**:
- Menggunakan `buildCompleteVariableValues()` yang sudah menyertakan semua tipe variables
- Menambahkan `measurement_item_name_id` ke response

```php
private function evaluateSampleItem(array $processedSamples, array $measurementPoint, array $measurementItem, array $measurementContext = []): array
{
    $evaluationType = $measurementPoint['evaluation_type'];
    $result = [
        'status' => false,
        'variable_values' => $measurementItem['variable_values'] ?? [], // ✅ Already includes all types from buildCompleteVariableValues
        'samples' => $processedSamples,
        'joint_setting_formula_values' => null,
    ];

    switch ($evaluationType) {
        case 'PER_SAMPLE':
            $result = $this->evaluatePerSampleItem($result, $measurementPoint, $processedSamples);
            break;
            
        case 'JOINT':
            $result = $this->evaluateJointItem($result, $measurementPoint, $processedSamples, $measurementContext, $measurementItem['variable_values'] ?? []);
            break;
            
        case 'SKIP_CHECK':
            $result['status'] = true;
            break;
    }

    return $result;
}
```

### 7. Perbaikan: `checkSamples()` - Response Structure

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**: Menambahkan `measurement_item_name_id` ke response

```php
// Evaluate berdasarkan evaluation type dengan measurement context
$result = $this->evaluateSampleItem($processedSamples, $measurementPoint, $measurementItemData, $measurementContext);

// ✅ FIX: Add measurement_item_name_id to result
$result['measurement_item_name_id'] = $request->measurement_item_name_id;

// ✅ Save hasil check sebagai "jejak" untuk comparison nanti
$this->saveLastCheckData($measurement, $request->measurement_item_name_id, $result);

return $this->successResponse($result, 'Samples processed successfully');
```

### 8. Perbaikan: `saveProgress()` - Error Handling

**Lokasi**: `app/Http/Controllers/Api/V1/ProductMeasurementController.php`

**Perbaikan**: Menambahkan error handling untuk request body kosong/invalid

```php
public function saveProgress(Request $request, string $productMeasurementId)
{
    try {
        // ✅ FIX: Check if request body is empty or invalid JSON
        if (empty($request->all())) {
            return $this->errorResponse(
                'Request body kosong atau tidak valid. Pastikan Content-Type: application/json dan payload JSON valid.',
                'INVALID_REQUEST_BODY',
                400
            );
        }
        
        // ... (rest of the function)
    }
}
```

## 📝 File yang Diubah

1. **app/Http/Controllers/Api/V1/ProductMeasurementController.php**
   - Fungsi baru: `buildCompleteVariableValues()`
   - Perbaikan: `buildMeasurementContext()`
   - Perbaikan: `evaluateFormulaWithContext()`
   - Perbaikan: `processPreProcessingFormulasForItem()`
   - Perbaikan: `evaluateJointItem()`
   - Perbaikan: `evaluateSampleItem()`
   - Perbaikan: `checkSamples()`
   - Perbaikan: `saveProgress()`

## ✅ Hasil Setelah Perbaikan

### Response `/samples/check` untuk `room_temp`:

```json
{
    "http_code": 200,
    "message": "Samples processed successfully",
    "data": {
        "measurement_item_name_id": "room_temp",
        "status": false,
        "variable_values": [
            {
                "name_id": "panjang",
                "value": 150
            },
            {
                "name_id": "cross_section",
                "value": 5.033333333333499  // ✅ Terhitung dari thickness.average*5
            },
            {
                "name_id": "temp_const",
                "value": 9.80665  // ✅ FIXED variable muncul
            }
        ],
        "samples": [
            {
                "sample_index": 1,
                "single_value": 2.3,
                "pre_processing_formula_values": [
                    {
                        "name": "normalized",
                        "formula": "=single_value/cross_section",
                        "value": 0.45695364238409086  // ✅ Value muncul
                    }
                ]
            }
        ],
        "joint_setting_formula_values": [
            {
                "name": "average",
                "formula": "=avg(normalized)",
                "is_final_value": false,
                "value": 0.4675496688741568  // ✅ Value muncul
            },
            {
                "name": "fix",
                "formula": "=average*temp_const",
                "is_final_value": true,
                "value": 4.585095960264749  // ✅ Value muncul
            }
        ]
    }
}
```

## 🎯 Fitur yang Didukung

### 1. Variable Types
- ✅ **FIXED**: Variable dengan nilai tetap (otomatis di-include)
- ✅ **MANUAL**: Variable yang diinput user (di-include jika disediakan)
- ✅ **FORMULA**: Variable dengan formula (dihitung otomatis)

### 2. Formula Notation
- ✅ **Function Notation**: `avg(thickness_a)`, `sum(values)`
- ✅ **Dot Notation**: `thickness.average`, `room_temp.fix`
- ✅ **Mixed**: `=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3`

### 3. Formula Processing
- ✅ **Pre-processing Formulas**: Diproses per sample dengan akses ke variables
- ✅ **Joint Formulas**: Diproses untuk agregasi dengan akses ke semua variables
- ✅ **Cross-reference**: Formula bisa reference ke measurement item lain

## 🔍 Testing

### Test Case 1: Variable `cross_section` dengan dot notation
**Input**:
```json
{
  "measurement_item_name_id": "room_temp",
  "variable_values": [{"name_id": "panjang", "value": 150}],
  "samples": [...]
}
```

**Expected**: `cross_section` terhitung dari `thickness.average*5`

**Result**: ✅ **PASS** - `cross_section` muncul dengan nilai yang benar

### Test Case 2: Pre-processing formulas
**Input**: Samples dengan `single_value`

**Expected**: `pre_processing_formula_values` memiliki `value` yang terhitung

**Result**: ✅ **PASS** - Semua pre-processing formulas memiliki nilai

### Test Case 3: Joint formulas
**Input**: Samples dengan pre-processing formulas

**Expected**: `joint_setting_formula_values` memiliki `value` yang terhitung

**Result**: ✅ **PASS** - Semua joint formulas memiliki nilai

### Test Case 4: FIXED variables
**Input**: Measurement point dengan FIXED variables

**Expected**: FIXED variables muncul di `variable_values`

**Result**: ✅ **PASS** - FIXED variables muncul di response

## 📌 Catatan Penting

1. **Formula Format**: Formula harus dalam format string dengan prefix `=` (seperti Excel)
   - ✅ Benar: `"formula": "=avg(single_value)"`
   - ❌ Salah: `"formula": =avg(single_value)`

2. **Dot Notation**: Format `measurement_item.formula_name` (contoh: `thickness.average`)
   - Di-convert menjadi `measurement_item_formula_name` untuk eksekusi
   - Contoh: `thickness.average` → `thickness_average`

3. **Function Notation**: Format `function(measurement_item)` (contoh: `avg(thickness_a)`)
   - Measurement item di-set sebagai array untuk aggregation functions
   - Contoh: `avg(thickness_a)` → aggregate dari samples `thickness_a`

4. **Variable Dependencies**: Formula variables bisa reference ke:
   - Measurement items lain (via dot notation atau function notation)
   - Variables lain (FIXED, MANUAL, atau FORMULA sebelumnya)
   - Pre-processing formulas (untuk joint formulas)

## 🚀 Deployment Notes

- ✅ Tidak ada breaking changes
- ✅ Backward compatible dengan data existing
- ✅ Tidak ada perubahan database schema
- ✅ Tidak ada perubahan API contract (hanya menambahkan fields di response)

## 📚 Referensi

- Formula Helper: `app/Helpers/FormulaHelper.php`
- MathExecutor Library: `NXP\MathExecutor`
- Product Measurement Model: `app/Models/ProductMeasurement.php`
- Product Model: `app/Models/Product.php`

---

**Tanggal Update**: December 2025  
**Versi**: 1.0  
**Status**: ✅ Completed & Tested

