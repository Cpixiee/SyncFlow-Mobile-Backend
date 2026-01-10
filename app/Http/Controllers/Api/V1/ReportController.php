<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMeasurement;
use App\Models\Quarter;
use App\Models\ReportMasterFile;
use App\Traits\ApiResponseTrait;
use App\Helpers\ReportExcelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
     * Get products for selected quarter
     * GET /api/v1/reports/filters/products?quarter=3&year=2025
     */
    public function getProducts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quarter' => 'required|integer|min:1|max:4',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $quarter = $request->get('quarter');
            $year = $request->get('year');

            // Get quarter range
            $quarterRange = $this->getQuarterRangeFromQuarterNumber($quarter, $year);

            // Get products yang punya measurement di quarter ini
            $products = Product::with('productCategory')
                ->whereHas('productMeasurements', function ($query) use ($quarterRange) {
                    $query->whereBetween('due_date', [$quarterRange['start'], $quarterRange['end']])
                        ->whereNotNull('due_date');
                })
                ->distinct()
                ->get()
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'product_spec_name' => $product->product_spec_name,
                        'product_category' => $product->productCategory->name ?? null,
                    ];
                });

            return $this->successResponse($products, 'Products retrieved successfully');
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
                ->distinct()
                ->pluck('batch_number')
                ->filter()
                ->values();

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
            $measurementOk = 0;
            $measurementNg = 0;
            $todo = 0;

            foreach ($measurementPoints as $point) {
                $nameId = $point['setup']['name_id'] ?? null;
                if (!$nameId) {
                    continue;
                }

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
                $type = $nature === 'QUALITATIVE' ? 'QUALITATIVE JUDGMENT' : 'QUANTITATIVE JUDGMENT';

                // Determine status
                $status = '-';
                if ($itemResult) {
                    if ($itemResult['status'] === true) {
                        $status = 'OK';
                        $measurementOk++;
                    } elseif ($itemResult['status'] === false) {
                        $status = 'NG';
                        $measurementNg++;
                    } else {
                        $todo++;
                    }
                } else {
                    $todo++;
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
                ],
                'measurement_items' => $measurementItems,
                'summary' => [
                    'measurement_ok' => $measurementOk,
                    'measurement_ng' => $measurementNg,
                    'todo' => $todo,
                ],
                'measurement_id' => $measurement->measurement_id,
                'batch_number' => $measurement->batch_number,
            ], 'Report data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving report data: ' . $e->getMessage(), 'REPORT_DATA_FETCH_ERROR', 500);
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

            // Store file
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $storedFilename = 'master_' . time() . '_' . $file->hashName();
            $filePath = $file->storeAs('reports/master_files', $storedFilename, 'local');

            // Get sheet names from Excel file
            $fullPath = Storage::disk('local')->path($filePath);
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

            return $this->successResponse([
                'master_file_id' => $masterFile->id,
                'filename' => $originalFilename,
                'sheets' => $sheetNames,
            ], 'Master file uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading master file: ' . $e->getMessage(), 'UPLOAD_ERROR', 500);
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
            
            // Transform to Excel rows
            $dataRows = ReportExcelHelper::transformMeasurementResultsToExcelRows($product, $measurementResults);

            // Check if master file exists
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if ($masterFile && Storage::disk('local')->exists($masterFile->file_path)) {
                // Merge data ke master file
                $masterFilePath = Storage::disk('local')->path($masterFile->file_path);
                $spreadsheet = ReportExcelHelper::mergeDataToMasterFile($masterFilePath, $dataRows, 'raw_data');
                $filename = pathinfo($masterFile->original_filename, PATHINFO_FILENAME) . '.xlsx';
            } else {
                // Create new Excel file
                $spreadsheet = ReportExcelHelper::createExcelFile($dataRows, 'raw_data');
                $filename = 'raw_data.xlsx';
            }

            // Write to temporary file
            $tempPath = storage_path('app/temp_' . time() . '.xlsx');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            // Return file download
            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return $this->errorResponse('Error downloading Excel: ' . $e->getMessage(), 'EXCEL_DOWNLOAD_ERROR', 500);
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
            $dataRows = ReportExcelHelper::transformMeasurementResultsToExcelRows($product, $measurementResults);

            // Check if master file exists
            $masterFile = ReportMasterFile::where('product_measurement_id', $measurement->id)->first();

            if ($masterFile && Storage::disk('local')->exists($masterFile->file_path)) {
                // Convert each sheet to PDF
                $masterFilePath = Storage::disk('local')->path($masterFile->file_path);
                
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
