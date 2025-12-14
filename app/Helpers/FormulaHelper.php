<?php

namespace App\Helpers;

class FormulaHelper
{
    /**
     * Auto-generate name_id from name
     * Example: "Room Temp" -> "room_temp"
     *          "Thickness A" -> "thickness_a"
     *          "Temperature" -> "temperature"
     */
    public static function generateNameId(string $name): string
    {
        // Convert to lowercase
        $nameId = strtolower($name);
        
        // Replace spaces with underscores
        $nameId = str_replace(' ', '_', $nameId);
        
        // Remove all characters except letters, numbers, and underscores
        $nameId = preg_replace('/[^a-z0-9_]/', '', $nameId);
        
        // Remove consecutive underscores
        $nameId = preg_replace('/_+/', '_', $nameId);
        
        // Remove leading/trailing underscores
        $nameId = trim($nameId, '_');
        
        return $nameId;
    }

    /**
     * Validate formula starts with =
     * @throws \InvalidArgumentException if formula doesn't start with =
     */
    public static function validateFormulaFormat(string $formula): void
    {
        $formula = trim($formula);
        
        if (!str_starts_with($formula, '=')) {
            throw new \InvalidArgumentException("Formula harus dimulai dengan '=' seperti di Excel. Contoh: =avg(thickness_a) + avg(thickness_b)");
        }
    }

    /**
     * Strip leading = from formula
     * Example: "=avg(thickness_a)" -> "avg(thickness_a)"
     */
    public static function stripFormulaPrefix(string $formula): string
    {
        $formula = trim($formula);
        
        if (str_starts_with($formula, '=')) {
            return substr($formula, 1);
        }
        
        return $formula;
    }

    /**
     * Normalize function names to lowercase
     * Example: "AVG(thickness_a)" -> "avg(thickness_a)"
     *          "SIN(angle)" -> "sin(angle)"
     * 
     * Supports ALL functions available in MathExecutor library
     */
    public static function normalizeFunctionNames(string $formula): string
    {
        // Complete list of math functions supported by MathExecutor
        // Including all standard math functions and custom functions
        $functions = [
            // Aggregation functions
            'AVG', 'SUM', 'MIN', 'MAX', 'COUNT',
            
            // Trigonometric functions
            'SIN', 'COS', 'TAN', 'COT', 'SEC', 'CSC',
            'ASIN', 'ACOS', 'ATAN', 'ATAN2', 'ACOT', 'ASEC', 'ACSC',
            
            // Hyperbolic functions
            'SINH', 'COSH', 'TANH', 'COTH', 'SECH', 'CSCH',
            'ASINH', 'ACOSH', 'ATANH', 'ACOTH', 'ASECH', 'ACSCH',
            
            // Rounding functions
            'CEIL', 'FLOOR', 'ROUND', 'TRUNC',
            
            // Math functions
            'SQRT', 'ABS', 'SIGN', 'FMOD', 'HYPOT',
            
            // Logarithmic and exponential
            'LOG', 'LOG10', 'LOG2', 'LN', 'EXP', 'POW', 'POWER',
            
            // Statistical functions
            'AVERAGE', 'MEDIAN', 'MODE', 'STDEV', 'VARIANCE',
            
            // Other functions
            'IF', 'IFS', 'SWITCH', 'RAND', 'RANDOM',
            
            // Constants (treated as functions without params)
            'PI', 'E', 'PHI', 'EULER',
            
            // Degree/Radian conversion
            'DEG2RAD', 'RAD2DEG', 'DEGREES', 'RADIANS'
        ];
        
        foreach ($functions as $func) {
            // Replace function name (case insensitive) with lowercase version
            // Use word boundary to avoid replacing partial words
            $formula = preg_replace(
                '/\b' . preg_quote($func, '/') . '\b/i',
                strtolower($func),
                $formula
            );
        }
        
        return $formula;
    }

    /**
     * Extract measurement item references from formula
     * Example: "avg(thickness_a) + avg(thickness_b)" -> ["thickness_a", "thickness_b"]
     * Example: "thickness_a.avg + thickness_b.avg" -> ["thickness_a", "thickness_b"]
     * Note: Dot notation parts (like "final", "avg" after dot) are NOT included as separate references
     */
    public static function extractMeasurementReferences(string $formula): array
    {
        $references = [];
        $excludedFromStandalone = []; // Track identifiers that are part of dot notation
        
        // Strip formula prefix if exists
        $formula = self::stripFormulaPrefix($formula);
        
        // Step 1: Extract from function calls like avg(thickness_a)
        if (preg_match_all('/\b(avg|sum|min|max)\s*\(\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\)/i', $formula, $matches)) {
            $references = array_merge($references, $matches[2]);
        }
        
        // Step 2: Extract from dot notation FIRST and mark formula names to exclude
        // Example: thickness.final -> extract "thickness", exclude "final" from standalone extraction
        $dotNotationMatches = [];
        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $measurementItemId = $match[1]; // e.g., "thickness"
                $formulaName = $match[2]; // e.g., "final", "avg"
                $fullDotNotation = $match[0]; // e.g., "thickness.final"
                
                // Add measurement item name_id to references
                $references[] = $measurementItemId;
                
                // Mark formula name to exclude from standalone extraction
                $excludedFromStandalone[$formulaName] = true;
                
                // Store for replacement (we'll replace all at once to avoid position shifting issues)
                $dotNotationMatches[] = $fullDotNotation;
            }
            
