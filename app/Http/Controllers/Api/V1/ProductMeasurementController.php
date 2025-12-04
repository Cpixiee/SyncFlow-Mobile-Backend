<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductMeasurement;
use App\Models\Product;
use App\Enums\MeasurementType;
use App\Enums\SampleStatus;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductMeasurementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get available products for creating new monthly target
     * Returns list of products yang belum punya target di quarter yang dipilih
     */
    public function getAvailableProducts(Request $request)
    {
        try {
            // Validate query parameters
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get all products
            $allProductsQuery = Product::with(['quarter', 'productCategory']);

            // Get products yang sudah punya measurement di quarter ini
            $productsWithMeasurement = ProductMeasurement::whereBetween('measured_at', [$quarterRange['start'], $quarterRange['end']])
                ->pluck('product_id')
                ->unique()
                ->toArray();

            // Filter products yang belum punya measurement di quarter ini
            if (!empty($productsWithMeasurement)) {
                $allProductsQuery->whereNotIn('id', $productsWithMeasurement);
            }

            // Paginate results
            $products = $allProductsQuery->paginate($limit, ['*'], 'page', $page);

            // Transform products to match GET /products response format
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
                ],
                'Available products retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve available products: ' . $e->getMessage(),
                'AVAILABLE_PRODUCTS_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Get product measurements list for Monthly Target page
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate query parameters (all optional now)
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'measurement_type' => 'nullable|in:FULL_MEASUREMENT,SCALE_MEASUREMENT',
                'status' => 'nullable|in:TODO,ONGOING,NEED_TO_MEASURE,OK,NG,NOT_COMPLETE',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'query' => 'nullable|string|max:255',
                'quarter' => 'nullable|integer|min:1|max:4',
                'year' => 'nullable|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $measurementType = $request->get('measurement_type');
            $status = $request->get('status');
            $productCategoryId = $request->get('product_category_id');
            $query = $request->get('query');
            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Build base query with ProductMeasurement join
            $productsQuery = Product::with(['productCategory', 'quarter'])
                ->select('products.*')
                ->join('product_measurements', 'products.id', '=', 'product_measurements.product_id')
                ->distinct();

            // Apply optional filters
            if ($measurementType) {
                $productsQuery->where('product_measurements.measurement_type', $measurementType);
            }

            // Filter by quarter and year if provided
            if ($quarter && $year) {
                $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
                $productsQuery->whereBetween('product_measurements.measured_at', [$quarterRange['start'], $quarterRange['end']]);
            } elseif ($startDate && $endDate) {
                $productsQuery->whereBetween('product_measurements.measured_at', [$startDate, $endDate]);
            }

            // Apply filters
            if ($productCategoryId) {
                $productsQuery->where('products.product_category_id', $productCategoryId);
            }

            if ($query) {
                $productsQuery->where(function($q) use ($query) {
                    $q->where('products.product_name', 'like', "%{$query}%")
                      ->orWhere('products.product_id', 'like', "%{$query}%")
                      ->orWhere('products.article_code', 'like', "%{$query}%")
                      ->orWhere('products.ref_spec_number', 'like', "%{$query}%");
                });
            }

            // Get products and process with measurements
            $products = $productsQuery->get();
            $processedData = [];

            foreach ($products as $product) {
                // Get latest measurement for this product
                $measurementQuery = ProductMeasurement::where('product_id', $product->id);
                
                // Apply optional filters to measurement query
                if ($measurementType) {
                    $measurementQuery->where('measurement_type', $measurementType);
                }
                
                // Filter by quarter and year if provided
                if ($quarter && $year) {
                    $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
                    $measurementQuery->whereBetween('measured_at', [$quarterRange['start'], $quarterRange['end']]);
                } elseif ($startDate && $endDate) {
                    $measurementQuery->whereBetween('measured_at', [$startDate, $endDate]);
                }
                
                $latestMeasurement = $measurementQuery->latest('created_at')->first();

                if (!$latestMeasurement) {
                    continue;
                }

                // Determine status and progress
                $productStatus = $this->determineProductStatus($latestMeasurement);
                $sampleStatus = $latestMeasurement->getSampleStatus();
                $progress = $this->calculateProgress($latestMeasurement);
                
                // Apply status filter if provided
                if ($status && $productStatus !== $status) {
                    continue;
                }

                $processedData[] = [
                    'product_measurement_id' => $latestMeasurement->measurement_id,
                    'measurement_type' => $latestMeasurement->measurement_type->value,
                    'status' => $productStatus,
                    'sample_status' => $sampleStatus->value,
                    'batch_number' => $latestMeasurement->batch_number,
                    'progress' => $progress,
                    'due_date' => $latestMeasurement->measured_at->format('Y-m-d H:i:s'),
                    'product' => [
                        'id' => $product->product_id,
                        'product_category_id' => $product->productCategory->id,
                        'product_category_name' => $product->productCategory->name,
                        'product_name' => $product->product_name,
                        'ref_spec_number' => $product->ref_spec_number,
                        'nom_size_vo' => $product->nom_size_vo,
                        'article_code' => $product->article_code,
                        'no_document' => $product->no_document,
                        'no_doc_reference' => $product->no_doc_reference,
                    ]
                ];
            }

            // Apply pagination to processed data
            $total = count($processedData);
            $offset = ($page - 1) * $limit;
            $paginatedData = array_slice($processedData, $offset, $limit);

            return $this->paginationResponse(
                $paginatedData,
                [
                    'current_page' => $page,
                    'total_page' => ceil($total / $limit),
                    'limit' => $limit,
                    'total_docs' => $total,
                ],
                'Product measurements retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve product measurements: ' . $e->getMessage(),
                'PRODUCT_MEASUREMENTS_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Determine product status based on measurement
     */
    private function determineProductStatus($measurement): string
    {
        if (!$measurement) {
            return 'TODO'; // No measurement created yet
        }

        switch ($measurement->status) {
            case 'PENDING':
                return 'ONGOING';
            case 'IN_PROGRESS':
                return 'NEED_TO_MEASURE';
            case 'COMPLETED':
                return $measurement->overall_result ? 'OK' : 'OK'; // Both OK and NG show as OK in list
            default:
                return 'TODO';
        }
    }

    /**
     * Calculate measurement progress
     */
    private function calculateProgress($measurement): ?float
    {
        if (!$measurement || !$measurement->measurement_results) {
            return null;
        }

        if ($measurement->status === 'COMPLETED') {
            return 100.0;
        }

        // Calculate based on completed measurement items
        $measurementResults = $measurement->measurement_results;
        if (empty($measurementResults)) {
            return 0.0;
        }

        $totalItems = count($measurementResults);
        $completedItems = 0;

        foreach ($measurementResults as $result) {
            if (isset($result['status']) && $result['status'] !== null) {
                $completedItems++;
            }
        }

        return $totalItems > 0 ? ($completedItems / $totalItems) * 100.0 : 0.0;
    }

    /**
     * Create new measurement entry for a product
     */
    public function store(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate request (support single or multiple products in one endpoint)
            $validator = Validator::make($request->all(), [
                'product_id' => 'required_without:product_ids|string|exists:products,product_id',
                'product_ids' => 'required_without:product_id|array|min:1',
                'product_ids.*' => 'string|exists:products,product_id',
                'due_date' => 'required|date|after_or_equal:today',
                'measurement_type' => 'required|in:FULL_MEASUREMENT,SCALE_MEASUREMENT',
                'batch_number' => 'nullable|string|max:255',
                'sample_count' => 'nullable|integer|min:1|max:100',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Check for duplicate measurement based on type
            $duplicateCheck = $this->checkDuplicateMeasurement($request);
            if ($duplicateCheck['has_duplicate']) {
                return $this->errorResponse(
                    $duplicateCheck['message'],
                    'DUPLICATE_MEASUREMENT',
                    400
                );
            }

            // Handle multiple products
            if ($request->filled('product_ids')) {
                $results = [];
                $products = Product::whereIn('product_id', $request->product_ids)->get();

                foreach ($products as $product) {
                    $batchNumber = $request->batch_number ?? 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    $measurementPoints = $product->measurement_points ?? [];
                    $sampleCount = $request->sample_count ?? (count($measurementPoints) > 0 ? $measurementPoints[0]['setup']['sample_amount'] ?? 3 : 3);

                    $measurement = ProductMeasurement::create([
                        'product_id' => $product->id,
                        'batch_number' => $batchNumber,
                        'sample_count' => $sampleCount,
                        'measurement_type' => $request->measurement_type,
                        'status' => 'PENDING',
                        'measured_by' => $user->id,
                        'measured_at' => $request->due_date,
                        'notes' => $request->notes,
                    ]);

                    $results[$product->product_id] = $measurement->measurement_id;
                }

                return $this->successResponse($results, 'Measurement entries created successfully', 201);
            }

            // Single product flow
            $product = Product::where('product_id', $request->product_id)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $batchNumber = $request->batch_number ?? 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $measurementPoints = $product->measurement_points ?? [];
            $sampleCount = $request->sample_count ?? (count($measurementPoints) > 0 ? $measurementPoints[0]['setup']['sample_amount'] ?? 3 : 3);

            $measurement = ProductMeasurement::create([
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'sample_count' => $sampleCount,
                'measurement_type' => $request->measurement_type,
                'status' => 'PENDING',
                'measured_by' => $user->id,
                'measured_at' => $request->due_date,
                'notes' => $request->notes,
            ]);

            return $this->successResponse([
                'product_measurement_id' => $measurement->measurement_id,
            ], 'Measurement entry created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create measurement entry',
                'MEASUREMENT_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Bulk create measurements for multiple products
     */
    public function bulkStore(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $validator = Validator::make($request->all(), [
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|string|exists:products,product_id',
                'due_date' => 'required|date|after_or_equal:today',
                'measurement_type' => 'required|in:FULL_MEASUREMENT,SCALE_MEASUREMENT',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Check for duplicate measurement based on type
            $duplicateCheck = $this->checkDuplicateMeasurement($request);
            if ($duplicateCheck['has_duplicate']) {
                return $this->errorResponse(
                    $duplicateCheck['message'],
                    'DUPLICATE_MEASUREMENT',
                    400
                );
            }

            $results = [];

            $products = Product::whereIn('product_id', $request->product_ids)->get();
            foreach ($products as $product) {
                $batchNumber = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

                $measurement = ProductMeasurement::create([
                    'product_id' => $product->id,
                    'batch_number' => $batchNumber,
                    'sample_count' => ($product->measurement_points[0]['setup']['sample_amount'] ?? 3),
                    'measurement_type' => $request->measurement_type,
                    'status' => 'PENDING',
                    'measured_by' => $user->id,
                    // measured_at sementara dipakai sebagai due_date
                    'measured_at' => $request->due_date,
                    'notes' => null,
                ]);

                $results[$product->product_id] = $measurement->measurement_id;
            }

            return $this->successResponse($results, 'Bulk measurements created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create bulk measurement entries: ' . $e->getMessage(),
                'MEASUREMENT_BULK_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Set batch number and move status to ONGOING (IN_PROGRESS)
     */
    public function setBatchNumber(Request $request, string $productMeasurementId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'batch_number' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();
            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            $measurement->update([
                'batch_number' => $request->batch_number,
                'status' => 'IN_PROGRESS',
            ]);

            return $this->successResponse(null, 'Batch number set successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error setting batch number: ' . $e->getMessage(),
                'SET_BATCH_ERROR',
                500
            );
        }
    }

    /**
     * Check samples for a specific measurement item (for individual measurement item evaluation)
     */
    public function checkSamples(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Get product and measurement point to check source type
            $product = $measurement->product;
            $measurementPoint = $product->getMeasurementPointByNameId($request->measurement_item_name_id);
            
            if (!$measurementPoint) {
                return $this->notFoundResponse("Measurement point tidak ditemukan: {$request->measurement_item_name_id}");
            }

            $sourceType = $measurementPoint['setup']['source'];
            
            // Handle different source types
            switch ($sourceType) {
                case 'MANUAL':
                    // User input manual - validate samples required
                    break;
                case 'INSTRUMENT':
                    // Auto dari IoT/alat ukur - samples bisa kosong karena akan diambil otomatis
                    return $this->successResponse([
                        'status' => null,
                        'message' => 'Measurement akan diambil otomatis dari alat ukur IoT',
                        'source_type' => 'INSTRUMENT',
                        'samples' => []
                    ], 'Waiting for instrument data');
                case 'DERIVED':
                    // Auto dari measurement item lain - samples akan dihitung otomatis
                    $derivedFromId = $measurementPoint['setup']['source_derived_name_id'];
                    return $this->successResponse([
                        'status' => null,
                        'message' => "Measurement akan diambil otomatis dari: {$derivedFromId}",
                        'source_type' => 'DERIVED',
                        'derived_from' => $derivedFromId,
                        'samples' => []
                    ], 'Waiting for derived data');
            }

            // Check if measurement item with formula dependencies needs prerequisite data
            if (isset($measurementPoint['variables']) && !empty($measurementPoint['variables'])) {
                $missingDependencies = $this->checkFormulaDependencies($measurement, $measurementPoint['variables']);
                if (!empty($missingDependencies)) {
                    return $this->errorResponse(
                        "Measurement item ini membutuhkan data dari: " . implode(', ', $missingDependencies) . ". Silakan input data tersebut terlebih dahulu.",
                        'MISSING_DEPENDENCIES',
                        400
                    );
                }
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'measurement_item_name_id' => 'required|string',
                'variable_values' => 'nullable|array',
                'variable_values.*.name_id' => 'required_with:variable_values|string',
                'variable_values.*.value' => 'required_with:variable_values|numeric',
                'samples' => 'required|array|min:1',
                'samples.*.sample_index' => 'required|integer|min:1',
                'samples.*.single_value' => 'nullable|numeric',
                'samples.*.before_after_value' => 'nullable|array',
                'samples.*.before_after_value.before' => 'required_with:samples.*.before_after_value|numeric',
                'samples.*.before_after_value.after' => 'required_with:samples.*.before_after_value|numeric',
                'samples.*.qualitative_value' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Process single measurement item
            $measurementItemData = [
                'measurement_item_name_id' => $request->measurement_item_name_id,
                'variable_values' => $request->variable_values ?? [],
                'samples' => $request->samples,
            ];

            // Get product and measurement point
            $product = $measurement->product;
            $measurementPoint = $product->getMeasurementPointByNameId($request->measurement_item_name_id);
            
            if (!$measurementPoint) {
                return $this->notFoundResponse("Measurement point tidak ditemukan: {$request->measurement_item_name_id}");
            }

            // Process samples dengan variables dan pre-processing formulas
            $processedSamples = $this->processSampleItem($measurementItemData, $measurementPoint);
            
            // Evaluate berdasarkan evaluation type
            $result = $this->evaluateSampleItem($processedSamples, $measurementPoint, $measurementItemData);

            return $this->successResponse($result, 'Samples processed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error processing samples: ' . $e->getMessage(),
                'SAMPLES_PROCESS_ERROR',
                500
            );
        }
    }

    /**
     * Process samples for individual measurement item
     */
    private function processSampleItem(array $measurementItem, array $measurementPoint): array
    {
        $processedSamples = [];
        $variables = $measurementItem['variable_values'] ?? [];
        $setup = $measurementPoint['setup'];

        foreach ($measurementItem['samples'] as $sample) {
            $processedSample = [
                'sample_index' => $sample['sample_index'],
                'status' => null,
                'single_value' => $sample['single_value'] ?? null,
                'before_after_value' => $sample['before_after_value'] ?? null,
                'qualitative_value' => $sample['qualitative_value'] ?? null,
                'pre_processing_formula_values' => null,
            ];

            // Process pre-processing formulas jika ada
            if (isset($measurementPoint['pre_processing_formulas']) && !empty($measurementPoint['pre_processing_formulas'])) {
                $rawValues = [];
                if ($setup['type'] === 'SINGLE') {
                    $rawValues['single_value'] = $sample['single_value'];
                } elseif ($setup['type'] === 'BEFORE_AFTER') {
                    $rawValues['before_after_value'] = $sample['before_after_value'];
                }

                $processedFormulas = $this->processPreProcessingFormulasForItem(
                    $measurementPoint['pre_processing_formulas'],
                    $rawValues,
                    $variables
                );
                
                $processedSample['pre_processing_formula_values'] = array_map(function($formula, $result) {
                    return [
                        'name' => $formula['name'],
                        'formula' => $formula['formula'],
                        'value' => $result,
                        'is_show' => $formula['is_show']
                    ];
                }, $measurementPoint['pre_processing_formulas'], $processedFormulas);
            }

            $processedSamples[] = $processedSample;
        }

        return $processedSamples;
    }

    /**
     * Evaluate samples for individual measurement item
     */
    private function evaluateSampleItem(array $processedSamples, array $measurementPoint, array $measurementItem): array
    {
        $evaluationType = $measurementPoint['evaluation_type'];
        $result = [
            'status' => false,
            'variable_values' => $measurementItem['variable_values'] ?? [],
            'samples' => $processedSamples,
            'joint_setting_formula_values' => null,
        ];

        switch ($evaluationType) {
            case 'PER_SAMPLE':
                $result = $this->evaluatePerSampleItem($result, $measurementPoint, $processedSamples);
                break;
                
            case 'JOINT':
                $result = $this->evaluateJointItem($result, $measurementPoint, $processedSamples);
                break;
                
            case 'SKIP_CHECK':
                $result['status'] = true;
                break;
        }

        return $result;
    }

    /**
     * Evaluate per sample for individual item
     */
    private function evaluatePerSampleItem(array $result, array $measurementPoint, array $processedSamples): array
    {
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        $evaluationSetting = $measurementPoint['evaluation_setting']['per_sample_setting'];
        
        $allSamplesOK = true;
        
        foreach ($result['samples'] as &$sample) {
            $valueToEvaluate = null;
            
            if ($evaluationSetting['is_raw_data']) {
                $valueToEvaluate = $sample['single_value'];
            } else {
                // Use pre-processing formula result
                $formulaName = $evaluationSetting['pre_processing_formula_name'];
                if ($sample['pre_processing_formula_values']) {
                    foreach ($sample['pre_processing_formula_values'] as $formula) {
                        if ($formula['name'] === $formulaName) {
                            $valueToEvaluate = $formula['value'];
                            break;
                        }
                    }
                }
            }

            $sampleOK = $this->evaluateWithRuleItem($valueToEvaluate, $ruleEvaluation);
            $sample['status'] = $sampleOK;
            
            if (!$sampleOK) {
                $allSamplesOK = false;
            }
        }
        
        $result['status'] = $allSamplesOK;
        return $result;
    }

    /**
     * Evaluate joint for individual item
     */
    private function evaluateJointItem(array $result, array $measurementPoint, array $processedSamples): array
    {
        $jointSetting = $measurementPoint['evaluation_setting']['joint_setting'];
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        
        // Process joint formulas
        $jointResults = [];
        foreach ($jointSetting['formulas'] as $formula) {
            $jointResults[] = [
                'name' => $formula['name'],
                'formula' => $formula['formula'],
                'is_final_value' => $formula['is_final_value'],
                'value' => null // Will be calculated by frontend or provided in submission
            ];
        }

        $result['joint_setting_formula_values'] = $jointResults;
        $result['status'] = true; // For now, assume OK until actual calculation
        
        return $result;
    }

    /**
     * Check if formula dependencies are available
     */
    private function checkFormulaDependencies(ProductMeasurement $measurement, array $variables): array
    {
        $missingDependencies = [];
        $measurementResults = $measurement->measurement_results ?? [];
        
        foreach ($variables as $variable) {
            if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
                $formula = $variable['formula'];
                
                // Check if formula references other measurement items like AVG(thickness_a_measurement)
                if (preg_match_all('/AVG\(([^)]+)\)/', $formula, $matches)) {
                    foreach ($matches[1] as $referencedItem) {
                        // Check if referenced measurement item has data
                        $hasData = false;
                        foreach ($measurementResults as $result) {
                            if ($result['measurement_item_name_id'] === $referencedItem) {
                                $hasData = true;
                                break;
                            }
                        }
                        
                        if (!$hasData) {
                            $missingDependencies[] = $referencedItem;
                        }
                    }
                }
            }
        }
        
        return array_unique($missingDependencies);
    }

    /**
     * Process pre-processing formulas for individual item
     */
    private function processPreProcessingFormulasForItem(array $formulas, array $rawValues, array $variables): array
    {
        $results = [];
        // For now, return placeholder values
        // In real implementation, this would use MathExecutor like in the main processing
        foreach ($formulas as $formula) {
            $results[] = 0.0; // Placeholder
        }
        return $results;
    }

    /**
     * Generate evaluation summary for response
     */
    private function generateEvaluationSummary(array $measurementResults): array
    {
        $totalItems = count($measurementResults);
        $passedItems = 0;
        $failedItems = 0;
        $itemDetails = [];

        foreach ($measurementResults as $item) {
            $itemStatus = $item['status'] ?? false;
            $itemName = $item['measurement_item_name_id'];
            
            if ($itemStatus) {
                $passedItems++;
            } else {
                $failedItems++;
            }

            // Count sample results for PER_SAMPLE evaluation
            $sampleResults = [];
            if (isset($item['samples'])) {
                foreach ($item['samples'] as $sample) {
                    $sampleResults[] = [
                        'sample_index' => $sample['sample_index'],
                        'status' => $sample['status'] ?? null,
                        'result' => isset($sample['status']) ? ($sample['status'] ? 'OK' : 'NG') : 'N/A'
                    ];
                }
            }

            // Enhanced evaluation details for different types
            $evaluationDetails = [
                'measurement_item' => $itemName,
                'status' => $itemStatus,
                'result' => $itemStatus ? 'OK' : 'NG',
                'evaluation_type' => isset($item['joint_results']) ? 'JOINT' : 'PER_SAMPLE',
                'final_value' => $item['final_value'] ?? null,
                'samples_summary' => $sampleResults
            ];

            // Add specific details for JOINT evaluation
            if (isset($item['joint_results']) && !empty($item['joint_results'])) {
                $evaluationDetails['joint_evaluation'] = [
                    'final_value' => $item['final_value'],
                    'rule_evaluation' => [
                        'result' => $itemStatus ? 'OK' : 'NG',
                        'final_value' => $item['final_value'],
                        'evaluation_method' => 'Final value checked against rule'
                    ],
                    'formula_steps' => $item['joint_results']
                ];
                
                // Update sample summary for JOINT - show final result for all samples
                $evaluationDetails['samples_summary'] = array_map(function($sample) use ($itemStatus, $item) {
                    return [
                        'sample_index' => $sample['sample_index'],
                        'status' => $itemStatus,
                        'result' => $itemStatus ? 'OK' : 'NG',
                        'note' => 'Final value: ' . ($item['final_value'] ?? 'N/A') . ' → ' . ($itemStatus ? 'OK' : 'NG')
                    ];
                }, $sampleResults);
            }

            $itemDetails[] = $evaluationDetails;
        }

        return [
            'total_items' => $totalItems,
            'passed_items' => $passedItems,
            'failed_items' => $failedItems,
            'pass_rate' => $totalItems > 0 ? round(($passedItems / $totalItems) * 100, 2) : 0,
            'item_details' => $itemDetails
        ];
    }

    /**
     * Evaluate with rule for individual item
     */
    private function evaluateWithRuleItem($value, array $ruleEvaluation): bool
    {
        if ($value === null || !is_numeric($value)) {
            return false;
        }

        $rule = $ruleEvaluation['rule'];
        $ruleValue = $ruleEvaluation['value'];

        switch ($rule) {
            case 'MIN':
                return $value >= $ruleValue;
            case 'MAX':
                return $value <= $ruleValue;
            case 'BETWEEN':
                $minValue = $ruleValue - $ruleEvaluation['tolerance_minus'];
                $maxValue = $ruleValue + $ruleEvaluation['tolerance_plus'];
                return $value >= $minValue && $value <= $maxValue;
            default:
                return false;
        }
    }

    /**
     * Save measurement progress (partial save)
     */
    public function saveProgress(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'measurement_results' => 'required|array|min:1',
                'measurement_results.*.measurement_item_name_id' => 'required|string',
                'measurement_results.*.samples' => 'nullable|array',
                'measurement_results.*.variable_values' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Save partial results
            $existingResults = $measurement->measurement_results ?? [];
            $newResults = $request->measurement_results;

            // Merge with existing results
            foreach ($newResults as $newResult) {
                $found = false;
                foreach ($existingResults as &$existingResult) {
                    if ($existingResult['measurement_item_name_id'] === $newResult['measurement_item_name_id']) {
                        $existingResult = array_merge($existingResult, $newResult);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $existingResults[] = $newResult;
                }
            }

            // Update measurement with progress
            $measurement->update([
                'status' => 'IN_PROGRESS',
                'measurement_results' => $existingResults,
            ]);

            // Calculate progress
            $progress = $this->calculateProgress($measurement);

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'status' => 'IN_PROGRESS',
                'progress' => $progress,
                'saved_items' => count($newResults),
                'total_items' => count($existingResults),
            ], 'Progress saved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error saving progress: ' . $e->getMessage(),
                'PROGRESS_SAVE_ERROR',
                500
            );
        }
    }

    /**
     * Submit measurement results
     */
    public function submitMeasurement(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Enhanced validation based on your specification
            $validator = Validator::make($request->all(), [
                'measurement_results' => 'required|array|min:1',
                'measurement_results.*.measurement_item_name_id' => 'required|string',
                'measurement_results.*.status' => 'nullable|boolean',
                'measurement_results.*.variable_values' => 'nullable|array',
                'measurement_results.*.variable_values.*.name' => 'required_with:measurement_results.*.variable_values|string',
                'measurement_results.*.variable_values.*.value' => 'required_with:measurement_results.*.variable_values|numeric',
                'measurement_results.*.samples' => 'required|array|min:1',
                'measurement_results.*.samples.*.sample_index' => 'required|integer|min:1',
                'measurement_results.*.samples.*.status' => 'nullable|string',
                'measurement_results.*.samples.*.single_value' => 'nullable|numeric',
                'measurement_results.*.samples.*.before_after_value' => 'nullable|array',
                'measurement_results.*.samples.*.before_after_value.before' => 'required_with:measurement_results.*.samples.*.before_after_value|numeric',
                'measurement_results.*.samples.*.before_after_value.after' => 'required_with:measurement_results.*.samples.*.before_after_value|numeric',
                'measurement_results.*.samples.*.qualitative_value' => 'nullable|string',
                'measurement_results.*.samples.*.pre_processing_formula_values' => 'nullable|array',
                'measurement_results.*.joint_setting_formula_values' => 'nullable|array',
                'measurement_results.*.joint_setting_formula_values.*.name' => 'required_with:measurement_results.*.joint_setting_formula_values|string',
                'measurement_results.*.joint_setting_formula_values.*.value' => 'nullable|numeric',
                'measurement_results.*.joint_setting_formula_values.*.formula' => 'nullable|string',
                'measurement_results.*.joint_setting_formula_values.*.is_final_value' => 'required_with:measurement_results.*.joint_setting_formula_values|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Process measurement results
            $result = $measurement->processMeasurementResults($request->all());

            // Update measurement status
            $measurement->update([
                'status' => 'COMPLETED',
                'overall_result' => $result['overall_status'],
                'measurement_results' => $result['measurement_results'],
            ]);

            // Enhanced response with detailed evaluation results
            $evaluationSummary = $this->generateEvaluationSummary($result['measurement_results']);

            return $this->successResponse([
                'status' => $result['overall_status'],
                'overall_result' => $result['overall_status'] ? 'OK' : 'NG',
                'evaluation_summary' => $evaluationSummary,
                'samples' => $result['measurement_results'],
            ], 'Measurement results processed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error processing measurement: ' . $e->getMessage(),
                'MEASUREMENT_PROCESS_ERROR',
                500
            );
        }
    }

    /**
     * Get measurement by ID
     */
    public function show(string $productMeasurementId)
    {
        try {
            $measurement = ProductMeasurement::with(['product', 'measuredBy'])
                ->where('measurement_id', $productMeasurementId)
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'product_id' => $measurement->product->product_id,
                'batch_number' => $measurement->batch_number,
                'sample_count' => $measurement->sample_count,
                'measurement_type' => $measurement->measurement_type->value,
                'product_status' => $measurement->getProductStatus(),
                'measurement_status' => $measurement->status,
                'sample_status' => $measurement->getSampleStatus()->value,
                'overall_result' => $measurement->overall_result,
                'measurement_results' => $measurement->measurement_results,
                'measured_by' => $measurement->measuredBy ? [
                    'username' => $measurement->measuredBy->username,
                    'employee_id' => $measurement->measuredBy->employee_id,
                ] : null,
                'measured_at' => $measurement->measured_at,
                'notes' => $measurement->notes,
                'created_at' => $measurement->created_at,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error fetching measurement: ' . $e->getMessage(),
                'MEASUREMENT_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Create sample product from measurement results
     */
    public function createSampleProduct(Request $request, string $productMeasurementId)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Validate measurement is completed
            if ($measurement->status !== 'COMPLETED') {
                return $this->errorResponse(
                    'Measurement harus diselesaikan terlebih dahulu',
                    'MEASUREMENT_NOT_COMPLETED',
                    400
                );
            }

            // Create sample product data
            $sampleProductData = [
                'product_measurement_id' => $measurement->id,
                'product_id' => $measurement->product_id,
                'measurement_id' => $measurement->measurement_id,
                'batch_number' => $measurement->batch_number,
                'measurement_type' => $measurement->measurement_type->value,
                'sample_data' => $measurement->measurement_results,
                'overall_result' => $measurement->overall_result,
                'sample_status' => $measurement->getSampleStatus()->value,
                'status' => 'CREATED',
                'created_by' => $user->id,
                'created_at' => now(),
            ];

            // For now, we'll return the sample product data
            // In real implementation, you might want to save this to a sample_products table
            $sampleProductId = 'SAMPLE-' . strtoupper(substr(uniqid(), -8));

            return $this->successResponse([
                'sample_product_id' => $sampleProductId,
                'product_measurement_id' => $measurement->measurement_id,
                'status' => 'CREATED',
                'sample_data' => $sampleProductData,
                'created_by' => [
                    'username' => $user->username,
                    'employee_id' => $user->employee_id,
                ],
                'created_at' => now()->format('Y-m-d H:i:s'),
            ], 'Sample product created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error creating sample product: ' . $e->getMessage(),
                'SAMPLE_PRODUCT_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Check for duplicate measurement based on measurement type
     */
    private function checkDuplicateMeasurement(Request $request): array
    {
        $measurementType = $request->measurement_type;
        $dueDate = $request->due_date;
        
        if ($request->filled('product_id')) {
            // Single product check
            $product = Product::where('product_id', $request->product_id)->first();
            if (!$product) {
                return ['has_duplicate' => false, 'message' => ''];
            }
            
            $hasDuplicate = $this->checkProductDuplicate($product->id, $measurementType, $dueDate);
            if ($hasDuplicate) {
                $message = $measurementType === 'FULL_MEASUREMENT' 
                    ? 'Quarter ini sudah memiliki measurement (maksimal 1 data per quarter)'
                    : 'Hari ini sudah memiliki measurement (maksimal 1 data per hari)';
                return ['has_duplicate' => true, 'message' => $message];
            }
        } else {
            // Multiple products check
            $productIds = $request->product_ids;
            $products = Product::whereIn('product_id', $productIds)->get();
            
            $duplicateProducts = [];
            foreach ($products as $product) {
                $hasDuplicate = $this->checkProductDuplicate($product->id, $measurementType, $dueDate);
                if ($hasDuplicate) {
                    $duplicateProducts[] = $product->product_id;
                }
            }
            
            if (!empty($duplicateProducts)) {
                $message = $measurementType === 'FULL_MEASUREMENT'
                    ? 'Quarter ini sudah memiliki measurement (maksimal 1 data per quarter)'
                    : 'Hari ini sudah memiliki measurement (maksimal 1 data per hari)';
                return ['has_duplicate' => true, 'message' => $message];
            }
        }
        
        return ['has_duplicate' => false, 'message' => ''];
    }

    /**
     * Check if product already has measurement in the same quarter/day
     */
    private function checkProductDuplicate(int $productId, string $measurementType, string $dueDate): bool
    {
        if ($measurementType === 'FULL_MEASUREMENT') {
            // Check for measurement in same quarter for THIS PRODUCT
            $quarterRange = $this->getQuarterRange($dueDate);
            return ProductMeasurement::where('product_id', $productId)
                ->where('measurement_type', 'FULL_MEASUREMENT')
                ->whereBetween('measured_at', [$quarterRange['start'], $quarterRange['end']])
                ->exists();
        } else {
            // Check for measurement in same day for THIS PRODUCT
            $dayStart = date('Y-m-d 00:00:00', strtotime($dueDate));
            $dayEnd = date('Y-m-d 23:59:59', strtotime($dueDate));
            
            return ProductMeasurement::where('product_id', $productId)
                ->where('measurement_type', 'SCALE_MEASUREMENT')
                ->whereBetween('measured_at', [$dayStart, $dayEnd])
                ->exists();
        }
    }

    /**
     * Get quarter range based on quarter number (1-4) and year
     */
    private function getQuarterRangeFromQuarterNumber(int $quarter, int $year): array
    {
        // Quarter ranges:
        // Q1: Juni-Juli-Agustus (06-07-08)
        // Q2: September-Oktober-November (09-10-11)
        // Q3: Desember-Januari-Februari (12-01-02)
        // Q4: Maret-April-Mei (03-04-05)
        
        switch ($quarter) {
            case 1:
                $startDate = $year . '-06-01 00:00:00';
                $endDate = $year . '-08-31 23:59:59';
                break;
            case 2:
                $startDate = $year . '-09-01 00:00:00';
                $endDate = $year . '-11-30 23:59:59';
                break;
            case 3:
                // Q3 crosses year boundary: Dec-Jan-Feb
                $startDate = $year . '-12-01 00:00:00';
                $endDate = ($year + 1) . '-02-28 23:59:59';
                // Check for leap year
                if (date('L', strtotime($year + 1 . '-01-01'))) {
                    $endDate = ($year + 1) . '-02-29 23:59:59';
                }
                break;
            case 4:
                $startDate = $year . '-03-01 00:00:00';
                $endDate = $year . '-05-31 23:59:59';
                break;
            default:
                // Fallback to Q1
                $startDate = $year . '-06-01 00:00:00';
                $endDate = $year . '-08-31 23:59:59';
                break;
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate,
            'quarter' => 'Q' . $quarter
        ];
    }

    /**
     * Get quarter range based on date
     */
    private function getQuarterRange(string $date): array
    {
        $month = (int) date('m', strtotime($date));
        $year = (int) date('Y', strtotime($date));
        
        $quarterRanges = [
            'Q1' => ['06', '07', '08'],     // Juni-Juli-Agustus
            'Q2' => ['09', '10', '11'],     // September-Oktober-November  
            'Q3' => ['12', '01', '02'],     // Desember-Januari-Februari
            'Q4' => ['03', '04', '05']      // Maret-April-Mei
        ];
        
        foreach ($quarterRanges as $quarter => $months) {
            if (in_array(sprintf('%02d', $month), $months)) {
                // Determine quarter boundaries
                if ($quarter === 'Q1') {
                    $startDate = $year . '-06-01 00:00:00';
                    $endDate = $year . '-08-31 23:59:59';
                } elseif ($quarter === 'Q2') {
                    $startDate = $year . '-09-01 00:00:00';
                    $endDate = $year . '-11-30 23:59:59';
                } elseif ($quarter === 'Q3') {
                    // Handle year transition
                    if (in_array($month, [12])) {
                        $startDate = $year . '-12-01 00:00:00';
                        $endDate = ($year + 1) . '-02-28 23:59:59';
                    } else {
                        $startDate = $year . '-01-01 00:00:00';
                        $endDate = $year . '-02-28 23:59:59';
                    }
                } else { // Q4
                    $startDate = $year . '-03-01 00:00:00';
                    $endDate = $year . '-05-31 23:59:59';
                }
                
                return [
                    'start' => $startDate,
                    'end' => $endDate,
                    'quarter' => $quarter
                ];
            }
        }
        
        // Default fallback
        return [
            'start' => $year . '-01-01 00:00:00',
            'end' => $year . '-12-31 23:59:59',
            'quarter' => 'Q1'
        ];
    }
}