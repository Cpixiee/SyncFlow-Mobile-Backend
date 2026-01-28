<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Models\MasterProduct;
use App\Models\MeasurementInstrument;
use App\Traits\ApiResponseTrait;
use App\Helpers\FormulaHelper;
use App\Helpers\ValidationErrorHelper;
use App\Enums\BasicInfoErrorEnum;
use App\Enums\MeasurementPointErrorEnum;
use App\Enums\MeasurementPointSectionEnum;
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
                'basic_info.color' => 'nullable|string|max:50',
                'basic_info.size' => 'nullable|string',

                // Measurement Points
                'measurement_points' => 'required|array|min:1',
                'measurement_points.*.setup.name' => 'required|string',
                'measurement_points.*.setup.name_id' => 'nullable|string', // ✅ Format validation moved to enhanced validation
                'measurement_points.*.setup.sample_amount' => 'required|integer|min:0', // ✅ Allow 0 for auto-calculate from formula
                'measurement_points.*.setup.nature' => 'required|in:QUALITATIVE,QUANTITATIVE',

                // source and type are optional for QUALITATIVE, but validated conditionally below
                'measurement_points.*.setup.source' => 'nullable|in:MANUAL,DERIVED,TOOL,INSTRUMENT',
                'measurement_points.*.setup.source_derived_name_id' => 'required_if:measurement_points.*.setup.source,DERIVED|nullable|string',
                'measurement_points.*.setup.source_tool_model' => 'required_if:measurement_points.*.setup.source,TOOL|nullable|string',
                'measurement_points.*.setup.source_instrument_id' => 'required_if:measurement_points.*.setup.source,INSTRUMENT|nullable',
                'measurement_points.*.setup.type' => 'nullable|in:SINGLE,BEFORE_AFTER',

                // Variables - ✅ Format validation moved to enhanced validation
                'measurement_points.*.variables' => 'nullable|array',
                'measurement_points.*.variables.*.type' => 'required_with:measurement_points.*.variables|in:FIXED,MANUAL,FORMULA',
                'measurement_points.*.variables.*.name' => 'required_with:measurement_points.*.variables|string', // ✅ Removed regex, handled in enhanced validation
                'measurement_points.*.variables.*.value' => 'required_if:measurement_points.*.variables.*.type,FIXED|nullable|numeric',
                'measurement_points.*.variables.*.formula' => 'required_if:measurement_points.*.variables.*.type,FORMULA|nullable|string',
                'measurement_points.*.variables.*.is_show' => 'required_with:measurement_points.*.variables|boolean',

                // Pre-processing formulas - ✅ Format validation moved to enhanced validation
                'measurement_points.*.pre_processing_formulas' => 'nullable|array',
                'measurement_points.*.pre_processing_formulas.*.name' => 'required_with:measurement_points.*.pre_processing_formulas|string', // ✅ Removed regex, handled in enhanced validation
                'measurement_points.*.pre_processing_formulas.*.formula' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.is_show' => 'required_with:measurement_points.*.pre_processing_formulas|boolean',

                // Evaluation
                'measurement_points.*.evaluation_type' => 'required|in:PER_SAMPLE,JOINT,SKIP_CHECK',
                // Allow empty evaluation_setting for SKIP_CHECK, required otherwise
                'measurement_points.*.evaluation_setting' => 'required_unless:measurement_points.*.evaluation_type,SKIP_CHECK|array|nullable',

                // Rule evaluation
                'measurement_points.*.rule_evaluation_setting' => 'nullable|array',
                'measurement_points.*.rule_evaluation_setting.rule' => 'required_with:measurement_points.*.rule_evaluation_setting|in:MIN,MAX,BETWEEN',
                'measurement_points.*.rule_evaluation_setting.unit' => 'required_with:measurement_points.*.rule_evaluation_setting|string',
                'measurement_points.*.rule_evaluation_setting.value' => 'required_with:measurement_points.*.rule_evaluation_setting|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_minus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_plus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',

                // Measurement Groups - group_name nullable untuk sorting single item
                'measurement_groups' => 'nullable|array',
                'measurement_groups.*.group_name' => 'nullable|string', // Nullable: single item tanpa group name untuk sorting
                'measurement_groups.*.measurement_items' => 'required_with:measurement_groups|array',
                'measurement_groups.*.order' => 'required_with:measurement_groups|integer',
            ]);

            // ✅ NEW: Enhanced validation runs FIRST (before Laravel validator)
            // This ensures user-friendly error messages are returned
            $basicInfo = $request->input('basic_info');
            $measurementPoints = $request->input('measurement_points');
            $measurementGroups = $request->input('measurement_groups', []);

            // Basic validation - check if required fields exist
            if (empty($basicInfo) || empty($measurementPoints)) {
                return $this->errorResponse(
                    'Request invalid: basic_info dan measurement_points wajib diisi',
                    'VALIDATION_ERROR',
                    400
                );
            }

            // Validate product_category_id exists and get category details
            $category = ProductCategory::find($basicInfo['product_category_id'] ?? null);
            if (!$category) {
                $basicInfoErrors = [];
                if (!isset($basicInfo['product_category_id'])) {
                    $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                        BasicInfoErrorEnum::REQUIRED,
                        'product_category_id',
                        'Product category ID wajib diisi'
                    );
                } else {
                    $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                        BasicInfoErrorEnum::INVALID_CATEGORY,
                        'product_category_id',
                        'Product category tidak ditemukan'
                    );
                }
                $errorResponse = ValidationErrorHelper::formatErrorResponse(
                    'PRODUCT_VALIDATION_ERROR',
                    $basicInfoErrors,
                    []
                );
                return $this->errorResponse(
                    'Validation failed',
                    'PRODUCT_VALIDATION_ERROR',
                    400,
                    $errorResponse
                );
            }

            // Auto-generate name_id if not provided (before enhanced validation)
            $measurementPoints = $this->autoGenerateNameIds($measurementPoints);

            // ✅ FIX: Normalize source_instrument_id (convert name/model to ID if string provided)
            $measurementPoints = $this->normalizeInstrumentIds($measurementPoints);

            // ✅ NEW: Enhanced validation with structured error messages (runs FIRST)
            $enhancedValidation = $this->validateProductEnhanced($basicInfo, $measurementPoints, $category);
            if (!empty($enhancedValidation['basic_info']) || !empty($enhancedValidation['measurement_points'])) {
                $errorResponse = ValidationErrorHelper::formatErrorResponse(
                    'PRODUCT_VALIDATION_ERROR',
                    $enhancedValidation['basic_info'],
                    $enhancedValidation['measurement_points']
                );
                return $this->errorResponse(
                    'Validation failed',
                    'PRODUCT_VALIDATION_ERROR',
                    400,
                    $errorResponse
                );
            }

            // ✅ Run Laravel validator for basic type checking (after enhanced validation passes)
            // This catches any remaining type/format issues that enhanced validation might miss
            $validator = Validator::make($request->all(), [
                // Basic Info
                'basic_info.product_category_id' => 'required|integer|exists:product_categories,id',
                'basic_info.product_name' => 'required|string',
                'basic_info.ref_spec_number' => 'nullable|string',
                'basic_info.nom_size_vo' => 'nullable|string',
                'basic_info.article_code' => 'nullable|string',
                'basic_info.no_document' => 'nullable|string',
                'basic_info.no_doc_reference' => 'nullable|string',
                'basic_info.color' => 'nullable|string|max:50',
                'basic_info.size' => 'nullable|string',

                // Measurement Points - basic type checking only
                'measurement_points' => 'required|array|min:1',
                'measurement_points.*.setup.name' => 'required|string',
                'measurement_points.*.setup.name_id' => 'nullable|string',
                'measurement_points.*.setup.sample_amount' => 'required|integer|min:0',
                'measurement_points.*.setup.nature' => 'required|in:QUALITATIVE,QUANTITATIVE',
                'measurement_points.*.setup.source' => 'nullable|in:MANUAL,DERIVED,TOOL,INSTRUMENT',
                'measurement_points.*.setup.source_derived_name_id' => 'required_if:measurement_points.*.setup.source,DERIVED|nullable|string',
                'measurement_points.*.setup.source_tool_model' => 'required_if:measurement_points.*.setup.source,TOOL|nullable|string',
                'measurement_points.*.setup.source_instrument_id' => 'required_if:measurement_points.*.setup.source,INSTRUMENT|nullable',
                'measurement_points.*.setup.type' => 'nullable|in:SINGLE,BEFORE_AFTER',
                'measurement_points.*.variables' => 'nullable|array',
                'measurement_points.*.variables.*.type' => 'required_with:measurement_points.*.variables|in:FIXED,MANUAL,FORMULA',
                'measurement_points.*.variables.*.name' => 'required_with:measurement_points.*.variables|string',
                'measurement_points.*.variables.*.value' => 'required_if:measurement_points.*.variables.*.type,FIXED|nullable|numeric',
                'measurement_points.*.variables.*.formula' => 'required_if:measurement_points.*.variables.*.type,FORMULA|nullable|string',
                'measurement_points.*.variables.*.is_show' => 'required_with:measurement_points.*.variables|boolean',
                'measurement_points.*.pre_processing_formulas' => 'nullable|array',
                'measurement_points.*.pre_processing_formulas.*.name' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.formula' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.is_show' => 'required_with:measurement_points.*.pre_processing_formulas|boolean',
                'measurement_points.*.evaluation_type' => 'required|in:PER_SAMPLE,JOINT,SKIP_CHECK',
                'measurement_points.*.evaluation_setting' => 'required_unless:measurement_points.*.evaluation_type,SKIP_CHECK|array|nullable',
                'measurement_points.*.rule_evaluation_setting' => 'nullable|array',
                'measurement_points.*.rule_evaluation_setting.rule' => 'required_with:measurement_points.*.rule_evaluation_setting|in:MIN,MAX,BETWEEN',
                'measurement_points.*.rule_evaluation_setting.unit' => 'required_with:measurement_points.*.rule_evaluation_setting|string',
                'measurement_points.*.rule_evaluation_setting.value' => 'required_with:measurement_points.*.rule_evaluation_setting|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_minus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_plus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                'measurement_groups' => 'nullable|array',
                'measurement_groups.*.group_name' => 'nullable|string',
                'measurement_groups.*.measurement_items' => 'required_with:measurement_groups|array',
                'measurement_groups.*.order' => 'required_with:measurement_groups|integer',
            ]);

            if ($validator->fails()) {
                // If Laravel validator still fails, convert to enhanced format
                $errors = $validator->errors();
                $basicInfoErrors = [];
                $measurementPointErrors = [];

                foreach ($errors->keys() as $key) {
                    if (str_starts_with($key, 'basic_info.')) {
                        $field = str_replace('basic_info.', '', $key);
                        $message = $errors->first($key);
                        $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                            BasicInfoErrorEnum::INVALID_FORMAT,
                            $field,
                            $message
                        );
                    } elseif (str_starts_with($key, 'measurement_points.')) {
                        // Extract measurement point index and field
                        preg_match('/measurement_points\.(\d+)\.(.+)/', $key, $matches);
                        if (count($matches) === 3) {
                            $pointIndex = (int)$matches[1];
                            $fieldPath = $matches[2];
                            
                            // Get measurement point name if available
                            $measurementPoint = $measurementPoints[$pointIndex] ?? null;
                            $setup = $measurementPoint['setup'] ?? [];
                            $pointName = $setup['name'] ?? "Measurement Point #{$pointIndex}";
                            $pointNameId = $setup['name_id'] ?? null;

                            // Determine section and entity name
                            $section = MeasurementPointSectionEnum::SETUP;
                            $entityName = 'setup';
                            
                            if (str_contains($fieldPath, 'variables')) {
                                $section = MeasurementPointSectionEnum::VARIABLE;
                                preg_match('/variables\.(\d+)\.(.+)/', $fieldPath, $varMatches);
                                $entityName = $varMatches[2] ?? 'variable';
                            } elseif (str_contains($fieldPath, 'pre_processing_formulas')) {
                                $section = MeasurementPointSectionEnum::PRE_PROCESSING_FORMULA;
                                preg_match('/pre_processing_formulas\.(\d+)\.(.+)/', $fieldPath, $formulaMatches);
                                $entityName = $formulaMatches[2] ?? 'pre_processing_formula';
                            }

                            $message = $errors->first($key);
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $pointNameId, 'name' => $pointName],
                                $section,
                                $entityName,
                                MeasurementPointErrorEnum::INVALID_FORMAT,
                                "{$pointName} - {$message}"
                            );
                        }
                    }
                }

                $errorResponse = ValidationErrorHelper::formatErrorResponse(
                    'PRODUCT_VALIDATION_ERROR',
                    $basicInfoErrors,
                    $measurementPointErrors
                );
                return $this->errorResponse(
                    'Validation failed',
                    'PRODUCT_VALIDATION_ERROR',
                    400,
                    $errorResponse
                );
            }

            // Validate and process formulas
            $formulaValidationErrors = $this->validateAndProcessFormulasEnhanced($measurementPoints);
            if (!empty($formulaValidationErrors)) {
                $errorResponse = ValidationErrorHelper::formatErrorResponse(
                    'FORMULA_VALIDATION_ERROR',
                    [],
                    $formulaValidationErrors
                );
                return $this->errorResponse(
                    'Formula validation failed',
                    'FORMULA_VALIDATION_ERROR',
                    400,
                    $errorResponse
                );
            }

            // Process measurement groups if provided
            $processedMeasurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups ?? []);

            // Get active quarter (optional - quarter is only for measurement results, not product creation)
            $activeQuarter = Quarter::getActiveQuarter();

            // Create product (quarter_id is nullable)
            $product = Product::create([
                'quarter_id' => $activeQuarter?->id,
                'product_category_id' => $basicInfo['product_category_id'],
                'product_name' => $basicInfo['product_name'],
                'ref_spec_number' => $basicInfo['ref_spec_number'] ?? null,
                'nom_size_vo' => $basicInfo['nom_size_vo'] ?? null,
                'article_code' => $basicInfo['article_code'] ?? null,
                'no_document' => $basicInfo['no_document'] ?? null,
                'no_doc_reference' => $basicInfo['no_doc_reference'] ?? null,
                'color' => $basicInfo['color'] ?? null,
                'size' => $basicInfo['size'] ?? null,
                'measurement_points' => $processedMeasurementPoints,
                'measurement_groups' => $measurementGroups,
            ]);

            $product->load(['quarter', 'productCategory']);

            return $this->successResponse([
                'product_id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'product_spec_name' => $product->product_spec_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                    'color' => $product->color,
                    'size' => $product->size,
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

            // Process measurement grouping untuk sorting dan menambahkan group info
            $measurementPoints = $product->measurement_points ?? [];
            $measurementGroups = $product->measurement_groups ?? [];
            
            if (!empty($measurementGroups)) {
                $measurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups);
            }

            return $this->successResponse([
                'id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_category_name' => $product->productCategory ? $product->productCategory->name : null,
                    'product_name' => $product->product_name,
                    'product_spec_name' => $product->product_spec_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                    'color' => $product->color,
                    'size' => $product->size,
                ],
                'measurement_points' => $measurementPoints,
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
            $productCategoryId = $request->input('product_category_id');
            $searchQuery = $request->input('query');

            $query = Product::with(['quarter', 'productCategory']);

            // Filter by product_category_id if provided
            if ($productCategoryId) {
                $query->where('product_category_id', $productCategoryId);
            }

            // Search filter - prioritize product_spec_name for search
            if ($searchQuery) {
                $query->where(function($q) use ($searchQuery) {
                    $q->where('product_spec_name', 'like', "%{$searchQuery}%")
                      ->orWhere('product_name', 'like', "%{$searchQuery}%")
                      ->orWhere('product_id', 'like', "%{$searchQuery}%")
                      ->orWhere('article_code', 'like', "%{$searchQuery}%")
                      ->orWhere('ref_spec_number', 'like', "%{$searchQuery}%");
                });
            }

            $products = $query->paginate($limit, ['*'], 'page', $page);

            $transformedProducts = collect($products->items())
                ->map(function ($product) {
                    return [
                        'id' => $product->product_id,
                        'product_category_id' => $product->product_category_id,
                        'product_category_name' => $product->productCategory->name,
                        'product_name' => $product->product_name,
                        'product_spec_name' => $product->product_spec_name,
                        'ref_spec_number' => $product->ref_spec_number,
                        'nom_size_vo' => $product->nom_size_vo,
                        'article_code' => $product->article_code,
                        'no_document' => $product->no_document,
                        'no_doc_reference' => $product->no_doc_reference,
                        'color' => $product->color,
                        'size' => $product->size,
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
                'color' => 'nullable|string|max:50',
                'size' => 'nullable|string|max:255',
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
     * Delete product
     */
    /**
     * Get master products list (only products that haven't been created yet)
     * GET /api/v1/master-products?product_category_id=1&query=AVSSH
     */
    public function getMasterProducts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'query' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $productCategoryId = $request->input('product_category_id');
            $searchQuery = $request->input('query');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $query = MasterProduct::with('productCategory');

            // Filter by category if provided
            if ($productCategoryId) {
                $query->where('product_category_id', $productCategoryId);
            }

            // Search filter - prioritize product_spec_name for search
            if ($searchQuery) {
                $query->where(function($q) use ($searchQuery) {
                    $q->where('product_spec_name', 'like', "%{$searchQuery}%")
                      ->orWhere('product_name', 'like', "%{$searchQuery}%")
                      ->orWhere('article_code', 'like', "%{$searchQuery}%");
                });
            }

            // Get all master products
            $allMasterProducts = $query->get();

            // Filter out products that have already been created (based on product_spec_name)
            $existingProductSpecNames = Product::whereIn('product_spec_name', $allMasterProducts->pluck('product_spec_name'))
                ->pluck('product_spec_name')
                ->toArray();

            $availableMasterProducts = $allMasterProducts->filter(function($masterProduct) use ($existingProductSpecNames) {
                return !in_array($masterProduct->product_spec_name, $existingProductSpecNames);
            });

            // Paginate manually
            $total = $availableMasterProducts->count();
            $offset = ($page - 1) * $limit;
            $paginatedProducts = $availableMasterProducts->slice($offset, $limit)->values();

            $transformedProducts = $paginatedProducts->map(function ($masterProduct) {
                return [
                    'id' => $masterProduct->id,
                    'product_category_id' => $masterProduct->product_category_id,
                    'product_category_name' => $masterProduct->productCategory->name,
                    'product_name' => $masterProduct->product_name,
                    'product_spec_name' => $masterProduct->product_spec_name,
                    'ref_spec_number' => $masterProduct->ref_spec_number,
                    'nom_size_vo' => $masterProduct->nom_size_vo,
                    'article_code' => $masterProduct->article_code,
                    'no_document' => $masterProduct->no_document,
                    'no_doc_reference' => $masterProduct->no_doc_reference,
                    'color' => $masterProduct->color,
                    'size' => $masterProduct->size,
                ];
            })->toArray();

            $totalPages = ceil($total / $limit);

            return $this->paginationResponse(
                $transformedProducts,
                [
                    'current_page' => (int)$page,
                    'total_page' => $totalPages,
                    'limit' => (int)$limit,
                    'total_docs' => $total,
                ]
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Create product from existing master product
     * POST /api/v1/products/from-existing
     */
    public function createFromExisting(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'master_product_id' => 'required|integer|exists:master_products,id',
                'measurement_points' => 'required|array|min:1',
                'measurement_groups' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Get master product
            $masterProduct = MasterProduct::with('productCategory')->find($request->master_product_id);
            if (!$masterProduct) {
                return $this->notFoundResponse('Master product tidak ditemukan');
            }

            // Check if product with this product_spec_name already exists
            $existingProduct = Product::where('product_spec_name', $masterProduct->product_spec_name)->first();
            if ($existingProduct) {
                return $this->errorResponse(
                    "Product dengan spesifikasi '{$masterProduct->product_spec_name}' sudah pernah dibuat",
                    'PRODUCT_ALREADY_EXISTS',
                    400
                );
            }

            // Build basic_info from master product
            $basicInfo = [
                'product_category_id' => $masterProduct->product_category_id,
                'product_name' => $masterProduct->product_name,
                'product_spec_name' => $masterProduct->product_spec_name,
                'ref_spec_number' => $masterProduct->ref_spec_number,
                'nom_size_vo' => $masterProduct->nom_size_vo,
                'article_code' => $masterProduct->article_code,
                'no_document' => $masterProduct->no_document,
                'no_doc_reference' => $masterProduct->no_doc_reference,
                'color' => $masterProduct->color,
                'size' => $masterProduct->size,
            ];

            // Merge with measurement_points from request
            $measurementPoints = $request->input('measurement_points');
            $measurementGroups = $request->input('measurement_groups', []);

            // Auto-generate name_id if not provided
            $measurementPoints = $this->autoGenerateNameIds($measurementPoints);

            // ✅ FIX: Normalize source_instrument_id (convert name/model to ID if string provided)
            $measurementPoints = $this->normalizeInstrumentIds($measurementPoints);

            // Validate measurement_points (reuse existing validation logic)
            $measurementPointsValidation = $this->validateMeasurementPoints($measurementPoints);
            if (!empty($measurementPointsValidation)) {
                return $this->errorResponse('Measurement points validation failed', 'MEASUREMENT_VALIDATION_ERROR', 400, $measurementPointsValidation);
            }

            // Validate and process formulas
            $formulaValidationErrors = $this->validateAndProcessFormulas($measurementPoints);
            if (!empty($formulaValidationErrors)) {
                return $this->errorResponse('Formula validation failed', 'FORMULA_VALIDATION_ERROR', 400, $formulaValidationErrors);
            }

            // Process measurement groups
            $processedMeasurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups ?? []);

            // Validate quantitative requirements
            $quantitativeErrors = $this->validateQuantitativeRequirements($measurementPoints);
            if (!empty($quantitativeErrors)) {
                return $this->errorResponse('QUANTITATIVE measurement validation failed', 'QUANTITATIVE_VALIDATION_ERROR', 400, $quantitativeErrors);
            }

            // Validate qualitative requirements
            $qualitativeErrors = $this->validateQualitativeRequirements($measurementPoints);
            if (!empty($qualitativeErrors)) {
                return $this->errorResponse('QUALITATIVE measurement validation failed', 'QUALITATIVE_VALIDATION_ERROR', 400, $qualitativeErrors);
            }

            // Validate type-specific rules
            $typeSpecificErrors = $this->validateTypeSpecificRules($measurementPoints);
            if (!empty($typeSpecificErrors)) {
                return $this->errorResponse('Type-specific validation failed', 'TYPE_VALIDATION_ERROR', 400, $typeSpecificErrors);
            }

            // Validate name uniqueness
            $nameValidationErrors = $this->validateNameUniqueness($measurementPoints);
            if (!empty($nameValidationErrors)) {
                return $this->errorResponse('Name validation failed', 'NAME_UNIQUENESS_ERROR', 400, $nameValidationErrors);
            }

            // Get active quarter
            $activeQuarter = Quarter::getActiveQuarter();

            // Create product
            $product = Product::create([
                'quarter_id' => $activeQuarter?->id,
                'product_category_id' => $basicInfo['product_category_id'],
                'product_name' => $basicInfo['product_name'],
                'product_spec_name' => $basicInfo['product_spec_name'],
                'ref_spec_number' => $basicInfo['ref_spec_number'],
                'nom_size_vo' => $basicInfo['nom_size_vo'],
                'article_code' => $basicInfo['article_code'],
                'no_document' => $basicInfo['no_document'],
                'no_doc_reference' => $basicInfo['no_doc_reference'],
                'color' => $basicInfo['color'],
                'size' => $basicInfo['size'],
                'measurement_points' => $processedMeasurementPoints,
                'measurement_groups' => $measurementGroups,
            ]);

            $product->load(['quarter', 'productCategory']);

            return $this->successResponse([
                'product_id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'product_spec_name' => $product->product_spec_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                    'color' => $product->color,
                    'size' => $product->size,
                ],
                'measurement_points' => $product->measurement_points,
                'measurement_groups' => $product->measurement_groups,
                'product_category' => [
                    'id' => $masterProduct->productCategory->id,
                    'name' => $masterProduct->productCategory->name
                ]
            ], 'Product berhasil dibuat dari master product', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'CREATION_ERROR', 500);
        }
    }

    public function destroy(string $productId)
    {
        try {
            $product = Product::where('product_id', $productId)->first();

            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // ✅ NEW: Delete related data (cascade delete)
            // Delete product measurements dan turunannya
            $product->productMeasurements()->delete();
            
            // Delete scale measurements
            $product->scaleMeasurements()->delete();

            // Delete product
            $product->delete();

            return $this->successResponse(
                ['deleted' => true],
                'Product dan data terkait berhasil dihapus'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'DELETE_ERROR', 500);
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, string $productId)
    {
        try {
            $product = Product::where('product_id', $productId)->first();

            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // Validate request (basic type checking only, error akan diformat seperti create)
            $validator = Validator::make($request->all(), [
                // Basic Info - hanya yang dikirim yang akan diupdate
                'basic_info.product_category_id' => 'nullable|integer|exists:product_categories,id',
                'basic_info.product_name' => 'nullable|string',
                'basic_info.ref_spec_number' => 'nullable|string',
                'basic_info.nom_size_vo' => 'nullable|string',
                'basic_info.article_code' => 'nullable|string',
                'basic_info.no_document' => 'nullable|string',
                'basic_info.no_doc_reference' => 'nullable|string',
                'basic_info.color' => 'nullable|string|max:50',
                'basic_info.size' => 'nullable|string',

                // Measurement Points - optional untuk update
                'measurement_points' => 'nullable|array',
                'measurement_points.*.setup.name' => 'required_with:measurement_points|string',
                // ✅ Align with create: format validation handled in enhanced validation helper
                'measurement_points.*.setup.name_id' => 'required_with:measurement_points|string',
                'measurement_points.*.setup.sample_amount' => 'required_with:measurement_points|integer|min:0', // ✅ Allow 0 for auto-calculate from formula
                'measurement_points.*.setup.nature' => 'required_with:measurement_points|in:QUALITATIVE,QUANTITATIVE',
                'measurement_points.*.setup.source' => 'nullable|in:MANUAL,DERIVED,TOOL,INSTRUMENT',
                'measurement_points.*.setup.source_derived_name_id' => 'required_if:measurement_points.*.setup.source,DERIVED|nullable|string',
                'measurement_points.*.setup.source_tool_model' => 'required_if:measurement_points.*.setup.source,TOOL|nullable|string',
                'measurement_points.*.setup.source_instrument_id' => 'required_if:measurement_points.*.setup.source,INSTRUMENT|nullable',
                'measurement_points.*.setup.type' => 'nullable|in:SINGLE,BEFORE_AFTER',
                'measurement_points.*.variables' => 'nullable|array',
                'measurement_points.*.variables.*.type' => 'required_with:measurement_points.*.variables|in:FIXED,MANUAL,FORMULA',
                // ✅ Align with create: name format (allowed chars, etc) divalidasi di enhanced validation
                'measurement_points.*.variables.*.name' => 'required_with:measurement_points.*.variables|string',
                'measurement_points.*.variables.*.value' => 'required_if:measurement_points.*.variables.*.type,FIXED|nullable|numeric',
                'measurement_points.*.variables.*.formula' => 'required_if:measurement_points.*.variables.*.type,FORMULA|nullable|string',
                'measurement_points.*.variables.*.is_show' => 'required_with:measurement_points.*.variables|boolean',
                'measurement_points.*.pre_processing_formulas' => 'nullable|array',
                // ✅ Align with create: name format divalidasi di enhanced validation
                'measurement_points.*.pre_processing_formulas.*.name' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.formula' => 'required_with:measurement_points.*.pre_processing_formulas|string',
                'measurement_points.*.pre_processing_formulas.*.is_show' => 'required_with:measurement_points.*.pre_processing_formulas|boolean',
                'measurement_points.*.evaluation_type' => 'required_with:measurement_points|in:PER_SAMPLE,JOINT,SKIP_CHECK',
                // Allow empty evaluation_setting for SKIP_CHECK, required otherwise
                'measurement_points.*.evaluation_setting' => 'required_unless:measurement_points.*.evaluation_type,SKIP_CHECK|array|nullable',
                'measurement_points.*.rule_evaluation_setting' => 'nullable|array',
                'measurement_points.*.rule_evaluation_setting.rule' => 'required_with:measurement_points.*.rule_evaluation_setting|in:MIN,MAX,BETWEEN',
                'measurement_points.*.rule_evaluation_setting.unit' => 'required_with:measurement_points.*.rule_evaluation_setting|string',
                'measurement_points.*.rule_evaluation_setting.value' => 'required_with:measurement_points.*.rule_evaluation_setting|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_minus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',
                'measurement_points.*.rule_evaluation_setting.tolerance_plus' => 'required_if:measurement_points.*.rule_evaluation_setting.rule,BETWEEN|nullable|numeric',

                // Measurement Groups - group_name nullable untuk sorting single item
                'measurement_groups' => 'nullable|array',
                'measurement_groups.*.group_name' => 'nullable|string', // Nullable: single item tanpa group name untuk sorting
                'measurement_groups.*.measurement_items' => 'required_with:measurement_groups|array',
                'measurement_groups.*.order' => 'required_with:measurement_groups|integer',
            ]);

            if ($validator->fails()) {
                // ✅ Align error style with create: convert Laravel validation errors
                // menjadi basic_info & measurement_points menggunakan ValidationErrorHelper
                $errors = $validator->errors();
                $basicInfoErrors = [];
                $measurementPointErrors = [];

                foreach ($errors->keys() as $key) {
                    if (str_starts_with($key, 'basic_info.')) {
                        $field = str_replace('basic_info.', '', $key);
                        $message = $errors->first($key);
                        $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                            BasicInfoErrorEnum::INVALID_FORMAT,
                            $field,
                            $message
                        );
                    } elseif (str_starts_with($key, 'measurement_points.')) {
                        // Extract measurement point index and field
                        preg_match('/measurement_points\.(\d+)\.(.+)/', $key, $matches);
                        if (count($matches) === 3) {
                            $pointIndex = (int) $matches[1];
                            $fieldPath = $matches[2];

                            $measurementPointsInput = $request->input('measurement_points', []);
                            // Get measurement point name if available
                            $measurementPoint = $measurementPointsInput[$pointIndex] ?? null;
                            $setup = $measurementPoint['setup'] ?? [];
                            $pointName = $setup['name'] ?? "Measurement Point #{$pointIndex}";
                            $pointNameId = $setup['name_id'] ?? null;

                            // Determine section and entity name
                            $section = MeasurementPointSectionEnum::SETUP;
                            $entityName = 'setup';

                            if (str_contains($fieldPath, 'variables')) {
                                $section = MeasurementPointSectionEnum::VARIABLE;
                                preg_match('/variables\.(\d+)\.(.+)/', $fieldPath, $varMatches);
                                $entityName = $varMatches[2] ?? 'variable';
                            } elseif (str_contains($fieldPath, 'pre_processing_formulas')) {
                                $section = MeasurementPointSectionEnum::PRE_PROCESSING_FORMULA;
                                preg_match('/pre_processing_formulas\.(\d+)\.(.+)/', $fieldPath, $formulaMatches);
                                $entityName = $formulaMatches[2] ?? 'pre_processing_formula';
                            }

                            $message = $errors->first($key);
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $pointNameId, 'name' => $pointName],
                                $section,
                                $entityName,
                                MeasurementPointErrorEnum::INVALID_FORMAT,
                                "{$pointName} - {$message}"
                            );
                        }
                    }
                }

                $errorResponse = ValidationErrorHelper::formatErrorResponse(
                    'PRODUCT_VALIDATION_ERROR',
                    $basicInfoErrors,
                    $measurementPointErrors
                );

                return $this->errorResponse(
                    'Validation failed',
                    'PRODUCT_VALIDATION_ERROR',
                    400,
                    $errorResponse
                );
            }

            // Update basic info jika ada
            if ($request->has('basic_info')) {
                $basicInfo = $request->input('basic_info');
                
                // Validate product_category_id dan product_name jika diubah
                if (isset($basicInfo['product_category_id'])) {
                    $category = ProductCategory::find($basicInfo['product_category_id']);
                    if (!$category) {
                        return $this->errorResponse('Product category tidak ditemukan', 'CATEGORY_NOT_FOUND', 400);
                    }
                    $product->product_category_id = $basicInfo['product_category_id'];
                }

                if (isset($basicInfo['product_name'])) {
                    // Validate product_name valid untuk category
                    $category = $product->productCategory;
                    if (isset($basicInfo['product_category_id'])) {
                        $category = ProductCategory::find($basicInfo['product_category_id']);
                    }
                    
                    if (!in_array($basicInfo['product_name'], $category->products)) {
                        return $this->errorResponse(
                            'Product name "' . $basicInfo['product_name'] . '" tidak valid untuk category "' . $category->name . '"',
                            'INVALID_PRODUCT_NAME',
                            400
                        );
                    }
                    $product->product_name = $basicInfo['product_name'];
                }

                // Update other basic info fields
                $optionalFields = ['ref_spec_number', 'nom_size_vo', 'article_code', 'no_document', 'no_doc_reference', 'color', 'size'];
                foreach ($optionalFields as $field) {
                    if (isset($basicInfo[$field])) {
                        $product->$field = $basicInfo[$field];
                    }
                }
            }

            // Update measurement points jika ada
            if ($request->has('measurement_points')) {
                // ✅ NEW: Delete related data sebelum update (supaya tidak ada data yang broken)
                $product->productMeasurements()->delete();
                $product->scaleMeasurements()->delete();

                $measurementPoints = $request->input('measurement_points');
                
                // Auto-generate name_id if not provided
                $measurementPoints = $this->autoGenerateNameIds($measurementPoints);
                
                // ✅ FIX: Normalize source_instrument_id (convert name/model to ID if string provided)
                $measurementPoints = $this->normalizeInstrumentIds($measurementPoints);

                // ✅ NEW: Enhanced validation dengan struktur error sama seperti create
                $basicInfoInput = $request->input('basic_info', []);
                $basicInfoForValidation = [
                    'product_name' => $basicInfoInput['product_name'] ?? $product->product_name,
                    'color' => $basicInfoInput['color'] ?? $product->color,
                ];

                // Gunakan category terbaru jika diubah, kalau tidak pakai category existing
                $category = $product->productCategory;
                if (isset($basicInfoInput['product_category_id'])) {
                    $category = ProductCategory::find($basicInfoInput['product_category_id']) ?? $category;
                }

                if ($category) {
                    $enhancedValidation = $this->validateProductEnhanced($basicInfoForValidation, $measurementPoints, $category);
                    if (!empty($enhancedValidation['basic_info']) || !empty($enhancedValidation['measurement_points'])) {
                        $errorResponse = ValidationErrorHelper::formatErrorResponse(
                            'PRODUCT_VALIDATION_ERROR',
                            $enhancedValidation['basic_info'],
                            $enhancedValidation['measurement_points']
                        );
                        return $this->errorResponse(
                            'Validation failed',
                            'PRODUCT_VALIDATION_ERROR',
                            400,
                            $errorResponse
                        );
                    }
                }

                // ✅ Validate and process formulas with enhanced validator (same as create)
                $formulaValidationErrors = $this->validateAndProcessFormulasEnhanced($measurementPoints);
                if (!empty($formulaValidationErrors)) {
                    $errorResponse = ValidationErrorHelper::formatErrorResponse(
                        'FORMULA_VALIDATION_ERROR',
                        [],
                        $formulaValidationErrors
                    );
                    return $this->errorResponse(
                        'Formula validation failed',
                        'FORMULA_VALIDATION_ERROR',
                        400,
                        $errorResponse
                    );
                }

                // Additional validation for measurement points
                $validationErrors = $this->validateMeasurementPoints($measurementPoints);
                if (!empty($validationErrors)) {
                    return $this->errorResponse('Measurement points validation failed', 'MEASUREMENT_VALIDATION_ERROR', 400, $validationErrors);
                }

                // Validate source and type are required for QUANTITATIVE
                $quantitativeErrors = $this->validateQuantitativeRequirements($measurementPoints);
                if (!empty($quantitativeErrors)) {
                    return $this->errorResponse('QUANTITATIVE measurement validation failed', 'QUANTITATIVE_VALIDATION_ERROR', 400, $quantitativeErrors);
                }

                // ✅ NEW: Validate QUALITATIVE requirements
                $qualitativeErrors = $this->validateQualitativeRequirements($measurementPoints);
                if (!empty($qualitativeErrors)) {
                    return $this->errorResponse('QUALITATIVE measurement validation failed', 'QUALITATIVE_VALIDATION_ERROR', 400, $qualitativeErrors);
                }

                // Validate type-specific rules
                $typeSpecificErrors = $this->validateTypeSpecificRules($measurementPoints);
                if (!empty($typeSpecificErrors)) {
                    return $this->errorResponse('Type-specific validation failed', 'TYPE_VALIDATION_ERROR', 400, $typeSpecificErrors);
                }

                // Validate name uniqueness
                $nameValidationErrors = $this->validateNameUniqueness($measurementPoints);
                if (!empty($nameValidationErrors)) {
                    return $this->errorResponse('Name validation failed', 'NAME_UNIQUENESS_ERROR', 400, $nameValidationErrors);
                }

                // Process measurement groups if provided
                $measurementGroups = $request->input('measurement_groups', []);
                $processedMeasurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups);

                $product->measurement_points = $processedMeasurementPoints;
                
                if ($request->has('measurement_groups')) {
                    $product->measurement_groups = $measurementGroups;
                }
            }

            $product->save();

            return $this->successResponse([
                'product_id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'product_spec_name' => $product->product_spec_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                    'color' => $product->color,
                    'size' => $product->size,
                ],
                'measurement_points' => $product->measurement_points,
                'measurement_groups' => $product->measurement_groups,
            ], 'Product berhasil diupdate');

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'UPDATE_ERROR', 500);
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

            // ✅ NEW: Allow sample_amount = 0 with special constraints
            if (!isset($setup['sample_amount']) || $setup['sample_amount'] < 0) {
                $pointErrors[] = 'Sample amount must be at least 0';
            }

            // ✅ NEW: Validate constraints for sample_amount = 0
            if (isset($setup['sample_amount']) && $setup['sample_amount'] === 0) {
                // Constraint 1: Type must be SINGLE
                if (isset($setup['type']) && $setup['type'] !== 'SINGLE') {
                    $pointErrors[] = 'Type must be SINGLE when sample_amount = 0';
                }
                
                // Constraint 2: Evaluation must be JOINT
                if (isset($point['evaluation_type']) && $point['evaluation_type'] !== 'JOINT') {
                    $pointErrors[] = 'Evaluation type must be JOINT when sample_amount = 0';
                }
                
                // Constraint 3: Pre-processing formulas cannot be used
                if (isset($point['pre_processing_formulas']) && !empty($point['pre_processing_formulas'])) {
                    $pointErrors[] = 'Pre-processing formulas cannot be used when sample_amount = 0';
                }
            }

            // Nature-specific validation
            if (isset($setup['nature'])) {
                if ($setup['nature'] === 'QUANTITATIVE') {
                    // Quantitative must have rule_evaluation_setting UNLESS evaluation_type is SKIP_CHECK
                    $evaluationType = $point['evaluation_type'] ?? null;
                    if ($evaluationType !== 'SKIP_CHECK') {
                        if (!isset($point['rule_evaluation_setting']) || empty($point['rule_evaluation_setting'])) {
                            $pointErrors[] = 'Rule evaluation setting is required for QUANTITATIVE nature';
                        } else {
                            $ruleErrors = $this->validateRuleEvaluation($point['rule_evaluation_setting']);
                            if (!empty($ruleErrors)) {
                                $pointErrors = array_merge($pointErrors, $ruleErrors);
                            }
                        }
                    }
                    // For SKIP_CHECK, rule_evaluation_setting is optional (can be null/omitted)

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

                    // ✅ REMOVED: Validasi lama yang bertentangan dengan requirement baru
                    // Validasi untuk QUALITATIVE (evaluation_type, rule_evaluation_setting, sample_amount)
                    // sekarang ditangani oleh validateQualitativeRequirements() method
                    // yang lebih spesifik dan sesuai dengan requirement terbaru
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
        usort($measurementGroups, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        foreach ($measurementGroups as $group) {
            $hasGroupName = !empty($group['group_name']);
            
            foreach ($group['measurement_items'] as $itemNameId) {
                if (isset($measurementMap[$itemNameId])) {
                    // Add group information to measurement point
                    $measurementPoint = $measurementMap[$itemNameId];
                    
                    // If group_name exists, this is a grouped item
                    // If group_name is null/empty, this is a standalone item
                    if ($hasGroupName) {
                        $measurementPoint['group_name'] = $group['group_name'];
                        $measurementPoint['group_order'] = $group['order'];
                    } else {
                        $measurementPoint['group_name'] = null; // Standalone item
                        $measurementPoint['group_order'] = $group['order'];
                    }

                    $orderedMeasurementPoints[] = $measurementPoint;
                    unset($measurementMap[$itemNameId]); // Remove from map to avoid duplicates
                }
            }
        }

        // Add any remaining measurement points that weren't specified in any group
        // These will be put at the end with original order from measurement_points array
        $remainingOrder = 9999; // High number to ensure they appear at the end
        foreach ($measurementMap as $point) {
            $point['group_name'] = null; // Not grouped
            $point['group_order'] = $remainingOrder++; // Increment to maintain their relative order
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
                        // Get current measurement point context for variables
                        $currentPointContext = [
                            'type' => $point['setup']['type'] ?? null,
                            'variables' => array_slice($point['variables'], 0, $varIndex), // Previous variables only
                            'pre_processing_formulas' => [] // Variables can't reference pre-processing formulas
                        ];

                        $formulaErrors = $this->validateSingleFormula(
                            $variable['formula'],
                            $availableIds,
                            "{$pointPrefix}.variable_{$varIndex}",
                            $currentPointContext
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // ✅ FIX: Validate & normalize but DON'T strip = prefix for storage
                            // Formula will be stored with = prefix for response consistency
                            try {
                                // Only validate and normalize, keep = prefix
                                FormulaHelper::validateFormulaFormat($variable['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($variable['formula']);
                                $variable['formula'] = $normalized; // Keep with =
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
                        // For pre-processing formulas, can reference current measurement item's raw data
                        // and other measurement items defined before
                        $preProcessingAvailableIds = array_merge($availableIds, [$point['setup']['name_id']]);

                        // Get current measurement point context (type, variables, previous formulas)
                        $currentPointContext = [
                            'type' => $point['setup']['type'] ?? null,
                            'variables' => $point['variables'] ?? [],
                            'pre_processing_formulas' => array_slice($point['pre_processing_formulas'], 0, $formulaIndex) // Previous formulas only
                        ];

                        $formulaErrors = $this->validateSingleFormula(
                            $formula['formula'],
                            $preProcessingAvailableIds,
                            "{$pointPrefix}.pre_processing_formula_{$formulaIndex}",
                            $currentPointContext
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // ✅ FIX: Validate & normalize but DON'T strip = prefix for storage
                            try {
                                FormulaHelper::validateFormulaFormat($formula['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($formula['formula']);
                                $formula['formula'] = $normalized; // Keep with =
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

                        // Get current measurement point context for joint formulas
                        // Joint formulas can reference all pre-processing formulas, variables, and previous joint formulas
                        $currentPointContext = [
                            'type' => $point['setup']['type'] ?? null,
                            'variables' => $point['variables'] ?? [],
                            'pre_processing_formulas' => $point['pre_processing_formulas'] ?? [],
                            'joint_formulas' => array_slice($point['evaluation_setting']['joint_setting']['formulas'], 0, $formulaIndex) // Previous joint formulas only
                        ];

                        $formulaErrors = $this->validateSingleFormula(
                            $formula['formula'],
                            $jointAvailableIds,
                            "{$pointPrefix}.joint_formula_{$formulaIndex}",
                            $currentPointContext
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // ✅ FIX: Validate & normalize but DON'T strip = prefix for storage
                            try {
                                FormulaHelper::validateFormulaFormat($formula['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($formula['formula']);
                                $formula['formula'] = $normalized; // Keep with =
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
     * 
     * @param string $formula The formula to validate
     * @param array $availableIds Available measurement item name_ids
     * @param string $errorPrefix Error message prefix
     * @param array|null $currentPointContext Context of current measurement point (type, variables, pre_processing_formulas)
     * @return array Validation errors
     */
    private function validateSingleFormula(string $formula, array $availableIds, string $errorPrefix, ?array $currentPointContext = null): array
    {
        $errors = [];

        // Validate format (must start with =)
        try {
            FormulaHelper::validateFormulaFormat($formula);
        } catch (\InvalidArgumentException $e) {
            $errors[$errorPrefix] = $e->getMessage();
            return $errors;
        }

        // Get all referenced identifiers from formula
        $referencedItems = FormulaHelper::extractMeasurementReferences($formula);

        // Build list of valid local identifiers (raw data variables, variables, previous formulas)
        $validLocalIdentifiers = [];

        if ($currentPointContext) {
            $type = $currentPointContext['type'] ?? null;

            // Add raw data variables based on type
            if ($type === 'SINGLE') {
                $validLocalIdentifiers[] = 'single_value';
            } elseif ($type === 'BEFORE_AFTER') {
                $validLocalIdentifiers[] = 'before';
                $validLocalIdentifiers[] = 'after';
            }

            // Add variables from current measurement point
            if (isset($currentPointContext['variables']) && is_array($currentPointContext['variables'])) {
                foreach ($currentPointContext['variables'] as $variable) {
                    if (isset($variable['name'])) {
                        $validLocalIdentifiers[] = $variable['name'];
                    }
                }
            }

            // Add previous pre-processing formula names
            if (isset($currentPointContext['pre_processing_formulas']) && is_array($currentPointContext['pre_processing_formulas'])) {
                foreach ($currentPointContext['pre_processing_formulas'] as $prevFormula) {
                    if (isset($prevFormula['name'])) {
                        $validLocalIdentifiers[] = $prevFormula['name'];
                    }
                }
            }

            // Add previous joint formula names
            if (isset($currentPointContext['joint_formulas']) && is_array($currentPointContext['joint_formulas'])) {
                foreach ($currentPointContext['joint_formulas'] as $prevJointFormula) {
                    if (isset($prevJointFormula['name'])) {
                        $validLocalIdentifiers[] = $prevJointFormula['name'];
                    }
                }
            }
        }

        // Filter out valid local identifiers and check remaining against available measurement items
        $missingDependencies = [];
        foreach ($referencedItems as $itemId) {
            // Skip if it's a valid local identifier
            if (in_array($itemId, $validLocalIdentifiers)) {
                continue;
            }

            // Check if it's an available measurement item
            if (!in_array($itemId, $availableIds)) {
                $missingDependencies[] = $itemId;
            }
        }

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

            // ✅ NEW RULE: Name must be lowercase, start with letter, can contain numbers and underscores
            // Format: ^[a-z][a-z0-9_]*$ (lowercase letter first, then lowercase letters, numbers, underscores)
            foreach ($allNames as $name) {
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                    $errors["measurement_point_{$pointIndex}"] = "Invalid name format: '{$name}'. Name must be lowercase, start with a letter (a-z), and can only contain lowercase letters, numbers, and underscores. No spaces or uppercase letters allowed. Example: 'avg_value', 'thickness_1', 'room_temp'";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate QUANTITATIVE measurements have required source and type
     * Note: source and type are optional for sample_amount = 0 (auto-calculated from formula)
     */
    private function validateQuantitativeRequirements(array $measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $index => $point) {
            $setup = $point['setup'] ?? [];
            $nature = $setup['nature'] ?? '';
            $sampleAmount = $setup['sample_amount'] ?? 1;

            // For QUANTITATIVE:
            // - If sample_amount > 0: source and type are REQUIRED
            // - If sample_amount = 0: source is NOT required (will be auto-calculated), but type is still required (must be SINGLE)
            if ($nature === 'QUANTITATIVE') {
                if ($sampleAmount > 0) {
                    // For sample_amount > 0, source and type are both required
                    if (!isset($setup['source']) || empty($setup['source'])) {
                        $errors["measurement_points.{$index}.setup.source"] = 'Source is required for QUANTITATIVE nature when sample_amount > 0';
                    }

                    if (!isset($setup['type']) || empty($setup['type'])) {
                        $errors["measurement_points.{$index}.setup.type"] = 'Type is required for QUANTITATIVE nature when sample_amount > 0';
                    }
                } else {
                    // For sample_amount = 0, source is NOT required, but type should still be present (will be validated elsewhere)
                    // We don't require source here because it will be auto-calculated from formula
                    // Type is still validated in validateMeasurementPoints to ensure it's SINGLE
                }
            }
        }

        return $errors;
    }

    /**
     * Validate QUALITATIVE measurement requirements
     * - sample_amount can be any number (1, 4, 5, 10, etc.)
     * - evaluation_type must be PER_SAMPLE (cannot be SKIP_CHECK or JOINT)
     * - rule_evaluation_setting is required (PER_SAMPLE always needs rules)
     */
    private function validateQualitativeRequirements(array $measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $index => $point) {
            $setup = $point['setup'] ?? [];
            $nature = $setup['nature'] ?? '';
            $sampleAmount = $setup['sample_amount'] ?? null;
            $evaluationType = $point['evaluation_type'] ?? null;
            $ruleEvaluation = $point['rule_evaluation_setting'] ?? null;

            if ($nature === 'QUALITATIVE') {
                // 1. sample_amount must be >= 1 for QUALITATIVE (can be 1, 4, 5, 10, etc.)
                if ($sampleAmount === null || $sampleAmount < 1) {
                    $errors["measurement_points.{$index}.setup.sample_amount"] = 'QUALITATIVE nature requires sample_amount to be at least 1';
                }

                // 2. evaluation_type must be PER_SAMPLE (cannot be SKIP_CHECK or JOINT)
                if ($evaluationType !== 'PER_SAMPLE') {
                    $errors["measurement_points.{$index}.evaluation_type"] = 'QUALITATIVE nature requires evaluation_type to be PER_SAMPLE (cannot be SKIP_CHECK or JOINT)';
                }

                // 3. rule_evaluation_setting is required for PER_SAMPLE (always needs rules)
                if ($evaluationType === 'PER_SAMPLE' && !$ruleEvaluation) {
                    $errors["measurement_points.{$index}.rule_evaluation_setting"] = 'rule_evaluation_setting is required for QUALITATIVE nature with PER_SAMPLE evaluation';
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

            // Validate INSTRUMENT source
            if (isset($setup['source']) && $setup['source'] === 'INSTRUMENT') {
                if (!isset($setup['source_instrument_id']) || empty($setup['source_instrument_id'])) {
                    $errors["measurement_point_{$pointIndex}"] = 'source_instrument_id is required when source is INSTRUMENT';
                } else {
                    // Validate that instrument exists (by ID, name, or model)
                    $instrumentId = $setup['source_instrument_id'];
                    $instrument = null;
                    
                    if (is_numeric($instrumentId)) {
                        $instrument = MeasurementInstrument::find($instrumentId);
                    } else {
                        $instrument = MeasurementInstrument::where('name', $instrumentId)
                            ->orWhere('model', $instrumentId)
                            ->first();
                    }
                    
                    if (!$instrument) {
                        $errors["measurement_point_{$pointIndex}"] = "Measurement instrument dengan ID/name/model '{$instrumentId}' tidak ditemukan";
                    } else {
                        // Store the actual ID for consistency
                        $setup['source_instrument_id'] = $instrument->id;
                    }
                }
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
            // ✅ FIX: QUALITATIVE now requires rule_evaluation_setting (because it must be PER_SAMPLE)
            if (isset($setup['nature'])) {
                if ($setup['nature'] === 'QUALITATIVE') {
                    // ✅ FIX: QUALITATIVE with PER_SAMPLE requires rule_evaluation_setting
                    // This validation is now handled in validateQualitativeRequirements()
                    // But we still validate qualitative_setting exists
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

    /**
     * Validate type-specific rules
     * - BEFORE_AFTER wajib ada pre_processing_formulas
     * - BEFORE_AFTER tidak bisa is_raw_data = true
     * - Jika tidak ada pre_processing_formulas, tidak bisa pilih formula di evaluation
     * - pre_processing_formula_name harus ada di pre_processing_formulas
     * - ✅ NEW: sample_amount = 0 tidak boleh ada pre_processing_formulas
     */
    private function validateTypeSpecificRules(array $measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $pointIndex => $point) {
            $setup = $point['setup'] ?? [];
            $type = $setup['type'] ?? null;
            $sampleAmount = $setup['sample_amount'] ?? 1;
            $preProcessingFormulas = $point['pre_processing_formulas'] ?? [];
            $evaluationType = $point['evaluation_type'] ?? '';
            $evaluationSetting = $point['evaluation_setting'] ?? [];

            // ✅ NEW: Rule for sample_amount = 0: tidak boleh ada pre_processing_formulas
            if ($sampleAmount === 0 && !empty($preProcessingFormulas)) {
                $errors["measurement_points.{$pointIndex}.pre_processing_formulas"] =
                    'Pre-processing formulas tidak boleh digunakan ketika sample_amount = 0';
            }

            // ✅ NEW: Rule for sample_amount = 0: Formula tidak boleh akses single_value, before, atau after
            if ($sampleAmount === 0 && $evaluationType === 'JOINT') {
                $jointSetting = $evaluationSetting['joint_setting'] ?? null;
                if ($jointSetting && isset($jointSetting['formulas']) && is_array($jointSetting['formulas'])) {
                    $rawValueIdentifiers = ['single_value', 'before', 'after'];
                    foreach ($jointSetting['formulas'] as $formulaIndex => $formula) {
                        if (isset($formula['formula'])) {
                            $formulaStr = $formula['formula'];
                            // Check if formula contains raw value identifiers
                            foreach ($rawValueIdentifiers as $rawId) {
                                // Check for direct reference: single_value, before, after
                                if (preg_match('/\b' . preg_quote($rawId, '/') . '\b/', $formulaStr)) {
                                    $errors["measurement_points.{$pointIndex}.evaluation_setting.joint_setting.formulas.{$formulaIndex}.formula"] =
                                        "Formula tidak boleh mengakses '{$rawId}' ketika sample_amount = 0. Gunakan cross-reference ke measurement items lain (contoh: avg(thickness_a))";
                                }
                            }
                        }
                    }
                }
            }

            // Rule 1: BEFORE_AFTER wajib ada pre_processing_formulas
            if ($type === 'BEFORE_AFTER') {
                if (empty($preProcessingFormulas) || !is_array($preProcessingFormulas)) {
                    $errors["measurement_points.{$pointIndex}.pre_processing_formulas"] =
                        'Pre-processing formulas wajib diisi untuk type BEFORE_AFTER';
                }
            }

            // Rule 2 & 3: Validasi evaluation_setting untuk PER_SAMPLE
            if ($evaluationType === 'PER_SAMPLE') {
                $perSampleSetting = $evaluationSetting['per_sample_setting'] ?? null;

                if ($perSampleSetting) {
                    $isRawData = $perSampleSetting['is_raw_data'] ?? false;
                    $formulaName = $perSampleSetting['pre_processing_formula_name'] ?? null;

                    // Rule 2: BEFORE_AFTER tidak bisa is_raw_data = true
                    if ($type === 'BEFORE_AFTER' && $isRawData === true) {
                        $errors["measurement_points.{$pointIndex}.evaluation_setting.per_sample_setting.is_raw_data"] =
                            'Type BEFORE_AFTER tidak bisa menggunakan raw data untuk evaluation, harus menggunakan pre-processing formula';
                    }

                    // Rule 3: Jika tidak ada pre_processing_formulas, tidak bisa pilih formula
                    if (empty($preProcessingFormulas) && !$isRawData && !empty($formulaName)) {
                        $errors["measurement_points.{$pointIndex}.evaluation_setting.per_sample_setting.pre_processing_formula_name"] =
                            'Tidak bisa menggunakan pre-processing formula karena tidak ada pre-processing formulas yang didefinisikan. Gunakan is_raw_data = true atau tambahkan pre-processing formulas';
                    }

                    // Rule 4: pre_processing_formula_name harus ada di pre_processing_formulas
                    if (!$isRawData && !empty($formulaName) && !empty($preProcessingFormulas)) {
                        $formulaNames = array_column($preProcessingFormulas, 'name');
                        if (!in_array($formulaName, $formulaNames)) {
                            $errors["measurement_points.{$pointIndex}.evaluation_setting.per_sample_setting.pre_processing_formula_name"] =
                                "Pre-processing formula '{$formulaName}' tidak ditemukan dalam pre_processing_formulas. Formula yang tersedia: " . implode(', ', $formulaNames);
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Normalize source_instrument_id: convert name/model string to ID if needed
     * ✅ FIX: Handle case where frontend sends instrument name/model instead of ID
     */
    private function normalizeInstrumentIds(array $measurementPoints): array
    {
        foreach ($measurementPoints as &$point) {
            if (isset($point['setup']['source']) && $point['setup']['source'] === 'INSTRUMENT') {
                $instrumentId = $point['setup']['source_instrument_id'] ?? null;
                
                if ($instrumentId && !is_numeric($instrumentId)) {
                    // Try to find instrument by name or model
                    $instrument = MeasurementInstrument::where('name', $instrumentId)
                        ->orWhere('model', $instrumentId)
                        ->first();
                    
                    if ($instrument) {
                        $point['setup']['source_instrument_id'] = $instrument->id;
                    } else {
                        // If not found, keep original value and let validation handle it
                        // This will be caught in validateMeasurementPoints
                    }
                }
            }
        }

        return $measurementPoints;
    }

    /**
     * ✅ NEW: Enhanced validation with structured error messages
     */
    private function validateProductEnhanced(array $basicInfo, array $measurementPoints, $category): array
    {
        $basicInfoErrors = [];
        $measurementPointErrors = [];

        // Validate basic info
        if (!isset($basicInfo['product_name']) || empty($basicInfo['product_name'])) {
            $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                BasicInfoErrorEnum::REQUIRED,
                'product_name',
                'Nama produk wajib diisi'
            );
        } elseif (!in_array($basicInfo['product_name'], $category->products)) {
            $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                BasicInfoErrorEnum::INVALID_PRODUCT_NAME,
                'product_name',
                "Nama produk \"{$basicInfo['product_name']}\" tidak valid untuk kategori \"{$category->name}\". " .
                "Nama yang tersedia: " . implode(', ', $category->products)
            );
        }

        if (isset($basicInfo['color']) && strlen($basicInfo['color']) > 50) {
            $basicInfoErrors[] = ValidationErrorHelper::createBasicInfoError(
                BasicInfoErrorEnum::TOO_LONG,
                'color',
                'Warna terlalu panjang (maksimal 50 karakter)'
            );
        }

        // Validate measurement points
        foreach ($measurementPoints as $index => $point) {
            $setup = $point['setup'] ?? [];
            $name = $setup['name'] ?? '';
            $nameId = $setup['name_id'] ?? '';

            // Validate setup.name
            if (empty($name)) {
                $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                    ['name_id' => $nameId, 'name' => $name],
                    MeasurementPointSectionEnum::SETUP,
                    'name',
                    MeasurementPointErrorEnum::REQUIRED,
                    "Nama measurement item wajib diisi"
                );
            }

            // Validate setup.name_id format
            if (!empty($nameId)) {
                $nameError = ValidationErrorHelper::validateNameFormat($nameId);
                if ($nameError) {
                    $errorMessage = ValidationErrorHelper::generateInvalidNameMessage($nameId, $nameError);
                    $code = match($nameError) {
                        'space' => MeasurementPointErrorEnum::CONTAINS_SPACE,
                        'uppercase' => MeasurementPointErrorEnum::UPPERCASE_NOT_ALLOWED,
                        'special_character' => MeasurementPointErrorEnum::SPECIAL_CHARACTER,
                        default => MeasurementPointErrorEnum::INVALID_NAME,
                    };
                    $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                        ['name_id' => $nameId, 'name' => $name],
                        MeasurementPointSectionEnum::SETUP,
                        'name_id',
                        $code,
                        "Measurement item \"{$name}\" - {$errorMessage}"
                    );
                }
            }

            // Validate variables
            if (isset($point['variables']) && is_array($point['variables'])) {
                foreach ($point['variables'] as $varIndex => $variable) {
                    $varName = $variable['name'] ?? '';
                    
                    if (!empty($varName)) {
                        $varNameError = ValidationErrorHelper::validateNameFormat($varName);
                        if ($varNameError) {
                            $errorMessage = ValidationErrorHelper::generateVariableErrorMessage($varName, $varNameError);
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $nameId, 'name' => $name],
                                MeasurementPointSectionEnum::VARIABLE,
                                $varName,
                                MeasurementPointErrorEnum::INVALID_NAME,
                                $errorMessage
                            );
                        }
                    }

                    // Validate FIXED variable value
                    if (($variable['type'] ?? '') === 'FIXED') {
                        if (!isset($variable['value']) || !is_numeric($variable['value'])) {
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $nameId, 'name' => $name],
                                MeasurementPointSectionEnum::VARIABLE,
                                $varName,
                                MeasurementPointErrorEnum::REQUIRED,
                                "Variable \"{$varName}\" bertipe FIXED harus memiliki nilai numerik"
                            );
                        }
                    }
                }
            }

            // Validate pre-processing formulas
            if (isset($point['pre_processing_formulas']) && is_array($point['pre_processing_formulas'])) {
                foreach ($point['pre_processing_formulas'] as $formulaIndex => $formula) {
                    $formulaName = $formula['name'] ?? '';
                    
                    if (!empty($formulaName)) {
                        $formulaNameError = ValidationErrorHelper::validateNameFormat($formulaName);
                        if ($formulaNameError) {
                            $errorMessage = ValidationErrorHelper::generateInvalidNameMessage($formulaName, $formulaNameError);
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $nameId, 'name' => $name],
                                MeasurementPointSectionEnum::PRE_PROCESSING_FORMULA,
                                $formulaName,
                                MeasurementPointErrorEnum::INVALID_NAME,
                                "Pre-processing formula \"{$formulaName}\" - {$errorMessage}"
                            );
                        }
                    }
                }
            }

            // Validate evaluation type
            $evaluationType = $point['evaluation_type'] ?? '';
            if (empty($evaluationType)) {
                $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                    ['name_id' => $nameId, 'name' => $name],
                    MeasurementPointSectionEnum::EVALUATION,
                    'evaluation_type',
                    MeasurementPointErrorEnum::REQUIRED,
                    "Measurement item \"{$name}\" harus memiliki tipe evaluasi (PER_SAMPLE, JOINT, atau SKIP_CHECK)"
                );
            }

            // Validate rule evaluation
            if (isset($point['rule_evaluation_setting'])) {
                $ruleEval = $point['rule_evaluation_setting'];
                
                if (empty($ruleEval['rule'])) {
                    $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                        ['name_id' => $nameId, 'name' => $name],
                        MeasurementPointSectionEnum::RULE_EVALUATION,
                        'rule',
                        MeasurementPointErrorEnum::REQUIRED,
                        "Measurement item \"{$name}\" harus memiliki rule evaluasi (MIN, MAX, atau BETWEEN)"
                    );
                }

                if (!isset($ruleEval['value']) || !is_numeric($ruleEval['value'])) {
                    $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                        ['name_id' => $nameId, 'name' => $name],
                        MeasurementPointSectionEnum::RULE_EVALUATION,
                        'value',
                        MeasurementPointErrorEnum::REQUIRED,
                        "Measurement item \"{$name}\" harus memiliki nilai rule yang valid"
                    );
                }

                if (($ruleEval['rule'] ?? '') === 'BETWEEN') {
                    if (!isset($ruleEval['tolerance_minus']) || !is_numeric($ruleEval['tolerance_minus'])) {
                        $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::RULE_EVALUATION,
                            'tolerance_minus',
                            MeasurementPointErrorEnum::INVALID_TOLERANCE,
                            "Measurement item \"{$name}\" dengan rule BETWEEN harus memiliki tolerance_minus yang valid"
                        );
                    }
                    if (!isset($ruleEval['tolerance_plus']) || !is_numeric($ruleEval['tolerance_plus'])) {
                        $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::RULE_EVALUATION,
                            'tolerance_plus',
                            MeasurementPointErrorEnum::INVALID_TOLERANCE,
                            "Measurement item \"{$name}\" dengan rule BETWEEN harus memiliki tolerance_plus yang valid"
                        );
                    }
                }
            }

            // ✅ NEW: Validate DERIVED source consistency
            if (($setup['source'] ?? '') === 'DERIVED') {
                $derivedFromId = $setup['source_derived_name_id'] ?? null;
                if ($derivedFromId) {
                    // Find source measurement point
                    $sourcePoint = null;
                    foreach ($measurementPoints as $prevIndex => $prevPoint) {
                        if ($prevIndex < $index && isset($prevPoint['setup']['name_id']) && $prevPoint['setup']['name_id'] === $derivedFromId) {
                            $sourcePoint = $prevPoint;
                            break;
                        }
                    }

                    if (!$sourcePoint) {
                        $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::SETUP,
                            'source_derived_name_id',
                            MeasurementPointErrorEnum::NOT_FOUND,
                            "Measurement item \"{$name}\" - Source measurement item \"{$derivedFromId}\" tidak ditemukan. Pastikan measurement item tersebut didefinisikan sebelumnya."
                        );
                    } else {
                        $sourceSetup = $sourcePoint['setup'] ?? [];
                        $sourceSampleAmount = $sourceSetup['sample_amount'] ?? null;
                        $sourceType = $sourceSetup['type'] ?? null;
                        $currentSampleAmount = $setup['sample_amount'] ?? null;
                        $currentType = $setup['type'] ?? null;

                        // Validate sample_amount match
                        if ($sourceSampleAmount !== null && $currentSampleAmount !== null && $sourceSampleAmount !== $currentSampleAmount) {
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $nameId, 'name' => $name],
                                MeasurementPointSectionEnum::SETUP,
                                'sample_amount',
                                MeasurementPointErrorEnum::LOGICAL_CONFLICT,
                                "Measurement item \"{$name}\" - sample_amount ({$currentSampleAmount}) harus sama dengan source measurement item \"{$derivedFromId}\" ({$sourceSampleAmount})"
                            );
                        }

                        // Validate type match
                        if ($sourceType !== null && $currentType !== null && $sourceType !== $currentType) {
                            $measurementPointErrors[] = ValidationErrorHelper::createMeasurementPointError(
                                ['name_id' => $nameId, 'name' => $name],
                                MeasurementPointSectionEnum::SETUP,
                                'type',
                                MeasurementPointErrorEnum::LOGICAL_CONFLICT,
                                "Measurement item \"{$name}\" - type ({$currentType}) harus sama dengan source measurement item \"{$derivedFromId}\" ({$sourceType})"
                            );
                        }
                    }
                }
            }
        }

        return [
            'basic_info' => $basicInfoErrors,
            'measurement_points' => $measurementPointErrors,
        ];
    }

    /**
     * ✅ NEW: Enhanced formula validation with structured error messages
     */
    private function validateAndProcessFormulasEnhanced(array &$measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $pointIndex => &$point) {
            $setup = $point['setup'] ?? [];
            $name = $setup['name'] ?? '';
            $nameId = $setup['name_id'] ?? '';

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
                    if (($variable['type'] ?? '') === 'FORMULA' && isset($variable['formula'])) {
                        $currentPointContext = [
                            'type' => $setup['type'] ?? null,
                            'variables' => array_slice($point['variables'], 0, $varIndex),
                            'pre_processing_formulas' => []
                        ];

                        $formulaErrors = $this->validateSingleFormulaEnhanced(
                            $variable['formula'],
                            $availableIds,
                            $currentPointContext,
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::VARIABLE,
                            $variable['name'] ?? 'unnamed'
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Normalize formula
                            try {
                                FormulaHelper::validateFormulaFormat($variable['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($variable['formula']);
                                $variable['formula'] = $normalized;
                            } catch (\InvalidArgumentException $e) {
                                $errors[] = ValidationErrorHelper::createMeasurementPointError(
                                    ['name_id' => $nameId, 'name' => $name],
                                    MeasurementPointSectionEnum::VARIABLE,
                                    $variable['name'] ?? 'unnamed',
                                    MeasurementPointErrorEnum::INVALID_FORMULA,
                                    "Variable \"{$variable['name']}\" - " . $e->getMessage()
                                );
                            }
                        }
                    }
                }
            }

            // Validate pre-processing formulas
            if (isset($point['pre_processing_formulas']) && is_array($point['pre_processing_formulas'])) {
                foreach ($point['pre_processing_formulas'] as $formulaIndex => &$formula) {
                    if (isset($formula['formula'])) {
                        $preProcessingAvailableIds = array_merge($availableIds, [$nameId]);
                        $currentPointContext = [
                            'type' => $setup['type'] ?? null,
                            'variables' => $point['variables'] ?? [],
                            'pre_processing_formulas' => array_slice($point['pre_processing_formulas'], 0, $formulaIndex)
                        ];

                        $formulaErrors = $this->validateSingleFormulaEnhanced(
                            $formula['formula'],
                            $preProcessingAvailableIds,
                            $currentPointContext,
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::PRE_PROCESSING_FORMULA,
                            $formula['name'] ?? 'unnamed'
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Normalize formula
                            try {
                                FormulaHelper::validateFormulaFormat($formula['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($formula['formula']);
                                $formula['formula'] = $normalized;
                            } catch (\InvalidArgumentException $e) {
                                $errors[] = ValidationErrorHelper::createMeasurementPointError(
                                    ['name_id' => $nameId, 'name' => $name],
                                    MeasurementPointSectionEnum::PRE_PROCESSING_FORMULA,
                                    $formula['name'] ?? 'unnamed',
                                    MeasurementPointErrorEnum::INVALID_FORMULA,
                                    "Pre-processing formula \"{$formula['name']}\" - " . $e->getMessage()
                                );
                            }
                        }
                    }
                }
            }

            // Validate joint setting formulas
            if (isset($point['evaluation_setting']['joint_setting']['formulas']) && is_array($point['evaluation_setting']['joint_setting']['formulas'])) {
                foreach ($point['evaluation_setting']['joint_setting']['formulas'] as $formulaIndex => &$formula) {
                    if (isset($formula['formula'])) {
                        $jointAvailableIds = array_merge($availableIds, [$nameId]);
                        $currentPointContext = [
                            'type' => $setup['type'] ?? null,
                            'variables' => $point['variables'] ?? [],
                            'pre_processing_formulas' => $point['pre_processing_formulas'] ?? [],
                            'joint_formulas' => array_slice($point['evaluation_setting']['joint_setting']['formulas'], 0, $formulaIndex)
                        ];

                        $formulaErrors = $this->validateSingleFormulaEnhanced(
                            $formula['formula'],
                            $jointAvailableIds,
                            $currentPointContext,
                            ['name_id' => $nameId, 'name' => $name],
                            MeasurementPointSectionEnum::JOINT_FORMULA,
                            $formula['name'] ?? 'unnamed'
                        );

                        if (!empty($formulaErrors)) {
                            $errors = array_merge($errors, $formulaErrors);
                        } else {
                            // Normalize formula
                            try {
                                FormulaHelper::validateFormulaFormat($formula['formula']);
                                $normalized = FormulaHelper::normalizeFunctionNames($formula['formula']);
                                $formula['formula'] = $normalized;
                            } catch (\InvalidArgumentException $e) {
                                $errors[] = ValidationErrorHelper::createMeasurementPointError(
                                    ['name_id' => $nameId, 'name' => $name],
                                    MeasurementPointSectionEnum::JOINT_FORMULA,
                                    $formula['name'] ?? 'unnamed',
                                    MeasurementPointErrorEnum::INVALID_FORMULA,
                                    "Joint formula \"{$formula['name']}\" - " . $e->getMessage()
                                );
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * ✅ NEW: Validate single formula with enhanced error messages
     */
    private function validateSingleFormulaEnhanced(
        string $formula,
        array $availableIds,
        ?array $currentPointContext,
        array $measurementItem,
        MeasurementPointSectionEnum $section,
        string $entityName
    ): array {
        $errors = [];

        // Validate format (must start with =)
        try {
            FormulaHelper::validateFormulaFormat($formula);
        } catch (\InvalidArgumentException $e) {
            $errors[] = ValidationErrorHelper::createMeasurementPointError(
                $measurementItem,
                $section,
                $entityName,
                MeasurementPointErrorEnum::INVALID_FORMULA,
                "{$section->value} \"{$entityName}\" tidak valid: Formula harus diawali dengan tanda '=' (contoh: =avg(thickness))"
            );
            return $errors;
        }

        // Get all referenced identifiers from formula
        $referencedItems = FormulaHelper::extractMeasurementReferences($formula);

        // Build list of valid local identifiers
        $validLocalIdentifiers = [];

        if ($currentPointContext) {
            $type = $currentPointContext['type'] ?? null;

            // Add raw data variables based on type
            if ($type === 'SINGLE') {
                $validLocalIdentifiers[] = 'single_value';
            } elseif ($type === 'BEFORE_AFTER') {
                $validLocalIdentifiers[] = 'before';
                $validLocalIdentifiers[] = 'after';
            }

            // Add variables
            if (isset($currentPointContext['variables']) && is_array($currentPointContext['variables'])) {
                foreach ($currentPointContext['variables'] as $variable) {
                    if (isset($variable['name'])) {
                        $validLocalIdentifiers[] = $variable['name'];
                    }
                }
            }

            // Add previous pre-processing formula names
            if (isset($currentPointContext['pre_processing_formulas']) && is_array($currentPointContext['pre_processing_formulas'])) {
                foreach ($currentPointContext['pre_processing_formulas'] as $prevFormula) {
                    if (isset($prevFormula['name'])) {
                        $validLocalIdentifiers[] = $prevFormula['name'];
                    }
                }
            }

            // Add previous joint formula names
            if (isset($currentPointContext['joint_formulas']) && is_array($currentPointContext['joint_formulas'])) {
                foreach ($currentPointContext['joint_formulas'] as $prevJointFormula) {
                    if (isset($prevJointFormula['name'])) {
                        $validLocalIdentifiers[] = $prevJointFormula['name'];
                    }
                }
            }
        }

        // Check for missing dependencies
        $missingDependencies = [];
        foreach ($referencedItems as $itemId) {
            // Skip if it's a valid local identifier
            if (in_array($itemId, $validLocalIdentifiers)) {
                continue;
            }

            // Check if it's an available measurement item
            if (!in_array($itemId, $availableIds)) {
                $missingDependencies[] = $itemId;
            }
        }

        if (!empty($missingDependencies)) {
            foreach ($missingDependencies as $missingItem) {
                $message = ValidationErrorHelper::generateInvalidFormulaMessage(
                    $entityName,
                    $formula,
                    $missingItem
                );
                $errors[] = ValidationErrorHelper::createMeasurementPointError(
                    $measurementItem,
                    $section,
                    $entityName,
                    MeasurementPointErrorEnum::MISSING_DEPENDENCY,
                    $message
                );
            }
        }

        return $errors;
    }
}
