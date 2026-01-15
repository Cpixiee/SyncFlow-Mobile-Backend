<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScaleMeasurement;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ScaleMeasurementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get scale measurements list with filters
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate query parameters
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'nullable|in:NOT_CHECKED,CHECKED',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'query' => 'nullable|string|max:255',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $date = $request->get('date');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $status = $request->get('status');
            $productCategoryId = $request->get('product_category_id');
            $query = $request->get('query');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Build query
            $measurementsQuery = ScaleMeasurement::with(['product.productCategory', 'measuredBy']);

            // Filter by date
            if ($date) {
                $measurementsQuery->whereDate('measurement_date', $date);
            } elseif ($startDate && $endDate) {
                $measurementsQuery->whereBetween('measurement_date', [$startDate, $endDate]);
            }

            // Filter by status
            if ($status) {
                $measurementsQuery->where('status', $status);
            }

            // Filter by product category
            if ($productCategoryId) {
                $measurementsQuery->whereHas('product', function ($q) use ($productCategoryId) {
                    $q->where('product_category_id', $productCategoryId);
                });
            }

            // Search query
            if ($query) {
                $measurementsQuery->where(function ($q) use ($query) {
                    $q->whereHas('product', function ($subQ) use ($query) {
                        $subQ->where('product_name', 'like', "%{$query}%")
                             ->orWhere('product_id', 'like', "%{$query}%")
                             ->orWhere('article_code', 'like', "%{$query}%");
                    })
                    ->orWhere('scale_measurement_id', 'like', "%{$query}%");
                });
            }

            // Order by latest
            $measurementsQuery->orderBy('measurement_date', 'desc')
                             ->orderBy('created_at', 'desc');

            // Paginate
            $measurements = $measurementsQuery->paginate($limit, ['*'], 'page', $page);

            // Transform data
            $transformedData = collect($measurements->items())->map(function ($measurement) {
                return [
                    'scale_measurement_id' => $measurement->scale_measurement_id,
                    'batch_number' => $measurement->batch_number,
                    'machine_number' => $measurement->machine_number,
                    'measurement_date' => $measurement->measurement_date->format('Y-m-d'),
                    'weight' => $measurement->weight,
                    'status' => $measurement->status,
                    'notes' => $measurement->notes,
                    'product' => [
                        'id' => $measurement->product->product_id,
                        'product_name' => $measurement->product->product_name,
                        'product_spec_name' => $measurement->product->product_spec_name,
                        'product_category_id' => $measurement->product->product_category_id,
                        'product_category_name' => $measurement->product->productCategory->name,
                        'ref_spec_number' => $measurement->product->ref_spec_number,
                        'nom_size_vo' => $measurement->product->nom_size_vo,
                        'article_code' => $measurement->product->article_code,
                        'no_document' => $measurement->product->no_document,
                        'no_doc_reference' => $measurement->product->no_doc_reference,
                    ],
                    'measured_by' => $measurement->measuredBy ? [
                        'username' => $measurement->measuredBy->username,
                        'employee_id' => $measurement->measuredBy->employee_id,
                    ] : null,
                    'created_at' => $measurement->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $measurement->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return $this->paginationResponse(
                $transformedData->toArray(),
                [
                    'current_page' => $measurements->currentPage(),
                    'total_page' => $measurements->lastPage(),
                    'limit' => $measurements->perPage(),
                    'total_docs' => $measurements->total(),
                ],
                'Scale measurements retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve scale measurements: ' . $e->getMessage(),
                'SCALE_MEASUREMENTS_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Create new scale measurement entry
     */
    public function store(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|string|exists:products,product_id',
                'batch_number' => 'required|string|unique:scale_measurements,batch_number',
                'machine_number' => 'nullable|string|max:255',
                'measurement_date' => 'required|date',
                'weight' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Get product
            $product = Product::where('product_id', $request->product_id)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // Check duplicate: 1 product per day
            $existingMeasurement = ScaleMeasurement::where('product_id', $product->id)
                ->whereDate('measurement_date', $request->measurement_date)
                ->first();

            if ($existingMeasurement) {
                return $this->errorResponse(
                    'Product ini sudah memiliki scale measurement untuk tanggal tersebut',
                    'DUPLICATE_SCALE_MEASUREMENT',
                    400
                );
            }

            // Determine status
            $status = $request->weight !== null ? 'CHECKED' : 'NOT_CHECKED';

            // Create scale measurement
            $measurement = ScaleMeasurement::create([
                'product_id' => $product->id,
                'batch_number' => $request->batch_number,
                'machine_number' => $request->machine_number,
                'measurement_date' => $request->measurement_date,
                'weight' => $request->weight,
                'status' => $status,
                'measured_by' => $user->id,
                'notes' => $request->notes,
            ]);

            return $this->successResponse([
                'scale_measurement_id' => $measurement->scale_measurement_id,
                'batch_number' => $measurement->batch_number,
                'machine_number' => $measurement->machine_number,
                'measurement_date' => $measurement->measurement_date->format('Y-m-d'),
                'weight' => $measurement->weight,
                'status' => $measurement->status,
            ], 'Scale measurement created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create scale measurement: ' . $e->getMessage(),
                'SCALE_MEASUREMENT_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Get single scale measurement by ID
     */
    public function show(string $scaleMeasurementId)
    {
        try {
            $measurement = ScaleMeasurement::with(['product.productCategory', 'measuredBy'])
                ->where('scale_measurement_id', $scaleMeasurementId)
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Scale measurement tidak ditemukan');
            }

            return $this->successResponse([
                'scale_measurement_id' => $measurement->scale_measurement_id,
                'batch_number' => $measurement->batch_number,
                'machine_number' => $measurement->machine_number,
                'measurement_date' => $measurement->measurement_date->format('Y-m-d'),
                'weight' => $measurement->weight,
                'status' => $measurement->status,
                'notes' => $measurement->notes,
                'product' => [
                    'id' => $measurement->product->product_id,
                    'product_name' => $measurement->product->product_name,
                    'product_spec_name' => $measurement->product->product_spec_name,
                    'product_category_id' => $measurement->product->product_category_id,
                    'product_category_name' => $measurement->product->productCategory->name,
                    'ref_spec_number' => $measurement->product->ref_spec_number,
                    'nom_size_vo' => $measurement->product->nom_size_vo,
                    'article_code' => $measurement->product->article_code,
                ],
                'measured_by' => $measurement->measuredBy ? [
                    'username' => $measurement->measuredBy->username,
                    'employee_id' => $measurement->measuredBy->employee_id,
                ] : null,
                'created_at' => $measurement->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $measurement->updated_at->format('Y-m-d H:i:s'),
            ], 'Scale measurement retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve scale measurement: ' . $e->getMessage(),
                'SCALE_MEASUREMENT_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Update scale measurement (mainly for updating weight)
     */
    public function update(Request $request, string $scaleMeasurementId)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Find measurement
            $measurement = ScaleMeasurement::where('scale_measurement_id', $scaleMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Scale measurement tidak ditemukan');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'batch_number' => "nullable|string|unique:scale_measurements,batch_number,{$measurement->id}",
                'machine_number' => 'nullable|string|max:255',
                'measurement_date' => 'nullable|date',
                'weight' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Update fields
            if ($request->has('batch_number')) {
                $measurement->batch_number = $request->batch_number;
            }

            if ($request->has('machine_number')) {
                $measurement->machine_number = $request->machine_number;
            }

            if ($request->has('measurement_date')) {
                // Check duplicate if date changed
                if ($request->measurement_date !== $measurement->measurement_date->format('Y-m-d')) {
                    $existingMeasurement = ScaleMeasurement::where('product_id', $measurement->product_id)
                        ->where('id', '!=', $measurement->id)
                        ->whereDate('measurement_date', $request->measurement_date)
                        ->first();

                    if ($existingMeasurement) {
                        return $this->errorResponse(
                            'Product ini sudah memiliki scale measurement untuk tanggal tersebut',
                            'DUPLICATE_SCALE_MEASUREMENT',
                            400
                        );
                    }
                }
                
                $measurement->measurement_date = $request->measurement_date;
            }

            if ($request->has('weight')) {
                $measurement->weight = $request->weight;
            }

            if ($request->has('notes')) {
                $measurement->notes = $request->notes;
            }

            // Update status based on weight
            $measurement->updateStatus();

            return $this->successResponse([
                'scale_measurement_id' => $measurement->scale_measurement_id,
                'batch_number' => $measurement->batch_number,
                'machine_number' => $measurement->machine_number,
                'measurement_date' => $measurement->measurement_date->format('Y-m-d'),
                'weight' => $measurement->weight,
                'status' => $measurement->status,
            ], 'Scale measurement updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not update scale measurement: ' . $e->getMessage(),
                'SCALE_MEASUREMENT_UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Delete scale measurement
     */
    public function destroy(string $scaleMeasurementId)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Find measurement
            $measurement = ScaleMeasurement::where('scale_measurement_id', $scaleMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Scale measurement tidak ditemukan');
            }

            // Delete
            $measurement->delete();

            return $this->successResponse(null, 'Scale measurement deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not delete scale measurement: ' . $e->getMessage(),
                'SCALE_MEASUREMENT_DELETE_ERROR',
                500
            );
        }
    }

    /**
     * Get available products for scale measurement on specific date
     */
    public function getAvailableProducts(Request $request)
    {
        try {
            // Validate query parameters
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $date = $request->get('date');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Get products yang sudah ada measurement di tanggal tersebut
            $productsWithMeasurement = ScaleMeasurement::whereDate('measurement_date', $date)
                ->pluck('product_id')
                ->unique()
                ->toArray();

            // Get all products yang belum ada measurement
            $allProductsQuery = Product::with(['quarter', 'productCategory']);

            if (!empty($productsWithMeasurement)) {
                $allProductsQuery->whereNotIn('id', $productsWithMeasurement);
            }

            // Paginate
            $products = $allProductsQuery->paginate($limit, ['*'], 'page', $page);

            // Transform
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
     * Bulk create scale measurements for multiple products
     */
    public function bulkStore(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Support two formats:
            // 1. batch_number (string) - untuk semua product dengan auto-suffix
            // 2. batch_numbers (object) - untuk set batch_number per product
            $validator = Validator::make($request->all(), [
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|string|exists:products,product_id',
                'batch_number' => 'required_without:batch_numbers|string',
                'batch_numbers' => 'required_without:batch_number|array',
                'batch_numbers.*' => 'required|string',
                'machine_number' => 'nullable|string|max:255',
                'measurement_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $results = [];
            $products = Product::whereIn('product_id', $request->product_ids)->get();
            $batchNumbersMap = $request->batch_numbers ?? [];

            // ✅ Validasi: Jika menggunakan batch_numbers, pastikan semua product_ids ada di map
            if (!empty($batchNumbersMap)) {
                $missingProducts = [];
                foreach ($request->product_ids as $productId) {
                    if (!isset($batchNumbersMap[$productId])) {
                        $missingProducts[] = $productId;
                    }
                }
                
                if (!empty($missingProducts)) {
                    $missingList = implode(', ', $missingProducts);
                    $count = count($missingProducts);
                    $message = $count === 1 
                        ? "Tidak ada batch number untuk product: {$missingList}"
                        : "Tidak ada batch number untuk {$count} product: {$missingList}";
                    
                    return $this->errorResponse(
                        $message,
                        'MISSING_BATCH_NUMBER',
                        400
                    );
                }
            }

            foreach ($products as $product) {
                // Check duplicate
                $existingMeasurement = ScaleMeasurement::where('product_id', $product->id)
                    ->whereDate('measurement_date', $request->measurement_date)
                    ->first();

                if ($existingMeasurement) {
                    continue; // Skip duplicates
                }

                // Determine batch_number:
                // 1. Jika ada batch_numbers map, gunakan batch_number untuk product ini
                // 2. Jika tidak, gunakan batch_number dengan auto-suffix
                if (!empty($batchNumbersMap) && isset($batchNumbersMap[$product->product_id])) {
                    // Format baru: batch_numbers object dengan mapping per product
                    $batchNumber = $batchNumbersMap[$product->product_id];
                    
                    // Validate uniqueness
                    $existingBatch = ScaleMeasurement::where('batch_number', $batchNumber)->first();
                    if ($existingBatch) {
                        return $this->errorResponse(
                            "Batch number '{$batchNumber}' sudah digunakan untuk product lain",
                            'DUPLICATE_BATCH_NUMBER',
                            400
                        );
                    }
                } else {
                    // Format lama: batch_number dengan auto-suffix
                    $batchNumber = $request->batch_number . '-' . strtoupper(substr(uniqid(), -6));
                }

                $measurement = ScaleMeasurement::create([
                    'product_id' => $product->id,
                    'batch_number' => $batchNumber,
                    'machine_number' => $request->machine_number,
                    'measurement_date' => $request->measurement_date,
                    'weight' => null,
                    'status' => 'NOT_CHECKED',
                    'measured_by' => $user->id,
                    'notes' => null,
                ]);

                $results[$product->product_id] = $measurement->scale_measurement_id;
            }

            return $this->successResponse($results, 'Bulk scale measurements created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create bulk scale measurements: ' . $e->getMessage(),
                'SCALE_MEASUREMENT_BULK_CREATE_ERROR',
                500
            );
        }
    }
}

