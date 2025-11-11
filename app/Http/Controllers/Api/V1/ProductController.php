<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Traits\ApiResponseTrait;
use App\Helpers\FormulaHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        try {
            // Enhanced validation based on new requirements
            $validator = Validator::make($request->all(), [
                // Basic Info - product_category_id and product_name are required
                'basic_info.product_category_id' => 'required|integer|exists:product_categories,id',
                'basic_info.product_name' => 'required|string',
                'basic_info.ref_spec_number' => 'nullable|string',
                'basic_info.nom_size_vo' => 'nullable|string',
                'basic_info.article_code' => 'nullable|string',
                'basic_info.no_document' => 'nullable|string',
                'basic_info.no_doc_reference' => 'nullable|string',
                
                // Measurement Points
                'measurement_points' => 'required|array|min:1',
                'measurement_points.*.setup.name' => 'required|string',
                'measurement_points.*.setup.name_id' => 'required|string|regex:/^[a-zA-Z_]+$/',
                'measurement_points.*.setup.sample_amount' => 'required|integer|min:1',
                'measurement_points.*.setup.source' => 'required|in:INSTRUMENT,MANUAL,DERIVED,TOOL',
                'measurement_points.*.setup.source_instrument_id' => 'required_if:measurement_points.*.setup.source,INSTRUMENT|nullable|integer|exists:measurement_instruments,id',
                'measurement_points.*.setup.source_derived_name_id' => 'required_if:measurement_points.*.setup.source,DERIVED|nullable|string',
                'measurement_points.*.setup.source_tool_model' => 'required_if:measurement_points.*.setup.source,TOOL|nullable|string',
                'measurement_points.*.setup.type' => 'required|in:SINGLE,BEFORE_AFTER',
                'measurement_points.*.setup.nature' => 'required|in:QUALITATIVE,QUANTITATIVE',
                
                // Variables
                'measurement_points.*.variables' => 'nullable|array',
                'measurement_points.*.variables.*.type' => 'required_with:measurement_points.*.variables|in:FIXED,MANUAL,FORMULA',
                'measurement_points.*.variables.*.name' => 'required_with:measurement_points.*.variables|string|regex:/^[a-zA-Z_]+$/',
                'measurement_points.*.variables.*.value' => 'required_if:measurement_points.*.variables.*.type,FIXED|nullable|numeric',
                'measurement_points.*.variables.*.formula' => 'required_if:measurement_points.*.variables.*.type,FORMULA|nullable|string',
                'measurement_points.*.variables.*.is_show' => 'required_with:measurement_points.*.variables|boolean',
                
                // Pre-processing formulas
                'measurement_points.*.pre_processing_formulas' => 'nullable|array',
                'measurement_points.*.pre_processing_formulas.*.name' => 'required_with:measurement_points.*.pre_processing_formulas|string|regex:/^[a-zA-Z_]+$/',
                'measurement_points.*.pre_processing_formulas.*.formula' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.is_show' => 'required_with:measurement_points.*.pre_processing_formulas|boolean',
                
                // Evaluation
                'measurement_points.*.evaluation_type' => 'required|in:PER_SAMPLE,JOINT,SKIP_CHECK',
                'measurement_points.*.evaluation_setting' => 'required|array',
                
                // Rule evaluation
                'measurement_points.*.rule_evaluation_setting' => 'nullable|array',
                'measurement_points.*.rule_evaluation_setting.rule' => 'required_with:measurement_points.*.rule_evaluation_setting|in:MIN,MAX,BETWEEN',
                'measurement_points.*.rule_evaluation_setting.unit' => 'required_with:measurement_points.*.rule_evaluation_setting|string',
                'measurement_points.*.rule_evaluation_setting.value' => 'required_with:measurement_points.*.rule_evaluation_setting|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_minus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_plus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                
                // Measurement Groups
                'measurement_groups' => 'nullable|array',
                'measurement_groups.*.group_name' => 'required_with:measurement_groups|string',
                'measurement_groups.*.measurement_items' => 'required_with:measurement_groups|array',
                'measurement_groups.*.order' => 'required_with:measurement_groups|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $basicInfo = $request->input('basic_info');
            $measurementPoints = $request->input('measurement_points');
            $measurementGroups = $request->input('measurement_groups', []);

            // Validate product_category_id exists and get category details
            $category = ProductCategory::find($basicInfo['product_category_id']);
            if (!$category) {
                return $this->errorResponse('Product category tidak ditemukan', 'CATEGORY_NOT_FOUND', 400);
            }

            // Validate product_name is valid for the category
            if (!in_array($basicInfo['product_name'], $category->products)) {
                return $this->errorResponse(
                    'Product name "' . $basicInfo['product_name'] . '" tidak valid untuk category "' . $category->name . '"',
                    'INVALID_PRODUCT_NAME',
                    400
                );
            }

            // Auto-generate name_id if not provided
            $measurementPoints = $this->autoGenerateNameIds($measurementPoints);

            // Validate and process formulas
            $formulaValidationErrors = $this->validateAndProcessFormulas($measurementPoints);
            if (!empty($formulaValidationErrors)) {
                return $this->errorResponse('Formula validation failed', 'FORMULA_VALIDATION_ERROR', 400, $formulaValidationErrors);
            }

            // Process measurement groups if provided
            $processedMeasurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups);

            // Additional validation for measurement points
            $validationErrors = $this->validateMeasurementPoints($measurementPoints);
            if (!empty($validationErrors)) {
                return $this->errorResponse('Measurement points validation failed', 'MEASUREMENT_VALIDATION_ERROR', 400, $validationErrors);
            }

            // Validate name uniqueness across variables, pre_processing_formulas, and joint_setting formulas
            $nameValidationErrors = $this->validateNameUniqueness($measurementPoints);
            if (!empty($nameValidationErrors)) {
                return $this->errorResponse('Name validation failed', 'NAME_UNIQUENESS_ERROR', 400, $nameValidationErrors);
            }

            // Get active quarter
            $activeQuarter = Quarter::getActiveQuarter();
            if (!$activeQuarter) {
                return $this->errorResponse('Tidak ada quarter aktif', 'NO_ACTIVE_QUARTER', 400);
            }

            // Create product
            $product = Product::create([
                'quarter_id' => $activeQuarter->id,
                'product_category_id' => $basicInfo['product_category_id'],
                'product_name' => $basicInfo['product_name'],
                'ref_spec_number' => $basicInfo['ref_spec_number'] ?? null,
                'nom_size_vo' => $basicInfo['nom_size_vo'] ?? null,
                'article_code' => $basicInfo['article_code'] ?? null,
                'no_document' => $basicInfo['no_document'] ?? null,
                'no_doc_reference' => $basicInfo['no_doc_reference'] ?? null,
                'measurement_points' => $processedMeasurementPoints,
                'measurement_groups' => $measurementGroups,
            ]);

            $product->load(['quarter', 'productCategory']);

            return $this->successResponse([
                'product_id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                ],
                'measurement_points' => $product->measurement_points,
                'measurement_groups' => $product->measurement_groups,
                'product_category' => [
                    'id' => $category->id,
                    'name' => $category->name
                ]
            ], 'Product berhasil dibuat', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'CREATION_ERROR', 500);
        }
    }

    /**
     * Get product by ID
     */
    public function show(string $productId)
    {
        try {
            $product = Product::with(['quarter', 'productCategory'])
                ->where('product_id', $productId)
                ->first();

            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            return $this->successResponse([
                'id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                ],
                'measurement_points' => $product->measurement_points,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Get measurement items suggestions for autocomplete
     * Used when user types formula to suggest available measurement items
     * 
     * GET /api/v1/products/{productId}/measurement-items/suggest?query=temp
     */
    public function suggestMeasurementItems(string $productId, Request $request)
    {
        try {
            $query = $request->input('query', '');
            
            // Get product
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }
            
            $measurementPoints = $product->measurement_points ?? [];
            $suggestions = [];
            
            // Filter measurement items based on query
            foreach ($measurementPoints as $point) {
                if (!isset($point['setup']['name']) || !isset($point['setup']['name_id'])) {
                    continue;
                }
                
                $name = $point['setup']['name'];
                $nameId = $point['setup']['name_id'];
                
                // If query is empty, return all
                if (empty($query)) {
                    $suggestions[] = [
                        'name' => $name,
                        'name_id' => $nameId,
                        'type' => $point['setup']['nature'] ?? 'QUANTITATIVE'
                    ];
                    continue;
                }
                
                // Case-insensitive search in name or name_id
                if (stripos($name, $query) !== false || stripos($nameId, $query) !== false) {
                    $suggestions[] = [
                        'name' => $name,
                        'name_id' => $nameId,
                        'type' => $point['setup']['nature'] ?? 'QUANTITATIVE',
                        'source' => $point['setup']['source'] ?? null
                    ];
                }
            }
            
            return $this->successResponse([
                'query' => $query,
                'suggestions' => $suggestions,
                'total' => count($suggestions)
            ], 'Suggestions retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'SUGGESTION_ERROR', 500);
        }
    }

    /**
     * Get products list
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $products = Product::with(['quarter','productCategory'])
            ->paginate($limit, ['*'], 'page', $page);
        
        $transformedProducts = collect($products->items())
            ->map(function ($product) {
                return [
                    'id' => $product->product_id,
                    'product_category_id' => $product->product_category_id,
                    'product_category_name' => $product->productCategory->name,
                    'product_name' => $product->product_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                ];
            })->values()->all();

            return $this->paginationResponse(
                $transformedProducts,
                [
                    'current_page' => $products->currentPage(),
                    'total_page' => $products->lastPage(),
                    'limit' => $products->perPage(),
                    'total_docs' => $products->total(),
                ]
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Check if product exists
     */
    public function checkProductExists(Request $request)
    {
        try {
            // Validate required parameters
            $validator = Validator::make($request->all(), [
                'product_category_id' => 'required|integer|exists:product_categories,id',
                'product_name' => 'required|string|max:255',
                'ref_spec_number' => 'nullable|string|max:255',
                'nom_size_vo' => 'nullable|string|max:255',
                'article_code' => 'nullable|string|max:255',
                'no_document' => 'nullable|string|max:255',
                'no_doc_reference' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $exists = Product::checkProductExists($request->all());
            return $this->successResponse(['is_product_exists' => $exists]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'CHECK_ERROR', 500);
        }
    }

    /**
     * Get product categories
     */
    public function getProductCategories()
    {
        try {
            $categories = ProductCategory::select('id', 'name', 'products', 'description')->get();
            return $this->successResponse($categories);
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Validate measurement points structure
     */
    private function validateMeasurementPoints(array $measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $index => $point) {
            $pointErrors = [];
            
            // Validate setup
            if (!isset($point['setup'])) {
                $pointErrors[] = 'Setup is required';
                $errors["measurement_points.{$index}"] = $pointErrors;
                continue;
            }

            $setup = $point['setup'];
            
            // Required setup fields
            if (empty($setup['name'])) {
                $pointErrors[] = 'Setup name is required';
            }
            
            if (!isset($setup['sample_amount']) || $setup['sample_amount'] < 1) {
                $pointErrors[] = 'Sample amount must be at least 1';
            }

            // Nature-specific validation
            if (isset($setup['nature'])) {
                if ($setup['nature'] === 'QUANTITATIVE') {
                    // Quantitative must have rule_evaluation_setting
                    if (!isset($point['rule_evaluation_setting']) || empty($point['rule_evaluation_setting'])) {
                        $pointErrors[] = 'Rule evaluation setting is required for QUANTITATIVE nature';
                    } else {
                        $ruleErrors = $this->validateRuleEvaluation($point['rule_evaluation_setting']);
                        if (!empty($ruleErrors)) {
                            $pointErrors = array_merge($pointErrors, $ruleErrors);
                        }
                    }
                    
                    // Qualitative setting must be null for quantitative
                    if (isset($point['evaluation_setting']['qualitative_setting']) && $point['evaluation_setting']['qualitative_setting'] !== null) {
                        $pointErrors[] = 'Qualitative setting must be null for QUANTITATIVE nature';
                    }
                    
                } elseif ($setup['nature'] === 'QUALITATIVE') {
                    // Qualitative must have qualitative_setting
                    if (!isset($point['evaluation_setting']['qualitative_setting']) || empty($point['evaluation_setting']['qualitative_setting'])) {
                        $pointErrors[] = 'Qualitative setting is required for QUALITATIVE nature';
                    } else {
                        if (empty($point['evaluation_setting']['qualitative_setting']['label'])) {
                            $pointErrors[] = 'Qualitative label is required';
                        }
                    }
                    
                    // Rule evaluation must be null for qualitative
                    if (isset($point['rule_evaluation_setting']) && $point['rule_evaluation_setting'] !== null) {
                        $pointErrors[] = 'Rule evaluation setting must be null for QUALITATIVE nature';
                    }
                    
                    // For qualitative, evaluation_type should be SKIP_CHECK
                    if (isset($point['evaluation_type']) && $point['evaluation_type'] !== 'SKIP_CHECK') {
                        $pointErrors[] = 'Evaluation type must be SKIP_CHECK for QUALITATIVE nature';
                    }
                }
            }

            if (!empty($pointErrors)) {
                $errors["measurement_points.{$index}"] = $pointErrors;
            }
        }

        return $errors;
    }

    /**
     * Process measurement grouping and ordering
     */
    private function processMeasurementGrouping(array $measurementPoints, array $measurementGroups): array
    {
        if (empty($measurementGroups)) {
            // No grouping specified, return original order
            return $measurementPoints;
        }

        // Create mapping of measurement items by name_id
        $measurementMap = [];
        foreach ($measurementPoints as $point) {
            $measurementMap[$point['setup']['name_id']] = $point;
        }

        // Process groups and create ordered measurement points
        $orderedMeasurementPoints = [];
        
        // Sort groups by order
        usort($measurementGroups, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        foreach ($measurementGroups as $group) {
            foreach ($group['measurement_items'] as $itemNameId) {
                if (isset($measurementMap[$itemNameId])) {
                    // Add group information to measurement point
                    $measurementPoint = $measurementMap[$itemNameId];
                    $measurementPoint['group_name'] = $group['group_name'];
                    $measurementPoint['group_order'] = $group['order'];
                    
                    $orderedMeasurementPoints[] = $measurementPoint;
                    unset($measurementMap[$itemNameId]); // Remove from map to avoid duplicates
                }
            }
        }

        // Add any remaining measurement points that weren't grouped
        foreach ($measurementMap as $point) {
            $point['group_name'] = 'Ungrouped';
            $point['group_order'] = 999; // Put ungrouped items at the end
            $orderedMeasurementPoints[] = $point;
        }

        return $orderedMeasurementPoints;
    }

    /**
     * Auto-generate name_id if not provided
     * Example: "Room Temp" -> "room_temp"
     */
    private function autoGenerateNameIds(array $measurementPoints): array
    {
        foreach ($measurementPoints as &$point) {
            // Generate name_id for setup if not provided
            if (!isset($point['setup']['name_id']) || empty($point['setup']['name_id'])) {
                $point['setup']['name_id'] = FormulaHelper::generateNameId($point['setup']['name']);
            }
            
            // Generate name_id for variables if not provided
            if (isset($point['variables']) && is_array($point['variables'])) {
                foreach ($point['variables'] as &$variable) {
                    if (!isset($variable['name']) || empty($variable['name'])) {
                        continue;
                    }
                    // Variables should already have proper name, just ensure it's valid
                }
            }
            
            // Generate name_id for pre-processing formulas if not provided
            if (isset($point['pre_processing_formulas']) && is_array($point['pre_processing_formulas'])) {
                foreach ($point['pre_processing_formulas'] as &$formula) {
                    if (!isset($formula['name']) || empty($formula['name'])) {
                        continue;
                    }
                    // Formula names should already be proper identifiers
                }
            }
        }
        
        return $measurementPoints;
    }

    /**
     * Validate and process all formulas in measurement points
     * - Validate formula format (must start with =)
     * - Validate formula dependencies (referenced measurement items must exist)
     * - Normalize function names (AVG -> avg, SIN -> sin, etc)
     */
    private function validateAndProcessFormulas(array &$measurementPoints): array
    {
        $errors = [];
        
        foreach ($measurementPoints as $pointIndex => &$point) {
            $pointPrefix = "measurement_point_{$pointIndex}";
            
            // Get available measurement IDs (only those defined BEFORE current point)
            $availableIds = [];
            for ($i = 0; $i < $pointIndex; $i++) {
                if (isset($measurementPoints[$i]['setup']['name_id'])) {
                    $availableIds[] = $measurementPoints[$i]['setup']['name_id'];
                }
            }
            
            // Validate variables formulas
            if (isset($point['variables']) && is_array($point['variables'])) {
                foreach ($point['variables'] as $varIndex => &$variable) {
                    if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
                        $formulaErrors = $this->validateSingleFormula(
                            $variable['formula'],
                            $availableIds,
                            "{$pointPrefix}.variable_{$varIndex}"
                        );
                        
                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Process formula: strip =, normalize functions
                            try {
                                $variable['formula'] = FormulaHelper::processFormula($variable['formula']);
                            } catch (\InvalidArgumentException $e) {
                                $errors["{$pointPrefix}.variable_{$varIndex}"] = $e->getMessage();
                            }
                        }
                    }
                }
            }
            
            // Validate pre-processing formulas
            if (isset($point['pre_processing_formulas']) && is_array($point['pre_processing_formulas'])) {
                foreach ($point['pre_processing_formulas'] as $formulaIndex => &$formula) {
                    if (isset($formula['formula'])) {
                        $formulaErrors = $this->validateSingleFormula(
                            $formula['formula'],
                            $availableIds,
                            "{$pointPrefix}.pre_processing_formula_{$formulaIndex}"
                        );
                        
                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Process formula
                            try {
                                $formula['formula'] = FormulaHelper::processFormula($formula['formula']);
                            } catch (\InvalidArgumentException $e) {
                                $errors["{$pointPrefix}.pre_processing_formula_{$formulaIndex}"] = $e->getMessage();
                            }
                        }
                    }
                }
            }
            
            // Validate joint setting formulas
            if (isset($point['evaluation_setting']['joint_setting']['formulas']) && is_array($point['evaluation_setting']['joint_setting']['formulas'])) {
                foreach ($point['evaluation_setting']['joint_setting']['formulas'] as $formulaIndex => &$formula) {
                    if (isset($formula['formula'])) {
                        // For joint formulas, can reference current measurement item's pre-processing results
                        $jointAvailableIds = array_merge($availableIds, [$point['setup']['name_id']]);
                        
                        $formulaErrors = $this->validateSingleFormula(
                            $formula['formula'],
                            $jointAvailableIds,
                            "{$pointPrefix}.joint_formula_{$formulaIndex}"
                        );
                        
                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Process formula
                            try {
                                $formula['formula'] = FormulaHelper::processFormula($formula['formula']);
                            } catch (\InvalidArgumentException $e) {
                                $errors["{$pointPrefix}.joint_formula_{$formulaIndex}"] = $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
        
        return $errors;
    }

    /**
     * Validate a single formula
     */
    private function validateSingleFormula(string $formula, array $availableIds, string $errorPrefix): array
    {
        $errors = [];
        
        // Validate format (must start with =)
        try {
            FormulaHelper::validateFormulaFormat($formula);
        } catch (\InvalidArgumentException $e) {
            $errors[$errorPrefix] = $e->getMessage();
            return $errors;
        }
        
        // Validate dependencies
        $missingDependencies = FormulaHelper::validateFormulaDependencies($formula, $availableIds);
        if (!empty($missingDependencies)) {
            $errors[$errorPrefix] = "Formula references measurement items yang belum dibuat: " . 
                implode(', ', $missingDependencies) . 
                ". Pastikan measurement item tersebut dibuat lebih dulu (order matters).";
        }
        
        return $errors;
    }

    /**
     * Validate rule evaluation setting
     */
    private function validateRuleEvaluation(array $ruleEvaluation): array
    {
        $errors = [];

        if (empty($ruleEvaluation['rule'])) {
            $errors[] = 'Rule is required';
        } elseif (!in_array($ruleEvaluation['rule'], ['MIN', 'MAX', 'BETWEEN'])) {
            $errors[] = 'Rule must be one of: MIN, MAX, BETWEEN';
        }

        if (!isset($ruleEvaluation['value']) || !is_numeric($ruleEvaluation['value'])) {
            $errors[] = 'Rule value must be a number';
        }

        if (empty($ruleEvaluation['unit'])) {
            $errors[] = 'Unit is required';
        }

        // BETWEEN rule specific validation
        if (isset($ruleEvaluation['rule']) && $ruleEvaluation['rule'] === 'BETWEEN') {
            if (!isset($ruleEvaluation['tolerance_minus']) || !is_numeric($ruleEvaluation['tolerance_minus'])) {
                $errors[] = 'Tolerance minus is required and must be a number for BETWEEN rule';
            }
            
            if (!isset($ruleEvaluation['tolerance_plus']) || !is_numeric($ruleEvaluation['tolerance_plus'])) {
                $errors[] = 'Tolerance plus is required and must be a number for BETWEEN rule';
            }
        } else {
            // For MIN/MAX, tolerance should be null
            if (isset($ruleEvaluation['tolerance_minus']) && $ruleEvaluation['tolerance_minus'] !== null) {
                $errors[] = 'Tolerance minus must be null for MIN/MAX rules';
            }
            
            if (isset($ruleEvaluation['tolerance_plus']) && $ruleEvaluation['tolerance_plus'] !== null) {
                $errors[] = 'Tolerance plus must be null for MIN/MAX rules';
            }
        }

        return $errors;
    }

    /**
     * Validate name uniqueness across variables, pre-processing formulas, and joint setting formulas
     */
    private function validateNameUniqueness(array $measurementPoints): array
    {
        $errors = [];
        
        foreach ($measurementPoints as $pointIndex => $point) {
            $allNames = [];
            
            // Collect variable names
            if (isset($point['variables']) && is_array($point['variables'])) {
                foreach ($point['variables'] as $variable) {
                    if (isset($variable['name'])) {
                        $allNames[] = $variable['name'];
                    }
                }
            }
            
            // Collect pre-processing formula names
            if (isset($point['pre_processing_formulas']) && is_array($point['pre_processing_formulas'])) {
                foreach ($point['pre_processing_formulas'] as $formula) {
                    if (isset($formula['name'])) {
                        $allNames[] = $formula['name'];
                    }
                }
            }
            
            // Collect joint setting formula names
            if (isset($point['evaluation_setting']['joint_setting']['formulas']) && is_array($point['evaluation_setting']['joint_setting']['formulas'])) {
                foreach ($point['evaluation_setting']['joint_setting']['formulas'] as $formula) {
                    if (isset($formula['name'])) {
                        $allNames[] = $formula['name'];
                    }
                }
            }
            
            // Check for duplicates
            $duplicates = array_diff_assoc($allNames, array_unique($allNames));
            if (!empty($duplicates)) {
                $errors["measurement_point_{$pointIndex}"] = 'Duplicate names found: ' . implode(', ', array_unique($duplicates));
            }
            
            // Validate name format (only alphabet and underscore)
            foreach ($allNames as $name) {
                if (!preg_match('/^[a-zA-Z_]+$/', $name)) {
                    $errors["measurement_point_{$pointIndex}"] = "Invalid name format: {$name}. Only alphabet and underscore allowed.";
                }
            }
        }
        
        return $errors;
    }

    /**
     * Enhanced measurement points validation with new business rules
     */
    private function validateMeasurementPointsEnhanced(array $measurementPoints): array
    {
        $errors = [];
        
        foreach ($measurementPoints as $pointIndex => $point) {
            $setup = $point['setup'] ?? [];
            $variables = $point['variables'] ?? [];
            $evaluationType = $point['evaluation_type'] ?? '';
            $evaluationSetting = $point['evaluation_setting'] ?? [];
            $ruleEvaluation = $point['rule_evaluation_setting'] ?? null;
            
            // Validate DERIVED source sample_amount consistency
            if (isset($setup['source']) && $setup['source'] === 'DERIVED') {
                if (!isset($setup['source_derived_name_id'])) {
                    $errors["measurement_point_{$pointIndex}"] = 'source_derived_name_id is required when source is DERIVED';
                }
                // Note: We should validate that sample_amount matches the derived source
                // This would require checking against existing measurement points
            }
            
            // Validate variables based on type
            foreach ($variables as $varIndex => $variable) {
                $varType = $variable['type'] ?? '';
                
                if ($varType === 'FIXED' && (!isset($variable['value']) || !is_numeric($variable['value']))) {
                    $errors["measurement_point_{$pointIndex}_variable_{$varIndex}"] = 'FIXED variables must have a numeric value';
                }
                
                if ($varType === 'FORMULA' && empty($variable['formula'])) {
                    $errors["measurement_point_{$pointIndex}_variable_{$varIndex}"] = 'FORMULA variables must have a formula';
                }
                
                if ($varType === 'MANUAL' && (!isset($variable['is_show']) || $variable['is_show'] !== true)) {
                    $errors["measurement_point_{$pointIndex}_variable_{$varIndex}"] = 'MANUAL variables must have is_show set to true';
                }
            }
            
            // Validate evaluation settings
            if ($evaluationType === 'PER_SAMPLE') {
                $perSampleSetting = $evaluationSetting['per_sample_setting'] ?? null;
                if (!$perSampleSetting) {
                    $errors["measurement_point_{$pointIndex}"] = 'per_sample_setting is required for PER_SAMPLE evaluation';
                } else {
                    // XOR validation: either is_raw_data is true OR pre_processing_formula_name is set
                    $isRawData = $perSampleSetting['is_raw_data'] ?? false;
                    $hasFormulaName = !empty($perSampleSetting['pre_processing_formula_name']);
                    
                    if (!($isRawData xor $hasFormulaName)) {
                        $errors["measurement_point_{$pointIndex}"] = 'Either is_raw_data must be true OR pre_processing_formula_name must be set (not both)';
                    }
                }
            }
            
            if ($evaluationType === 'JOINT') {
                $jointSetting = $evaluationSetting['joint_setting'] ?? null;
                if (!$jointSetting || empty($jointSetting['formulas'])) {
                    $errors["measurement_point_{$pointIndex}"] = 'joint_setting with formulas is required for JOINT evaluation';
                } else {
                    // Validate only one is_final_value = true
                    $finalValueCount = 0;
                    foreach ($jointSetting['formulas'] as $formula) {
                        if (isset($formula['is_final_value']) && $formula['is_final_value'] === true) {
                            $finalValueCount++;
                        }
                    }
                    
                    if ($finalValueCount !== 1) {
                        $errors["measurement_point_{$pointIndex}"] = 'Exactly one formula must have is_final_value set to true';
                    }
                }
            }
            
            // Validate rule_evaluation_setting based on nature
            if (isset($setup['nature'])) {
                if ($setup['nature'] === 'QUALITATIVE') {
                    if ($ruleEvaluation !== null) {
                        $errors["measurement_point_{$pointIndex}"] = 'rule_evaluation_setting must be null for QUALITATIVE nature';
                    }
                    
                    // Validate qualitative_setting exists
                    if (!isset($evaluationSetting['qualitative_setting'])) {
                        $errors["measurement_point_{$pointIndex}"] = 'qualitative_setting is required for QUALITATIVE nature';
                    }
                } else {
                    // QUANTITATIVE must have rule_evaluation_setting
                    if (!$ruleEvaluation) {
                        $errors["measurement_point_{$pointIndex}"] = 'rule_evaluation_setting is required for QUANTITATIVE nature';
                    }
                }
            }
        }
        
        return $errors;
    }
}
