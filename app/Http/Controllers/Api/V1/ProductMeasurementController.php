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

            // Get products yang sudah punya due_date di quarter ini
            $productsWithMeasurement = ProductMeasurement::whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->whereNotNull('due_date') // Pastikan due_date tidak null
                ->pluck('product_id')
                ->unique()
                ->toArray();

            // Filter products yang belum punya measurement (due_date) di quarter ini
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
                'month' => 'nullable|integer|min:1|max:12',
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
            $month = $request->get('month');

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
                $productsQuery->whereBetween('product_measurements.due_date', [$quarterRange['start'], $quarterRange['end']]);
            } elseif ($month && $year) {
                // Filter by month and year
                $monthStart = $year . '-' . sprintf('%02d', $month) . '-01 00:00:00';
                $monthEnd = date('Y-m-t 23:59:59', strtotime($monthStart));
                $productsQuery->whereBetween('product_measurements.due_date', [$monthStart, $monthEnd]);
            } elseif ($startDate && $endDate) {
                $productsQuery->whereBetween('product_measurements.due_date', [$startDate, $endDate]);
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
                    $measurementQuery->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']]);
                } elseif ($month && $year) {
                    // Filter by month and year
                    $monthStart = $year . '-' . sprintf('%02d', $month) . '-01 00:00:00';
                    $monthEnd = date('Y-m-t 23:59:59', strtotime($monthStart));
                    $measurementQuery->whereBetween('due_date', [$monthStart, $monthEnd]);
                } elseif ($startDate && $endDate) {
                    $measurementQuery->whereBetween('due_date', [$startDate, $endDate]);
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
                    'due_date' => $latestMeasurement->due_date ? $latestMeasurement->due_date->format('Y-m-d H:i:s') : null,
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
                return 'TODO';
            case 'IN_PROGRESS':
                // Check if pernah submit (ada measurement_results yang NG)
                // Jika pernah submit dan ada NG, status = NEED_TO_MEASURE
                // Jika belum pernah submit, status = ONGOING
                if ($this->hasBeenSubmittedWithNG($measurement)) {
                    return 'NEED_TO_MEASURE';
                }
                return 'ONGOING';
            case 'COMPLETED':
                return $measurement->overall_result ? 'OK' : 'NG';
            default:
                return 'TODO';
        }
    }
    
    /**
     * Check if measurement has been submitted and has NG samples
     */
    private function hasBeenSubmittedWithNG($measurement): bool
    {
        // Cek apakah pernah submit (measured_at tidak null) dan ada hasil NG
        if (!$measurement->measured_at) {
            return false; // Belum pernah submit
        }
        
        // Cek apakah ada measurement results dengan status NG
        $measurementResults = $measurement->measurement_results ?? [];
        foreach ($measurementResults as $result) {
            if (isset($result['status']) && $result['status'] === false) {
                return true; // Ada yang NG
            }
            // Check samples
            if (isset($result['samples'])) {
                foreach ($result['samples'] as $sample) {
                    if (isset($sample['status']) && $sample['status'] === false) {
                        return true; // Ada sample NG
                    }
                }
            }
        }
        
        return false;
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
     * Status awal TODO, batch_number tidak auto-generate
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
                'due_date' => 'required|date',
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
                // Tidak auto-generate batch_number, akan di-set saat setBatchNumber dipanggil
                $measurement = ProductMeasurement::create([
                    'product_id' => $product->id,
                    'batch_number' => null, // Tidak auto-generate
                    'sample_count' => ($product->measurement_points[0]['setup']['sample_amount'] ?? 3),
                    'measurement_type' => $request->measurement_type,
                    'status' => 'TODO', // Status awal TODO
                    'sample_status' => 'NOT_COMPLETE',
                    'measured_by' => $user->id,
                    'due_date' => $request->due_date,
                    'measured_at' => null, // measured_at null sampai measurement selesai
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
     * Set batch number and move status from TODO to IN_PROGRESS
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

            // Update batch_number dan status
            $measurement->update([
                'batch_number' => $request->batch_number,
                'status' => 'IN_PROGRESS', // Status berubah dari TODO ke IN_PROGRESS
            ]);

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'batch_number' => $measurement->batch_number,
                'status' => $measurement->status,
            ], 'Batch number set successfully');

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
    /**
     * Check samples for a single measurement item
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

            // Get variable values from FE or from saved measurement_results
            $variableValues = $request->variable_values ?? [];
            
            // If variable_values not provided by FE, try to get from saved measurement_results
            if (empty($variableValues) && isset($measurementPoint['variables'])) {
                $variableValues = $this->extractVariableValuesFromSavedResults($measurement, $measurementPoint['variables']);
            }

            // Process single measurement item
            $measurementItemData = [
                'measurement_item_name_id' => $request->measurement_item_name_id,
                'variable_values' => $variableValues,
                'samples' => $request->samples,
            ];

            // Process samples dengan variables dan pre-processing formulas
            $processedSamples = $this->processSampleItem($measurementItemData, $measurementPoint);
            
            // Evaluate berdasarkan evaluation type
            $result = $this->evaluateSampleItem($processedSamples, $measurementPoint, $measurementItemData);

            // ✅ Save hasil check sebagai "jejak" untuk comparison nanti
            $this->saveLastCheckData($measurement, $request->measurement_item_name_id, $result);

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
     * Extract variable values from saved measurement_results
     */
    private function extractVariableValuesFromSavedResults(ProductMeasurement $measurement, array $variables): array
    {
        $variableValues = [];
        $savedResults = $measurement->measurement_results ?? [];
        
        foreach ($variables as $variable) {
            if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
                // Extract measurement_item_name_id from formula
                // Example: AVG(thickness_a_measurement) -> thickness_a_measurement
                preg_match_all('/AVG\(([^)]+)\)|MIN\(([^)]+)\)|MAX\(([^)]+)\)|([a-z_]+)/i', $variable['formula'], $matches);
                
                // Find all referenced measurement items
                $referencedItems = array_filter(array_merge($matches[1], $matches[2], $matches[3], $matches[4]));
                $referencedItems = array_unique($referencedItems);
                
                foreach ($referencedItems as $refItem) {
                    if (empty($refItem) || in_array(strtolower($refItem), ['avg', 'min', 'max'])) {
                        continue;
                    }
                    
                    // Find this measurement item in saved results
                    foreach ($savedResults as $savedResult) {
                        if ($savedResult['measurement_item_name_id'] === $refItem) {
                            // Calculate average from samples
                            $values = [];
                            if (isset($savedResult['samples'])) {
                                foreach ($savedResult['samples'] as $sample) {
                                    if (isset($sample['single_value']) && is_numeric($sample['single_value'])) {
                                        $values[] = $sample['single_value'];
                                    }
                                }
                            }
                            
                            if (!empty($values)) {
                                $avgValue = array_sum($values) / count($values);
                                $variableValues[] = [
                                    'name_id' => $refItem,
                                    'value' => $avgValue
                                ];
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        return $variableValues;
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
        $executor = new \NXP\MathExecutor();
        
        // Register custom functions
        $this->registerCustomFunctionsForItem($executor);
        
        // ✅ FIX: Extract RAW VALUES (single_value, before, after) dari samples
        $rawValues = [];
        foreach ($processedSamples as $sample) {
            // Collect single_value
            if (isset($sample['single_value']) && is_numeric($sample['single_value'])) {
                if (!isset($rawValues['single_value'])) {
                    $rawValues['single_value'] = [];
                }
                $rawValues['single_value'][] = $sample['single_value'];
            }
            
            // Collect before_after_value
            if (isset($sample['before_after_value'])) {
                if (isset($sample['before_after_value']['before']) && is_numeric($sample['before_after_value']['before'])) {
                    if (!isset($rawValues['before'])) {
                        $rawValues['before'] = [];
                    }
                    $rawValues['before'][] = $sample['before_after_value']['before'];
                }
                if (isset($sample['before_after_value']['after']) && is_numeric($sample['before_after_value']['after'])) {
                    if (!isset($rawValues['after'])) {
                        $rawValues['after'] = [];
                    }
                    $rawValues['after'][] = $sample['before_after_value']['after'];
                }
            }
        }
        
        // Set raw values as arrays untuk aggregation functions (avg, min, max, sum)
        foreach ($rawValues as $name => $values) {
            $executor->setVar($name, $values);
        }
        
        // Extract all pre_processing_formula_values from samples untuk aggregation
        $aggregatedValues = [];
        foreach ($processedSamples as $sample) {
            if (isset($sample['pre_processing_formula_values'])) {
                foreach ($sample['pre_processing_formula_values'] as $formulaValue) {
                    $name = $formulaValue['name'];
                    if (!isset($aggregatedValues[$name])) {
                        $aggregatedValues[$name] = [];
                    }
                    // Only aggregate non-null values
                    if ($formulaValue['value'] !== null && is_numeric($formulaValue['value'])) {
                        $aggregatedValues[$name][] = $formulaValue['value'];
                    }
                }
            }
        }
        
        // Set aggregated values as variables (as arrays for avg/min/max functions)
        foreach ($aggregatedValues as $name => $values) {
            $executor->setVar($name, $values);
        }
        
        // Execute each joint formula
        foreach ($jointSetting['formulas'] as $formula) {
            try {
                $result = $executor->execute($formula['formula']);
                $jointResults[] = [
                    'name' => $formula['name'],
                    'formula' => $formula['formula'],
                    'is_final_value' => $formula['is_final_value'],
                    'value' => $result
                ];
                
                // Set result untuk formula berikutnya
                $executor->setVar($formula['name'], $result);
            } catch (\Exception $e) {
                // If formula fails (missing variable), return null with error message
                $jointResults[] = [
                    'name' => $formula['name'],
                    'formula' => $formula['formula'],
                    'is_final_value' => $formula['is_final_value'],
                    'value' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        $result['joint_setting_formula_values'] = $jointResults;
        
        // Evaluate status based on rule if there's a final value
        $finalValue = null;
        foreach ($jointResults as $jointResult) {
            if ($jointResult['is_final_value'] && $jointResult['value'] !== null) {
                $finalValue = $jointResult['value'];
                break;
            }
        }
        
        if ($finalValue !== null) {
            $result['status'] = $this->evaluateWithRuleItem($finalValue, $ruleEvaluation);
        } else {
            $result['status'] = null; // Can't evaluate yet
        }
        
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
        $executor = new \NXP\MathExecutor();
        
        // Register custom functions
        $this->registerCustomFunctionsForItem($executor);
        
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
        
        // Set variable values as variables
        foreach ($variables as $variable) {
            if (is_numeric($variable['value'])) {
                $executor->setVar($variable['name_id'], $variable['value']);
            }
        }
        
        // Execute each formula
        foreach ($formulas as $formula) {
            try {
                $result = $executor->execute($formula['formula']);
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
    
    /**
     * Register custom functions for formula execution
     */
    private function registerCustomFunctionsForItem($executor): void
    {
        // Register AVG function for aggregation
        $executor->addFunction('avg', function($values) {
            if (is_array($values)) {
                $numericValues = array_filter($values, 'is_numeric');
                return count($numericValues) > 0 ? array_sum($numericValues) / count($numericValues) : null;
            }
            return is_numeric($values) ? $values : null;
        });
        
        // Register MIN function
        $executor->addFunction('min', function($values) {
            if (is_array($values)) {
                $numericValues = array_filter($values, 'is_numeric');
                return count($numericValues) > 0 ? min($numericValues) : null;
            }
            return is_numeric($values) ? $values : null;
        });
        
        // Register MAX function
        $executor->addFunction('max', function($values) {
            if (is_array($values)) {
                $numericValues = array_filter($values, 'is_numeric');
                return count($numericValues) > 0 ? max($numericValues) : null;
            }
            return is_numeric($values) ? $values : null;
        });
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
                'measurement_results.*.status' => 'nullable|boolean',
                'measurement_results.*.variable_values' => 'nullable|array',
                'measurement_results.*.variable_values.*.name_id' => 'required_with:measurement_results.*.variable_values|string',
                'measurement_results.*.variable_values.*.value' => 'required_with:measurement_results.*.variable_values',
                'measurement_results.*.samples' => 'nullable|array',
                'measurement_results.*.samples.*.sample_index' => 'required_with:measurement_results.*.samples|integer',
                'measurement_results.*.samples.*.status' => 'nullable|boolean',
                'measurement_results.*.samples.*.single_value' => 'nullable|numeric',
                'measurement_results.*.samples.*.before_after_value' => 'nullable|array',
                'measurement_results.*.samples.*.qualitative_value' => 'nullable|boolean',
                'measurement_results.*.samples.*.pre_processing_formula_values' => 'nullable|array',
                'measurement_results.*.joint_setting_formula_values' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Get existing results
            $existingResults = $measurement->measurement_results ?? [];
            $lastCheckData = $measurement->last_check_data ?? [];  // ✅ Jejak terakhir dari /samples/check
            $newResults = $request->measurement_results;
            
            // Build lookup for existing results by measurement_item_name_id
            $existingResultsMap = [];
            foreach ($existingResults as $key => $result) {
                $existingResultsMap[$result['measurement_item_name_id']] = [
                    'key' => $key,
                    'data' => $result
                ];
            }
            
            // Track which items changed and which need re-check
            $changedItems = [];
            $needReCheckItems = []; // Level 1: Raw data changed but not re-checked
            
            // Merge new results with existing
            foreach ($newResults as $newResult) {
                $itemNameId = $newResult['measurement_item_name_id'];
                $newSamples = $newResult['samples'] ?? [];
                
                // ✅ Compare dengan LAST CHECK DATA (jejak), bukan dengan saved results
                $lastCheckSamples = isset($lastCheckData[$itemNameId]) 
                    ? ($lastCheckData[$itemNameId]['samples'] ?? [])
                    : [];
                
                // If no last check data, compare with existing saved results
                if (empty($lastCheckSamples) && isset($existingResultsMap[$itemNameId])) {
                    $lastCheckSamples = $existingResultsMap[$itemNameId]['data']['samples'] ?? [];
                }
                
                // Check if samples data changed
                $samplesChanged = false;
                if (!empty($lastCheckSamples)) {
                    $samplesChanged = $this->samplesDataChanged($lastCheckSamples, $newSamples);
                }
                
                if ($samplesChanged) {
                    // Raw data changed!
                    
                    // Check if user already re-checked (hit /samples/check)
                    // Look for fresh data in last_check_data with recent timestamp
                    $hasRecentCheck = false;
                    if (isset($lastCheckData[$itemNameId]['checked_at'])) {
                        $lastCheckedAt = \Carbon\Carbon::parse($lastCheckData[$itemNameId]['checked_at']);
                        $timeDiff = $lastCheckedAt->diffInMinutes(now());
                        
                        // If last check was recent (< 5 minutes) AND samples match new data
                        if ($timeDiff < 5) {
                            $lastCheckSamplesRecent = $lastCheckData[$itemNameId]['samples'] ?? [];
                            // Compare new samples with last check samples
                            if (!$this->samplesDataChanged($lastCheckSamplesRecent, $newSamples)) {
                                // Samples match last check → user already validated
                                $hasRecentCheck = true;
                            }
                        }
                    }
                    
                    if (!$hasRecentCheck) {
                        // 🔴 Level 1 Warning: Raw data changed but not re-checked
                        $needReCheckItems[] = $itemNameId;
                    } else {
                        // ✅ User already re-checked, mark as changed for Level 2
                        $changedItems[] = $itemNameId;
                    }
                } else {
                    // No change in samples, but might have been recently checked
                    // (useful for items without sample changes but dependency updates)
                }
                
                // Update or add result
                if (isset($existingResultsMap[$itemNameId])) {
                    $key = $existingResultsMap[$itemNameId]['key'];
                    $oldData = $existingResultsMap[$itemNameId]['data'];
                    
                    // Update existing result
                    $existingResults[$key] = [
                        'measurement_item_name_id' => $itemNameId,
                        'status' => $newResult['status'] ?? null,
                        'variable_values' => $newResult['variable_values'] ?? [],
                        'samples' => $newSamples,
                        'joint_setting_formula_values' => $newResult['joint_setting_formula_values'] ?? null,
                        'created_at' => $oldData['created_at'] ?? now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];
                } else {
                    // Check if this is a new item with last_check_data
                    if (isset($lastCheckData[$itemNameId])) {
                        // User checked but never saved, this is first save
                        $changedItems[] = $itemNameId;
                    }
                    
                    // Add new result
                    $existingResults[] = [
                        'measurement_item_name_id' => $itemNameId,
                        'status' => $newResult['status'] ?? null,
                        'variable_values' => $newResult['variable_values'] ?? [],
                        'samples' => $newSamples,
                        'joint_setting_formula_values' => $newResult['joint_setting_formula_values'] ?? null,
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];
                }
            }
            
            // Level 1 Validation: Check if any items need re-check (raw data changed but not validated)
            // Build warnings for items that need re-check
            $needReCheckWarnings = [];
            foreach ($needReCheckItems as $itemNameId) {
                // Get last check values and current values for comparison
                $lastCheckValues = [];
                $currentValues = [];
                
                if (isset($lastCheckData[$itemNameId]['samples'])) {
                    foreach ($lastCheckData[$itemNameId]['samples'] as $sample) {
                        if (isset($sample['single_value'])) {
                            $lastCheckValues[] = $sample['single_value'];
                        }
                    }
                }
                
                // Find current values in newResults
                foreach ($newResults as $result) {
                    if ($result['measurement_item_name_id'] === $itemNameId && isset($result['samples'])) {
                        foreach ($result['samples'] as $sample) {
                            if (isset($sample['single_value'])) {
                                $currentValues[] = $sample['single_value'];
                            }
                        }
                        break;
                    }
                }
                
                $needReCheckWarnings[] = [
                    'measurement_item_name_id' => $itemNameId,
                    'level' => 'CRITICAL',
                    'reason' => "Raw data berubah dari hasil check terakhir tetapi belum di-validate ulang",
                    'action' => 'Silakan hit endpoint /samples/check untuk item ini terlebih dahulu',
                    'last_check_values' => $lastCheckValues,
                    'current_values' => $currentValues,
                    'type' => 'RAW_DATA_CHANGED_NOT_VALIDATED'
                ];
            }
            
            // Level 2 Validation: Check if any dependent items need re-checking
            $dependencyWarnings = $this->validateDependencies($measurement->product, $existingResults, $changedItems);

            // Combine all warnings
            $allWarnings = array_merge($needReCheckWarnings, $dependencyWarnings);
            
            // ✅ CHECK FIRST: If there are warnings, STOP and return error - DO NOT SAVE!
            if (!empty($allWarnings)) {
                // Categorize warnings
                $criticalCount = count(array_filter($allWarnings, fn($w) => ($w['level'] ?? '') === 'CRITICAL'));
                $dependencyCount = count($dependencyWarnings);
                
                $errorMessage = "Tidak dapat menyimpan progress karena ada data yang perlu di-validate ulang";
                
                if ($criticalCount > 0) {
                    $errorMessage .= ": {$criticalCount} item dengan raw data berubah belum di-check ulang";
                }
                if ($dependencyCount > 0) {
                    $errorMessage .= ($criticalCount > 0 ? ", " : ": ") . "{$dependencyCount} item terpengaruh perubahan dependency";
                }
                
                return $this->errorResponse(
                    $errorMessage,
                    'VALIDATION_REQUIRED',
                    400,
                    [
                        'warnings' => $allWarnings,
                        'critical_count' => $criticalCount,
                        'dependency_count' => $dependencyCount,
                    ]
                );
            }

            // ✅ No warnings - SAFE TO SAVE!
            $measurement->update([
                'status' => 'IN_PROGRESS',
                'measurement_results' => $existingResults,
            ]);

            // Calculate progress
            $progress = $this->calculateProgress($measurement);

            $response = [
                'measurement_id' => $measurement->measurement_id,
                'status' => 'IN_PROGRESS',
                'progress' => $progress,
                'saved_items' => count($newResults),
                'total_saved_items' => count($existingResults),
            ];

            return $this->successResponse($response, 'Progress saved successfully');

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
     * Q1: Januari - Maret (1-3)
     * Q2: April - Juni (4-6)
     * Q3: Juli - September (7-9)
     * Q4: Oktober - Desember (10-12)
     */
    private function getQuarterRangeFromQuarterNumber(int $quarter, int $year): array
    {
        switch ($quarter) {
            case 1:
                $startDate = $year . '-01-01 00:00:00';
                $endDate = $year . '-03-31 23:59:59';
                break;
            case 2:
                $startDate = $year . '-04-01 00:00:00';
                $endDate = $year . '-06-30 23:59:59';
                break;
            case 3:
                $startDate = $year . '-07-01 00:00:00';
                $endDate = $year . '-09-30 23:59:59';
                break;
            case 4:
                $startDate = $year . '-10-01 00:00:00';
                $endDate = $year . '-12-31 23:59:59';
                break;
            default:
                // Fallback to Q1
                $startDate = $year . '-01-01 00:00:00';
                $endDate = $year . '-03-31 23:59:59';
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
     * Q1: Januari - Maret (1-3)
     * Q2: April - Juni (4-6)
     * Q3: Juli - September (7-9)
     * Q4: Oktober - Desember (10-12)
     */
    private function getQuarterRange(string $date): array
    {
        $month = (int) date('m', strtotime($date));
        $year = (int) date('Y', strtotime($date));
        
        $quarterRanges = [
            'Q1' => ['01', '02', '03'],     // Januari - Maret
            'Q2' => ['04', '05', '06'],     // April - Juni
            'Q3' => ['07', '08', '09'],     // Juli - September
            'Q4' => ['10', '11', '12']      // Oktober - Desember
        ];
        
        foreach ($quarterRanges as $quarter => $months) {
            if (in_array(sprintf('%02d', $month), $months)) {
                // Determine quarter boundaries
                if ($quarter === 'Q1') {
                    $startDate = $year . '-01-01 00:00:00';
                    $endDate = $year . '-03-31 23:59:59';
                } elseif ($quarter === 'Q2') {
                    $startDate = $year . '-04-01 00:00:00';
                    $endDate = $year . '-06-30 23:59:59';
                } elseif ($quarter === 'Q3') {
                    $startDate = $year . '-07-01 00:00:00';
                    $endDate = $year . '-09-30 23:59:59';
                } else { // Q4
                    $startDate = $year . '-10-01 00:00:00';
                    $endDate = $year . '-12-31 23:59:59';
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

    /**
     * Delete product measurement (only if status is TODO)
     * DELETE /api/v1/product-measurement/:id
     */
    public function destroy(string $productMeasurementId)
    {
        try {
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Validate hanya bisa delete jika status TODO
            if ($measurement->status !== 'TODO') {
                return $this->errorResponse(
                    'Product measurement hanya bisa dihapus jika statusnya TODO',
                    'DELETE_NOT_ALLOWED',
                    400
                );
            }

            $measurement->delete();

            return $this->successResponse(
                ['deleted' => true],
                'Product measurement berhasil dihapus'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting product measurement: ' . $e->getMessage(),
                'DELETE_ERROR',
                500
            );
        }
    }

    /**
     * Update product measurement (only due_date can be updated)
     * PUT/PATCH /api/v1/product-measurement/:id
     */
    public function update(Request $request, string $productMeasurementId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'due_date' => 'required|date',
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

            // Update due_date
            $measurement->update([
                'due_date' => $request->due_date,
            ]);

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'due_date' => $measurement->due_date->format('Y-m-d H:i:s'),
            ], 'Product measurement berhasil diupdate');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error updating product measurement: ' . $e->getMessage(),
                'UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Get measurement progress for a quarter/year
     * GET /api/v1/product-measurement/progress?quarter=4&year=2025
     */
    public function getProgress(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get all measurements in this quarter
            $measurements = ProductMeasurement::whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->whereNotNull('due_date')
                ->get();

            // Calculate progress
            $totalProducts = $measurements->count();
            $ok = 0;
            $needToMeasure = 0;
            $ongoing = 0;
            $notChecked = 0;

            foreach ($measurements as $measurement) {
                $productStatus = $this->determineProductStatus($measurement);
                
                switch ($productStatus) {
                    case 'OK':
                        $ok++;
                        break;
                    case 'NEED_TO_MEASURE':
                        $needToMeasure++;
                        break;
                    case 'ONGOING':
                        $ongoing++;
                        break;
                    case 'TODO':
                    default:
                        $notChecked++;
                        break;
                }
            }

            return $this->successResponse([
                'progress' => [
                    'total_products' => $totalProducts,
                    'ok' => $ok,
                    'need_to_measure_again' => $needToMeasure,
                    'ongoing' => $ongoing,
                    'not_checked' => $notChecked,
                ]
            ], 'Progress retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error getting progress: ' . $e->getMessage(),
                'PROGRESS_ERROR',
                500
            );
        }
    }

    /**
     * Get progress per category for dashboard
     * GET /api/v1/product-measurement/progress-category?quarter=3&year=2025
     * 
     * Response format:
     * {
     *   "perCategory": [
     *     {
     *       "category_id": 1,
     *       "category_name": "Tube Test",
     *       "product_result": {
     *         "ok": 25,
     *         "ng": 10,
     *         "total": 50
     *       },
     *       "product_checking": {
     *         "todo": 15,
     *         "checked": 25,
     *         "done": 40,
     *         "total": 50
     *       }
     *     }
     *   ]
     * }
     */
    public function getProgressCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get all categories
            $categories = \App\Models\ProductCategory::all();

            $perCategory = [];

            foreach ($categories as $category) {
                // Get all products in this category
                $products = Product::where('product_category_id', $category->id)->get();
                
                if ($products->isEmpty()) {
                    continue; // Skip categories with no products
                }

                $productIds = $products->pluck('id')->toArray();

                // Get measurements for these products in the quarter
                $measurements = ProductMeasurement::whereIn('product_id', $productIds)
                    ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                    ->whereNotNull('due_date')
                    ->get();

                if ($measurements->isEmpty()) {
                    continue; // Skip categories with no measurements in this quarter
                }

                // Calculate product_result (OK/NG)
                $okCount = 0;
                $ngCount = 0;

                // Calculate product_checking (TODO/checked/done)
                $todoCount = 0;
                $checkedCount = 0;
                $doneCount = 0;

                foreach ($measurements as $measurement) {
                    // Product Result: OK vs NG
                    if ($measurement->status === 'COMPLETED') {
                        if ($measurement->overall_result) {
                            $okCount++;
                        } else {
                            $ngCount++;
                        }
                    }

                    // Product Checking: TODO vs CHECKED vs DONE
                    if ($measurement->status === 'TODO' || $measurement->batch_number === null) {
                        $todoCount++;
                    } elseif ($measurement->status === 'IN_PROGRESS') {
                        $checkedCount++; // Ongoing/In Progress = being checked
                    } elseif ($measurement->status === 'COMPLETED') {
                        $doneCount++; // Completed = done checking
                    } elseif ($measurement->status === 'PENDING') {
                        $checkedCount++; // Pending = checked, waiting for action
                    }
                }

                $totalProducts = $measurements->count();

                $perCategory[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'product_result' => [
                        'ok' => $okCount,
                        'ng' => $ngCount,
                        'total' => $totalProducts,
                    ],
                    'product_checking' => [
                        'todo' => $todoCount,
                        'checked' => $checkedCount,
                        'done' => $doneCount,
                        'total' => $totalProducts,
                    ],
                ];
            }

            return $this->successResponse([
                'perCategory' => $perCategory,
            ], 'Progress per category retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error getting progress per category: ' . $e->getMessage(),
                'PROGRESS_CATEGORY_ERROR',
                500
            );
        }
    }

    /**
     * Get overall progress for all product measurements
     * GET /api/v1/product-measurement/progress-all?quarter=3&year=2025
     * 
     * Response format:
     * {
     *   "done": 25,
     *   "ongoing": 10,
     *   "backlog": 15
     * }
     */
    public function getProgressAll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get all measurements in this quarter
            $measurements = ProductMeasurement::whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->whereNotNull('due_date')
                ->get();

            // Calculate progress
            $done = 0;
            $ongoing = 0;
            $backlog = 0;

            foreach ($measurements as $measurement) {
                if ($measurement->status === 'COMPLETED') {
                    $done++;
                } elseif ($measurement->status === 'IN_PROGRESS' || $measurement->status === 'PENDING') {
                    $ongoing++;
                } else {
                    // TODO or other status = backlog
                    $backlog++;
                }
            }

            return $this->successResponse([
                'done' => $done,
                'ongoing' => $ongoing,
                'backlog' => $backlog,
            ], 'Overall progress retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error getting overall progress: ' . $e->getMessage(),
                'PROGRESS_ALL_ERROR',
                500
            );
        }
    }
    
    /**
     * Check if samples data has changed
     */
    private function samplesDataChanged(array $oldSamples, array $newSamples): bool
    {
        if (count($oldSamples) !== count($newSamples)) {
            return true;
        }
        
        // Compare sample values
        foreach ($newSamples as $index => $newSample) {
            if (!isset($oldSamples[$index])) {
                return true;
            }
            
            $oldSample = $oldSamples[$index];
            
            // Check single_value
            if (isset($newSample['single_value']) && isset($oldSample['single_value'])) {
                if ($newSample['single_value'] != $oldSample['single_value']) {
                    return true;
                }
            }
            
            // Check before_after_value
            if (isset($newSample['before_after_value']) && isset($oldSample['before_after_value'])) {
                if (json_encode($newSample['before_after_value']) != json_encode($oldSample['before_after_value'])) {
                    return true;
                }
            }
            
            // Check qualitative_value
            if (isset($newSample['qualitative_value']) && isset($oldSample['qualitative_value'])) {
                if ($newSample['qualitative_value'] != $oldSample['qualitative_value']) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Validate dependencies - detect outdated dependent items
     */
    private function validateDependencies($product, array $savedResults, array $changedItems): array
    {
        $warnings = [];
        
        if (empty($changedItems)) {
            return $warnings;
        }
        
        // Get all measurement points
        $measurementPoints = $product->measurement_points ?? [];
        
        // Track affected items (items that got warnings)
        // This is needed for transitive/chain dependency detection
        $affectedItems = $changedItems; // Start with changed items
        
        // Multiple passes to catch chain dependencies
        // e.g., thickness_a changes → room_temp affected → fix_temp affected
        $maxIterations = 10; // Prevent infinite loop
        $iteration = 0;
        $foundNewAffected = true;
        
        while ($foundNewAffected && $iteration < $maxIterations) {
            $foundNewAffected = false;
            $iteration++;
            
            // Check each saved result
            foreach ($savedResults as $result) {
                $itemNameId = $result['measurement_item_name_id'];
                
                // Skip if this item is already in affected list
                if (in_array($itemNameId, $affectedItems)) {
                    continue;
                }
                
                // Find measurement point definition
                $measurementPoint = null;
                foreach ($measurementPoints as $mp) {
                    if ($mp['measurement_item_name_id'] === $itemNameId) {
                        $measurementPoint = $mp;
                        break;
                    }
                }
                
                if (!$measurementPoint) {
                    continue;
                }
                
                // Collect all dependencies for this item
                $allDependencies = [];
                
                // 1. Check variables (for pre-processing formulas)
                if (isset($measurementPoint['variables']) && !empty($measurementPoint['variables'])) {
                    foreach ($measurementPoint['variables'] as $variable) {
                        if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
                            // Extract referenced measurement items from formula
                            preg_match_all('/AVG\(([^)]+)\)|MIN\(([^)]+)\)|MAX\(([^)]+)\)|([a-z_]+)/i', $variable['formula'], $matches);
                            $referencedItems = array_filter(array_merge($matches[1], $matches[2], $matches[3], $matches[4]));
                            $allDependencies = array_merge($allDependencies, $referencedItems);
                        }
                    }
                }
                
                // 2. Check joint setting (for aggregation formulas)
                if (isset($measurementPoint['joint_setting'])) {
                    $jointSetting = $measurementPoint['joint_setting'];
                    
                    // Check joint_setting_formula
                    if (isset($jointSetting['joint_setting_formula']) && !empty($jointSetting['joint_setting_formula'])) {
                        foreach ($jointSetting['joint_setting_formula'] as $jointFormula) {
                            if (isset($jointFormula['formula'])) {
                                // Extract variable names (e.g., CROSS_SECTION, FINAL_AVG)
                                // These variables come from OTHER measurement items
                                preg_match_all('/[A-Z_]+/', $jointFormula['formula'], $varMatches);
                                foreach ($varMatches[0] as $varName) {
                                    // Find which measurement item provides this variable
                                    $sourceItem = $this->findMeasurementItemByVariableName($measurementPoints, $varName);
                                    if ($sourceItem) {
                                        $allDependencies[] = $sourceItem;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Remove duplicates and filter empty
                $allDependencies = array_unique(array_filter($allDependencies, function ($dep) {
                    return !empty($dep) && !in_array(strtolower($dep), ['avg', 'min', 'max', 'sum']);
                }));
                
                // Check if any dependency is in affected list
                $affectedDependencies = array_intersect($allDependencies, $affectedItems);
                
                if (!empty($affectedDependencies)) {
                    // This item is affected by changed dependencies!
                    $affectedItems[] = $itemNameId;
                    $foundNewAffected = true;
                    
                    $warnings[] = [
                        'measurement_item_name_id' => $itemNameId,
                        'level' => 'WARNING',
                        'reason' => 'Item ini bergantung pada ' . implode(', ', $affectedDependencies) . ' yang telah berubah atau terpengaruh perubahan, namun item ini belum di-check ulang',
                        'action' => 'Silakan hit endpoint /samples/check untuk ' . implode(', ', $affectedDependencies) . ' terlebih dahulu, lalu hit untuk ' . $itemNameId,
                        'dependencies_changed' => array_values($affectedDependencies),
                        'type' => 'DEPENDENCY_CHANGED',
                    ];
                }
            }
        }
        
        return $warnings;
    }
    
    /**
     * Find measurement item that provides a specific variable name
     */
    private function findMeasurementItemByVariableName(array $measurementPoints, string $variableName): ?string
    {
        foreach ($measurementPoints as $mp) {
            if (isset($mp['variables']) && !empty($mp['variables'])) {
                foreach ($mp['variables'] as $variable) {
                    if ($variable['name'] === $variableName) {
                        return $mp['measurement_item_name_id'];
                    }
                }
            }
        }
        return null;
    }
    
    /**
     * Save last check data as "jejak" for future comparison
     */
    private function saveLastCheckData(ProductMeasurement $measurement, string $itemNameId, array $result): void
    {
        $lastCheckData = $measurement->last_check_data ?? [];
        
        // Save or update last check for this item
        $lastCheckData[$itemNameId] = [
            'samples' => $result['samples'],
            'variable_values' => $result['variable_values'] ?? [],
            'status' => $result['status'],
            'joint_setting_formula_values' => $result['joint_setting_formula_values'] ?? null,
            'checked_at' => now()->toISOString(),
        ];
        
        $measurement->update([
            'last_check_data' => $lastCheckData
        ]);
    }
}