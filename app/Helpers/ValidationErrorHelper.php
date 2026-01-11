<?php

namespace App\Helpers;

use App\Enums\BasicInfoErrorEnum;
use App\Enums\MeasurementPointErrorEnum;
use App\Enums\MeasurementPointSectionEnum;

class ValidationErrorHelper
{
    /**
     * Create basic info error response
     */
    public static function createBasicInfoError(
        BasicInfoErrorEnum $code,
        string $fieldName,
        string $message
    ): array {
        return [
            'field' => $fieldName,
            'code' => $code->value,
            'message' => $message
        ];
    }

    /**
     * Create measurement point error response
     */
    public static function createMeasurementPointError(
        array $measurementItem,
        MeasurementPointSectionEnum $section,
        ?string $entityName,
        MeasurementPointErrorEnum $code,
        string $message
    ): array {
        return [
            'measurement_item' => [
                'id' => $measurementItem['name_id'] ?? null,
                'name' => $measurementItem['name'] ?? null,
            ],
            'context' => [
                'section' => $section->value,
                'entity_name' => $entityName,
            ],
            'code' => $code->value,
            'message' => $message,
        ];
    }

    /**
     * Format complete error response
     */
    public static function formatErrorResponse(
        string $errorId,
        array $basicInfoErrors = [],
        array $measurementPointErrors = []
    ): array {
        return [
            'error_id' => $errorId,
            'data' => [
                'basic_info' => $basicInfoErrors,
                'measurement_points' => $measurementPointErrors,
            ]
        ];
    }

    /**
     * Generate user-friendly error message for invalid name format
     */
    public static function generateInvalidNameMessage(string $name, string $reason): string
    {
        $messages = [
            'special_character' => "nama \"{$name}\" tidak valid karena mengandung karakter spesial. Hanya boleh huruf kecil, angka, dan underscore (_)",
            'uppercase' => "nama \"{$name}\" tidak valid karena mengandung huruf besar. Gunakan huruf kecil saja",
            'space' => "nama \"{$name}\" tidak valid karena mengandung spasi. Gunakan underscore (_) sebagai pengganti spasi",
            'start_with_number' => "nama \"{$name}\" tidak valid karena dimulai dengan angka. Harus dimulai dengan huruf kecil",
            'empty' => "nama tidak boleh kosong",
        ];

        return $messages[$reason] ?? "nama \"{$name}\" tidak valid";
    }

    /**
     * Generate user-friendly error message for invalid formula
     */
    public static function generateInvalidFormulaMessage(
        string $formulaName,
        string $formula,
        string $missingDependency
    ): string {
        return "Formula \"{$formulaName}\" tidak valid karena \"{$missingDependency}\" tidak ditemukan. " .
               "Pastikan \"{$missingDependency}\" sudah didefinisikan sebelumnya sebagai variable atau measurement item.";
    }

    /**
     * Generate user-friendly error message for variable error
     */
    public static function generateVariableErrorMessage(
        string $variableName,
        string $errorType,
        ?string $additionalInfo = null
    ): string {
        $messages = [
            'contains_space' => "Variable \"{$variableName}\" tidak valid karena mengandung spasi. Gunakan underscore (_) sebagai pengganti",
            'uppercase' => "Variable \"{$variableName}\" tidak valid karena mengandung huruf besar. Gunakan huruf kecil saja",
            'special_character' => "Variable \"{$variableName}\" tidak valid karena mengandung karakter spesial",
            'required' => "Variable \"{$variableName}\" wajib diisi",
            'invalid_value' => "Variable \"{$variableName}\" memiliki nilai yang tidak valid",
        ];

        $message = $messages[$errorType] ?? "Variable \"{$variableName}\" tidak valid";
        
        if ($additionalInfo) {
            $message .= ". {$additionalInfo}";
        }

        return $message;
    }

    /**
     * Validate name format (lowercase, alphanumeric, underscore, must start with letter)
     */
    public static function validateNameFormat(string $name): ?string
    {
        if (empty(trim($name))) {
            return 'empty';
        }

        if (preg_match('/\s/', $name)) {
            return 'space';
        }

        if (preg_match('/[A-Z]/', $name)) {
            return 'uppercase';
        }

        if (!preg_match('/^[a-z]/', $name)) {
            return 'start_with_number';
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            return 'special_character';
        }

        return null; // Valid
    }

    /**
     * Extract missing dependencies from formula error message
     */
    public static function extractMissingDependencies(string $formula): array
    {
        // Extract all identifiers from formula (excluding function names)
        preg_match_all('/\b([a-z_][a-z0-9_]*)\b/i', $formula, $matches);
        
        // Filter out common function names
        $functionNames = ['avg', 'sum', 'min', 'max', 'sin', 'cos', 'tan', 'sqrt', 'abs', 'round', 'floor', 'ceil', 'exp', 'log', 'pow'];
        $identifiers = array_diff($matches[1], $functionNames);
        
        return array_values(array_unique($identifiers));
    }
}