            // Replace all dot notations with placeholder to prevent their parts from being extracted
            // Use preg_replace to handle special characters properly
            foreach ($dotNotationMatches as $dotNotation) {
                $formula = preg_replace('/' . preg_quote($dotNotation, '/') . '/', ' ', $formula, 1);
            }
        }
        
        // Step 3: Extract standalone variable references (NOT in functions, NOT part of dot notation)
        // After dot notation removal, extract remaining identifiers
        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches)) {
            foreach ($matches[1] as $identifier) {
                // CRITICAL: Skip if this identifier was part of dot notation (e.g., "final" from "thickness.final")
                // This check MUST happen first before adding to references
                if (isset($excludedFromStandalone[$identifier])) {
                    continue;
                }
                
                // Skip math function names and operators
                $mathFunctionsLower = [
                    // Aggregation
                    'avg', 'sum', 'min', 'max', 'count',
                    // Trigonometric
                    'sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'asin', 'acos', 'atan', 'atan2', 'acot', 'asec', 'acsc',
                    // Hyperbolic
                    'sinh', 'cosh', 'tanh', 'coth', 'sech', 'csch', 'asinh', 'acosh', 'atanh', 'acoth', 'asech', 'acsch',
                    // Rounding
                    'ceil', 'floor', 'round', 'trunc',
                    // Math
                    'sqrt', 'abs', 'sign', 'fmod', 'hypot',
                    // Logarithmic
                    'log', 'log10', 'log2', 'ln', 'exp', 'pow', 'power',
                    // Statistical
                    'average', 'median', 'mode', 'stdev', 'variance',
                    // Other
                    'if', 'ifs', 'switch', 'rand', 'random',
                    // Constants
                    'pi', 'e', 'phi', 'euler',
                    // Conversion
                    'deg2rad', 'rad2deg', 'degrees', 'radians',
                    // Operators
                    'and', 'or', 'not', 'xor'
                ];
                
                if (!in_array(strtolower($identifier), $mathFunctionsLower)) {
                    $references[] = $identifier;
                }
            }
        }
        
        // Remove duplicates and return
        return array_unique($references);
    }
    
    /**
     * Transform dot notation to get aggregation result from measurement context
     * Example: "thickness_a.avg" → extract from measurement_context['thickness_a']['joint_results']['avg']
     * 
     * @param string $formula Formula dengan dot notation
     * @param array $measurementContext Context dari measurement items yang sudah diproses
     * @return string Formula yang sudah di-transform
     */
    public static function transformDotNotationFormula(string $formula, array $measurementContext): string
    {
        // Find all dot notation patterns: measurement_item.formula_name
        // Example: thickness_a.avg, room_temp.fix
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
                // If value not found, replace with variable name for MathExecutor
                // This will be set dynamically during execution
                $variableName = $measurementItemId . '_' . $formulaName;
                $formula = str_replace($fullMatch, $variableName, $formula);
            }
        }
        
        return $formula;
    }

    /**
     * Validate formula dependencies
     * Check if all referenced measurement items exist in the available list
     * 
     * @param string $formula The formula to validate
     * @param array $availableMeasurementIds List of available measurement item name_ids
     * @return array List of missing measurement item name_ids
     */
    public static function validateFormulaDependencies(string $formula, array $availableMeasurementIds): array
    {
        $referencedItems = self::extractMeasurementReferences($formula);
        $missing = [];
        
        foreach ($referencedItems as $itemId) {
            if (!in_array($itemId, $availableMeasurementIds)) {
                $missing[] = $itemId;
            }
        }
        
        return $missing;
    }

    /**
     * Process formula: validate, strip prefix ONLY FOR EXECUTION, normalize
     * IMPORTANT: This is for INTERNAL processing, not for storage/response
     * 
     * @param string $formula Original formula
     * @param bool $stripPrefix Whether to strip = prefix (default: true for execution)
     * @return string Processed formula ready for execution
     * @throws \InvalidArgumentException if validation fails
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

    /**
     * Get all measurement item name_ids from measurement points
     * 
     * @param array $measurementPoints
     * @return array List of name_ids
     */
    public static function getAllMeasurementIds(array $measurementPoints): array
    {
        $nameIds = [];
        
        foreach ($measurementPoints as $point) {
            if (isset($point['setup']['name_id'])) {
                $nameIds[] = $point['setup']['name_id'];
            }
        }
        
        return $nameIds;
    }

    /**
     * Validate formula in context (check dependencies)
     * 
     * @param string $formula
     * @param array $measurementPoints All measurement points
     * @param int $currentPointIndex Current measurement point index (to get available dependencies)
     * @return array Validation errors
     */
    public static function validateFormulaInContext(string $formula, array $measurementPoints, int $currentPointIndex): array
    {
        $errors = [];
        
        try {
            // Validate format
            self::validateFormulaFormat($formula);
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
            return $errors;
        }
        
        // Get available measurement items (only those defined BEFORE current point)
        $availableIds = [];
        for ($i = 0; $i < $currentPointIndex; $i++) {
            if (isset($measurementPoints[$i]['setup']['name_id'])) {
                $availableIds[] = $measurementPoints[$i]['setup']['name_id'];
            }
        }
        
        // Check dependencies
        $missingDependencies = self::validateFormulaDependencies($formula, $availableIds);
        
        if (!empty($missingDependencies)) {
            $errors[] = "Formula references measurement items yang belum dibuat: " . implode(', ', $missingDependencies) . 
                       ". Pastikan measurement item tersebut sudah dibuat sebelum menggunakan formula ini.";
        }
        
        return $errors;
    }
}
