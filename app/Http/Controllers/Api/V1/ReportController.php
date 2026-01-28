<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMeasurement;
use App\Models\ScaleMeasurement;
use App\Models\Quarter;
use App\Models\ReportMasterFile;
use App\Traits\ApiResponseTrait;
use App\Helpers\ReportExcelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

class ReportController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get available quarters for filter
     * GET /api/v1/reports/filters/quarters
     */
    public function getQuarters(Request $request)
    {
        try {
            $quarters = Quarter::select('name', 'year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($quarter) {
                    return [
                        'quarter' => (int) str_replace('Q', '', $quarter->name),
                        'year' => $quarter->year,
                        'label' => "{$quarter->name} {$quarter->year}"
                    ];
                });

            return $this->successResponse($quarters, 'Quarters retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving quarters: ' . $e->getMessage(), 'QUARTERS_FETCH_ERROR', 500);
        }
    }

    /**
     * Get products for selected quarter with pagination
     * GET /api/v1/reports/filters/products?quarter=3&year=2025&keyword=CIVUS&category=1&page=1&limit=10
     */
    public function getProducts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'keyword' => 'nullable|string|max:255',
                'category' => 'nullable|integer|exists:product_categories,id',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $keyword = $request->get('keyword');
            $category = $request->get('category');
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get product IDs yang punya measurement di quarter ini
            $productIds = ProductMeasurement::whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->whereNotNull('due_date')
                ->distinct()
                ->pluck('product_id')
                ->toArray();

            if (empty($productIds)) {
                return response()->json([
                    'http_code' => 200,
                    'message' => 'Products retrieved successfully',
                    'error_id' => null,
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'total_page' => 1,
                        'limit' => $limit,
                        'total_docs' => 0,
                    ],
                ], 200);
            }

            // Get products dengan filter
            $productsQuery = Product::with('productCategory')
                ->whereIn('id', $productIds);

            // Filter by category
            if ($category) {
                $productsQuery->where('product_category_id', $category);
            }

            // Search by keyword (search in product_name only as per requirement)
            if ($keyword) {
                $productsQuery->where('product_name', 'like', "%{$keyword}%");
            }

            // Paginate
            $products = $productsQuery->paginate($limit, ['*'], 'page', $page);

            $transformedProducts = collect($products->items())
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'product_spec_name' => $product->product_spec_name,
                        'product_category' => $product->productCategory->name ?? null,
                    ];
                })->values()->all();

            return response()->json([
                'http_code' => 200,
                'message' => 'Products retrieved successfully',
                'error_id' => null,
                'data' => $transformedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'total_page' => $products->lastPage(),
                    'limit' => $products->perPage(),
                    'total_docs' => $products->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving products: ' . $e->getMessage(), 'PRODUCTS_FETCH_ERROR', 500);
        }
    }

    /**
     * Get batch numbers for selected product in quarter
     * GET /api/v1/reports/filters/batch-numbers?quarter=3&year=2025&product_id=PRD-XXXXX
     */
    public function getBatchNumbers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get product
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // Get batch numbers dari measurements di quarter ini
            $batchNumbers = ProductMeasurement::where('product_id', $product->id)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->whereNotNull('due_date')
                ->whereNotNull('batch_number')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($measurement) {
                    return [
                        'batch_number' => $measurement->batch_number,
                        'measurement_id' => $measurement->measurement_id,
                        'created_at' => $measurement->created_at->format('Y-m-d H:i:s'),
                        'product_status' => $measurement->product_status ?? 'PENDING',
                    ];
                });

            return $this->successResponse($batchNumbers, 'Batch numbers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving batch numbers: ' . $e->getMessage(), 'BATCH_NUMBERS_FETCH_ERROR', 500);
        }
    }

    /**
     * Get report data (measurement items dengan type dan status)
     * GET /api/v1/reports/data?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
     */
    public function getReportData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product
            $product = Product::with('productCategory')->where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get measurement untuk batch number ini
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan untuk batch number ini');
            }

            // Get measurement points dan results
            $measurementPoints = $product->measurement_points ?? [];
            $measurementResults = $measurement->measurement_results ?? [];

            // Build measurement items list
            $measurementItems = [];
            $measurementPassed = 0;  // OK untuk QUALITATIVE
            $measurementFailed = 0;  // NG untuk QUALITATIVE
            $measurementOk = 0;      // OK untuk QUANTITATIVE
            $measurementNg = 0;      // NG untuk QUANTITATIVE
            $todo = 0;               // Belum diukur

            foreach ($measurementPoints as $point) {
                $nameId = $point['setup']['name_id'] ?? null;
                if (!$nameId) {
                    continue;
                }

                // Check if this measurement point has SKIP_CHECK evaluation type
                $evaluationType = $point['evaluation_type'] ?? null;
                $isSkipCheck = $evaluationType === 'SKIP_CHECK';

                // Find result for this measurement item
                $itemResult = null;
                foreach ($measurementResults as $result) {
                    if ($result['measurement_item_name_id'] === $nameId) {
                        $itemResult = $result;
                        break;
                    }
                }

                $name = $point['setup']['name'] ?? $nameId;
                $nature = $point['setup']['nature'] ?? 'QUANTITATIVE';
                // ✅ FIX: Type menggunakan enum QUALITATIVE atau QUANTITATIVE (bukan JUDGMENT)
                $type = $nature === 'QUALITATIVE' ? 'QUALITATIVE' : 'QUANTITATIVE';

                // ✅ FIX: Status menggunakan boolean (true/false) atau null (bukan string)
                $status = null;
                if ($itemResult) {
                    if ($itemResult['status'] === true) {
                        $status = true;
                        // Count berdasarkan type
                        if ($type === 'QUALITATIVE') {
                            $measurementPassed++;
                        } else {
                            $measurementOk++;
                        }
                    } elseif ($itemResult['status'] === false) {
                        $status = false;
                        // Count berdasarkan type
                        if ($type === 'QUALITATIVE') {
                            $measurementFailed++;
                        } else {
                            $measurementNg++;
                        }
                    } else {
                        $status = null;
                        // ✅ FIX: SKIP_CHECK items should NOT be counted in todo
                        if (!$isSkipCheck) {
                            $todo++;
                        }
                    }
                } else {
                    $status = null;
                    // ✅ FIX: SKIP_CHECK items should NOT be counted in todo
                    if (!$isSkipCheck) {
                        $todo++;
                    }
                }

                $measurementItems[] = [
                    'name' => $name,
                    'name_id' => $nameId,
                    'type' => $type,
                    'status' => $status,
                ];
            }

            return $this->successResponse([
                'product' => [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'product_spec_name' => $product->product_spec_name,
                    'product_category' => $product->productCategory->name ?? null,
                    'article_code' => $product->article_code ?? null,
                    'no_document' => $product->no_document ?? null,
                    'no_doc_reference' => $product->no_doc_reference ?? null,
                ],
                'measurement_items' => $measurementItems,
                'summary' => [
                    'measurement_passed' => $measurementPassed,    // ✅ OK untuk QUALITATIVE
                    'measurement_failed' => $measurementFailed,    // ❌ NG untuk QUALITATIVE
                    'measurement_ok' => $measurementOk,            // ✅ OK untuk QUANTITATIVE
                    'measurement_ng' => $measurementNg,             // ❌ NG untuk QUANTITATIVE
                    'todo' => $todo,                                // ⏳ Belum diukur
                ],
            ], 'Report data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving report data: ' . $e->getMessage(), 'REPORT_DATA_FETCH_ERROR', 500);
        }
    }

    /**
     * Get master template file (if exists)
     * GET /api/v1/reports/upload-master?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-123
     */
    public function getMasterTemplate(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product and measurement
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan untuk batch number ini');
            }

            // Check if master file exists for this measurement
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if (!$masterFile) {
                return $this->successResponse([
                    'has_template' => false,
                    'template' => null,
                ], 'No template found for this measurement');
            }

            // Get user info safely
            $uploadedBy = null;
            try {
                if ($masterFile->user_id) {
                    $user = \App\Models\LoginUser::find($masterFile->user_id);
                    $uploadedBy = $user ? $user->username : null;
                }
            } catch (\Exception $e) {
                // If user not found, just set to null
                $uploadedBy = null;
            }

            return $this->successResponse([
                'has_template' => true,
                'template' => [
                    'master_file_id' => $masterFile->id,
                    'product_measurement_id' => $measurement->id,
                    'measurement_id' => $measurement->measurement_id,
                    'batch_number' => $batchNumber,
                    'original_filename' => $masterFile->original_filename,
                    'stored_filename' => $masterFile->stored_filename,
                    'file_path' => $masterFile->file_path,
                    'sheet_names' => $masterFile->sheet_names ?? [],
                    'total_sheets' => count($masterFile->sheet_names ?? []),
                    'has_raw_data_sheet' => in_array('raw_data', $masterFile->sheet_names ?? []),
                    'uploaded_by' => $uploadedBy,
                    'uploaded_at' => $masterFile->created_at->format('Y-m-d H:i:s'),
                ],
            ], 'Template retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error retrieving template: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error retrieving template: ' . $e->getMessage(), 'TEMPLATE_FETCH_ERROR', 500);
        }
    }

    /**
     * Upload master Excel file
     * POST /api/v1/reports/upload-master
     */
    public function uploadMasterFile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product and measurement
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan untuk batch number ini');
            }

            // Check if master file already exists for this measurement
            $existingMaster = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();
            if ($existingMaster) {
                // Delete old file
                Storage::disk('local')->delete($existingMaster->file_path);
                $existingMaster->delete();
            }

            // Store file - using 'local' disk which maps to storage/app/private
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $storedFilename = 'master_' . time() . '_' . $file->hashName();
            
            // Ensure directory exists
            Storage::disk('local')->makeDirectory('reports/master_files');
            
            // Store file (will be saved in storage/app/private/reports/master_files/)
            $filePath = $file->storeAs('reports/master_files', $storedFilename, 'local');

            // Get sheet names from Excel file - use Storage path for correct disk location
            // Disk 'local' root is storage/app/private, so filePath is relative to that
            $fullPath = storage_path('app/private/' . $filePath);
            $sheetNames = ReportExcelHelper::getSheetNames($fullPath);

            // Save to database
            $masterFile = ReportMasterFile::create([
                'user_id' => $user->id,
                'product_measurement_id' => $measurement->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'sheet_names' => $sheetNames,
            ]);

            // Check if 'raw_data' sheet exists
            $hasRawDataSheet = in_array('raw_data', $sheetNames);

            return $this->successResponse([
                'master_file_id' => $masterFile->id,
                'product_measurement_id' => $measurement->id,
                'measurement_id' => $measurement->measurement_id,
                'batch_number' => $batchNumber,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'sheet_names' => $sheetNames,
                'total_sheets' => count($sheetNames),
                'has_raw_data_sheet' => $hasRawDataSheet,
                'uploaded_by' => $user->username,
                'uploaded_at' => $masterFile->created_at->format('Y-m-d H:i:s'),
                'note' => $hasRawDataSheet 
                    ? 'Data will be injected to existing "raw_data" sheet' 
                    : 'A new "raw_data" sheet will be created',
            ], 'Master file uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading master file: ' . $e->getMessage(), 'UPLOAD_ERROR', 500);
        }
    }

    /**
     * Download master template file
     * GET /api/v1/reports/download/master?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-123
     */
    public function downloadMasterFile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product and measurement
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan untuk batch number ini');
            }

            // Check if master file exists for this measurement
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if (!$masterFile) {
                return $this->notFoundResponse('Master file tidak ditemukan untuk measurement ini');
            }

            // Check if file exists in storage
            $filePath = storage_path('app/private/' . $masterFile->file_path);
            
            if (!file_exists($filePath)) {
                return $this->errorResponse(
                    'Master file tidak ditemukan di storage. Silakan upload ulang.',
                    'MASTER_FILE_NOT_FOUND',
                    404
                );
            }

            if (!is_readable($filePath)) {
                return $this->errorResponse(
                    'Master file tidak dapat dibaca. Cek permission file.',
                    'MASTER_FILE_NOT_READABLE',
                    500
                );
            }

            // Return file download
            return response()->download($filePath, $masterFile->original_filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading master file: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error downloading master file: ' . $e->getMessage(), 'MASTER_FILE_DOWNLOAD_ERROR', 500);
        }
    }

    /**
     * Download Excel (Admin/SuperAdmin)
     * GET /api/v1/reports/download/excel?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
     */
    public function downloadExcel(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Check role - only Admin and SuperAdmin
            if (!in_array($user->role, ['admin', 'superadmin'])) {
                return $this->unauthorizedResponse('Only Admin and SuperAdmin can download Excel');
            }

            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product and measurement
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan');
            }

            // Get measurement results
            $measurementResults = $measurement->measurement_results ?? [];
            
            // Log untuk debugging - detailed
            Log::info('Download Excel - Measurement ID: ' . $measurement->measurement_id);
            Log::info('Download Excel - Measurement Results Count: ' . count($measurementResults));
            
            // Log struktur data untuk debugging
            if (!empty($measurementResults)) {
                $firstItem = $measurementResults[0] ?? null;
                if ($firstItem) {
                    Log::info('Download Excel - First Item Structure: ' . json_encode([
                        'has_name_id' => isset($firstItem['measurement_item_name_id']),
                        'name_id' => $firstItem['measurement_item_name_id'] ?? null,
                        'has_samples' => isset($firstItem['samples']),
                        'samples_count' => isset($firstItem['samples']) ? count($firstItem['samples']) : 0,
                        'first_sample_keys' => !empty($firstItem['samples']) ? array_keys($firstItem['samples'][0] ?? []) : [],
                    ]));
                }
            } else {
                Log::warning('Download Excel - Measurement Results is EMPTY for Measurement ID: ' . $measurement->measurement_id);
            }
            
            // Transform to Excel rows
            $dataRows = ReportExcelHelper::transformMeasurementResultsToExcelRows($product, $measurementResults, $measurement);
            
            // Log hasil transform
            Log::info('Download Excel - Data Rows Count: ' . count($dataRows));
            
            // Log sample data rows jika ada
            if (!empty($dataRows)) {
                Log::info('Download Excel - First Data Row: ' . json_encode($dataRows[0] ?? null));
            }

            // Check if master file exists
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if ($masterFile && Storage::disk('local')->exists($masterFile->file_path)) {
                // Merge data ke master file - use Storage path for correct disk location
                // Disk 'local' root is storage/app/private, so filePath is relative to that
                $masterFilePath = storage_path('app/private/' . $masterFile->file_path);
                
                // Check if file exists and is readable
                if (!file_exists($masterFilePath)) {
                    Log::error('Master file not found: ' . $masterFilePath);
                    return $this->errorResponse(
                        'Master file tidak ditemukan di storage. Silakan upload ulang.',
                        'MASTER_FILE_NOT_FOUND',
                        404
                    );
                }
                
                if (!is_readable($masterFilePath)) {
                    Log::error('Master file not readable: ' . $masterFilePath);
                    return $this->errorResponse(
                        'Master file tidak dapat dibaca. Cek permission file.',
                        'MASTER_FILE_NOT_READABLE',
                        500
                    );
                }
                
                try {
                    $spreadsheet = ReportExcelHelper::mergeDataToMasterFile($masterFilePath, $dataRows, 'raw_data');
                    $filename = pathinfo($masterFile->original_filename, PATHINFO_FILENAME) . '.xlsx';
                } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                    Log::error('Excel processing error: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                    return $this->errorResponse(
                        'Error processing Excel file: ' . $e->getMessage(),
                        'EXCEL_PROCESSING_ERROR',
                        500
                    );
                }
            } else {
                // Create new Excel file
                try {
                    $spreadsheet = ReportExcelHelper::createExcelFile($dataRows, 'raw_data');
                    $filename = 'raw_data.xlsx';
                } catch (\Exception $e) {
                    Log::error('Excel creation error: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                    return $this->errorResponse(
                        'Error creating Excel file: ' . $e->getMessage(),
                        'EXCEL_CREATION_ERROR',
                        500
                    );
                }
            }

            // Write to temporary file
            $tempPath = storage_path('app/temp_' . time() . '.xlsx');
            
            // Ensure temp directory exists
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($tempPath);
                
                // Check if file was created successfully
                if (!file_exists($tempPath)) {
                    Log::error('Temp file was not created: ' . $tempPath);
                    return $this->errorResponse(
                        'Error creating temporary file for download',
                        'TEMP_FILE_CREATION_ERROR',
                        500
                    );
                }
                
                // Return file download
                return response()->download($tempPath, $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(true);
            } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
                Log::error('Excel writer error: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                return $this->errorResponse(
                    'Error writing Excel file: ' . $e->getMessage(),
                    'EXCEL_WRITER_ERROR',
                    500
                );
            }
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            Log::error('Excel processing error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse(
                'Error processing Excel file: ' . $e->getMessage(),
                'EXCEL_PROCESSING_ERROR',
                500
            );
        } catch (\Exception $e) {
            Log::error('Download Excel error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse(
                'Error downloading Excel: ' . $e->getMessage(),
                'EXCEL_DOWNLOAD_ERROR',
                500
            );
        }
    }

    /**
     * Download PDF (Operator)
     * GET /api/v1/reports/download/pdf?quarter=3&year=2025&product_id=PRD-XXXXX&batch_number=XYZ-22082025-01
     */
    public function downloadPdf(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Check role - only Operator
            if ($user->role !== 'operator') {
                return $this->unauthorizedResponse('Only Operator can download PDF');
            }

            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
                'product_id' => 'required|string',
                'batch_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');
            $productId = $request->get('product_id');
            $batchNumber = $request->get('batch_number');

            // Get product and measurement
            $product = Product::where('product_id', $productId)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);
            $measurement = ProductMeasurement::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Measurement tidak ditemukan');
            }

            // Get measurement results
            $measurementResults = $measurement->measurement_results ?? [];
            
            // Log untuk debugging
            Log::info('Download PDF - Measurement ID: ' . $measurement->measurement_id);
            Log::info('Download PDF - Measurement Results Count: ' . count($measurementResults));
            
            $dataRows = ReportExcelHelper::transformMeasurementResultsToExcelRows($product, $measurementResults, $measurement);
            
            // Log hasil transform
            Log::info('Download PDF - Data Rows Count: ' . count($dataRows));

            // Check if master file exists
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if ($masterFile && Storage::disk('local')->exists($masterFile->file_path)) {
                // Convert each sheet to PDF - use Storage path for correct disk location
                // Disk 'local' root is storage/app/private, so filePath is relative to that
                $masterFilePath = storage_path('app/private/' . $masterFile->file_path);
                
                // Merge data to raw_data sheet first
                $spreadsheet = ReportExcelHelper::mergeDataToMasterFile($masterFilePath, $dataRows, 'raw_data');
                $sheetNames = $spreadsheet->getSheetNames();
                
                // Create ZIP file for multiple PDFs
                $zipPath = storage_path('app/temp_reports_' . time() . '.zip');
                $zip = new ZipArchive();
                $zip->open($zipPath, ZipArchive::CREATE);

                $tempPdfPaths = [];
                foreach ($sheetNames as $index => $sheetName) {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    if (!$sheet) {
                        continue;
                    }

                    // Convert sheet to HTML table
                    $html = $this->sheetToHtml($sheet);

                    // Generate PDF
                    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
                    $pdfName = pathinfo($masterFile->original_filename, PATHINFO_FILENAME) . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sheetName) . '.pdf';
                    $tempPdfPath = storage_path('app/temp_' . time() . '_' . $index . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sheetName) . '.pdf');
                    $pdf->save($tempPdfPath);
                    $tempPdfPaths[] = $tempPdfPath;

                    // Add to ZIP
                    $zip->addFile($tempPdfPath, $pdfName);
                }

                $zip->close();

                // Return ZIP download with cleanup callback
                $zipFilename = pathinfo($masterFile->original_filename, PATHINFO_FILENAME) . '_reports.zip';
                
                // Register shutdown function for cleanup
                register_shutdown_function(function () use ($tempPdfPaths, $zipPath) {
                    foreach ($tempPdfPaths as $tempPdf) {
                        if (file_exists($tempPdf)) {
                            @unlink($tempPdf);
                        }
                    }
                });
                
                return response()->download($zipPath, $zipFilename, [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend(true);
            } else {
                // Create single PDF from raw_data
                $html = $this->dataRowsToHtml($dataRows);
                $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
                $filename = 'raw_data.pdf';

                return $pdf->download($filename);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error downloading PDF: ' . $e->getMessage(), 'PDF_DOWNLOAD_ERROR', 500);
        }
    }

    /**
     * Download Scale Measurement CSV per day
     * GET /api/v1/reports/download/scale-csv?date=YYYY-MM-DD
     *
     * Columns (match UI screenshot):
     * Product Name, Category, Batch Number, Machine Number, No. Document,
     * No. Document Reference, Article Code, Weight, Status
     */
    public function downloadScaleCsv(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $date = $request->get('date');

            $rows = ScaleMeasurement::with(['product.productCategory'])
                ->whereDate('measurement_date', $date)
                ->orderBy('created_at', 'asc')
                ->get();

            $headers = [
                'Product Name',
                'Category',
                'Batch Number',
                'Machine Number',
                'No. Document',
                'No. Document Reference',
                'Article Code',
                'Weight',
                'Status',
            ];

            $filename = "scale_measurements_{$date}.csv";

            return response()->streamDownload(function () use ($rows, $headers) {
                $out = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fwrite($out, "\xEF\xBB\xBF");

                // Use semicolon delimiter for better Excel compatibility on locales that use ',' as decimal separator
                fputcsv($out, $headers, ';');

                foreach ($rows as $m) {
                    $product = $m->product;
                    $category = $product?->productCategory;

                    $status = $m->status ?? null;
                    // UI shows "NOT CHECKED" / "CHECKED"
                    $statusLabel = $status ? str_replace('_', ' ', strtoupper($status)) : '';

                    fputcsv($out, [
                        $product->product_spec_name ?? $product->product_name ?? '',
                        $category->name ?? '',
                        $m->batch_number ?? '',
                        $m->machine_number ?? '',
                        $product->no_document ?? '',
                        $product->no_doc_reference ?? '',
                        $product->article_code ?? '',
                        $m->weight !== null ? (string) $m->weight : '',
                        $statusLabel,
                    ], ';');
                }

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error downloading scale CSV: ' . $e->getMessage(),
                'SCALE_CSV_DOWNLOAD_ERROR',
                500
            );
        }
    }

    /**
     * Helper: Convert sheet to HTML table
     */
    private function sheetToHtml($sheet): string
    {
        $html = '<table border="1" style="border-collapse: collapse; width: 100%;">';
        
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            $html .= '<tr>';
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getCalculatedValue();
                if ($row === 1) {
                    $html .= '<th style="background-color: #4472C4; color: white; padding: 8px; text-align: center; font-weight: bold;">' . htmlspecialchars($cellValue) . '</th>';
                } else {
                    $html .= '<td style="padding: 5px;">' . htmlspecialchars($cellValue) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        return '<html><head><meta charset="UTF-8"><style>body { font-family: Arial; }</style></head><body>' . $html . '</body></html>';
    }

    /**
     * Helper: Convert data rows to HTML table
     */
    private function dataRowsToHtml(array $dataRows): string
    {
        $html = '<table border="1" style="border-collapse: collapse; width: 100%;">';
        $html .= '<tr>';
        $html .= '<th style="background-color: #4472C4; color: white; padding: 8px; text-align: center; font-weight: bold;">Name</th>';
        $html .= '<th style="background-color: #4472C4; color: white; padding: 8px; text-align: center; font-weight: bold;">Type</th>';
        $html .= '<th style="background-color: #4472C4; color: white; padding: 8px; text-align: center; font-weight: bold;">Sample Index</th>';
        $html .= '<th style="background-color: #4472C4; color: white; padding: 8px; text-align: center; font-weight: bold;">Result</th>';
        $html .= '</tr>';

        foreach ($dataRows as $row) {
            $html .= '<tr>';
            $html .= '<td style="padding: 5px;">' . htmlspecialchars($row['name']) . '</td>';
            $html .= '<td style="padding: 5px;">' . htmlspecialchars($row['type']) . '</td>';
            $html .= '<td style="padding: 5px; text-align: center;">' . htmlspecialchars($row['sample_index'] ?? '-') . '</td>';
            $html .= '<td style="padding: 5px; text-align: right;">' . htmlspecialchars($row['result'] ?? '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return '<html><head><meta charset="UTF-8"><style>body { font-family: Arial; }</style></head><body>' . $html . '</body></html>';
    }

    /**
     * Helper: Get quarter range from quarter number
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
}
