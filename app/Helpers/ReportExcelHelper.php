<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\ProductMeasurement;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportExcelHelper
{
    /**
     * Transform measurement_results ke format Excel rows
     * Format: [Name, Type, Sample Index, Result]
     */
    public static function transformMeasurementResultsToExcelRows(Product $product, array $measurementResults): array
    {
        $rows = [];
        
        if (empty($measurementResults)) {
            Log::warning('No measurement results to transform for product: ' . $product->product_id);
            return $rows;
        }
        
        foreach ($measurementResults as $itemIndex => $item) {
            // Validate item structure
            if (!isset($item['measurement_item_name_id'])) {
                Log::warning('Measurement item missing name_id at index: ' . $itemIndex . ' - Item: ' . json_encode(array_keys($item ?? [])));
                continue;
            }
            
            $itemNameId = $item['measurement_item_name_id'];
            $measurementPoint = $product->getMeasurementPointByNameId($itemNameId);
            
            if (!$measurementPoint) {
                Log::warning('Measurement point not found for name_id: ' . $itemNameId . ' (Product: ' . $product->product_id . ')');
                continue; // Skip if measurement point not found
            }
            
            // Log untuk debugging item yang sedang diproses
            Log::debug('Processing item: ' . $itemNameId . ' - Samples count: ' . (isset($item['samples']) ? count($item['samples']) : 0));
            
            $displayName = $measurementPoint['setup']['name'] ?? $itemNameId;
            $type = $measurementPoint['setup']['type'] ?? 'SINGLE';
            $evaluationType = $measurementPoint['evaluation_type'] ?? 'PER_SAMPLE';
            $nature = $measurementPoint['setup']['nature'] ?? 'QUANTITATIVE';
            
            // 1. Process samples (Single/Before/After)
            if (!empty($item['samples'])) {
                foreach ($item['samples'] as $sampleIndex => $sample) {
                    $sampleIdx = $sample['sample_index'] ?? ($sampleIndex + 1);
                    
                    // ✅ FIX: Check for raw_values structure first (new format)
                    $hasRawValues = isset($sample['raw_values']) && is_array($sample['raw_values']);
                    
                    // Log untuk debugging
                    Log::debug("Processing sample - Item: $itemNameId, Sample Index: $sampleIdx, Type: $type, Nature: $nature, Has raw_values: " . ($hasRawValues ? 'yes' : 'no') . ", Has single_value: " . (isset($sample['single_value']) ? 'yes' : 'no'));
                    
                    // Raw values - SINGLE type (from raw_values structure)
                    if ($type === 'SINGLE' && $hasRawValues && isset($sample['raw_values']['single_value'])) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIdx,
                            'result' => $sample['raw_values']['single_value']
                        ];
                    }
                    // ✅ FIX: Handle direct single_value (most common format - prioritize this)
                    elseif ($type === 'SINGLE' && isset($sample['single_value']) && $sample['single_value'] !== null) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIdx,
                            'result' => $sample['single_value']
                        ];
                    }
                    
                    // Raw values - BEFORE_AFTER type (from raw_values structure)
                    if ($type === 'BEFORE_AFTER' && $hasRawValues && isset($sample['raw_values']['before_after_value'])) {
                        $beforeAfter = $sample['raw_values']['before_after_value'];
                        
                        if (is_array($beforeAfter)) {
                            if (isset($beforeAfter['before'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'Before',
                                    'sample_index' => $sampleIdx,
                                    'result' => $beforeAfter['before']
                                ];
                            }
                            if (isset($beforeAfter['after'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'After',
                                    'sample_index' => $sampleIdx,
                                    'result' => $beforeAfter['after']
                                ];
                            }
                        }
                    }
                    // ✅ FIX: Handle direct before_after_value (most common format - prioritize this)
                    if ($type === 'BEFORE_AFTER' && isset($sample['before_after_value']) && $sample['before_after_value'] !== null) {
                        $beforeAfter = $sample['before_after_value'];
                        if (is_array($beforeAfter)) {
                            if (isset($beforeAfter['before'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'Before',
                                    'sample_index' => $sampleIdx,
                                    'result' => $beforeAfter['before']
                                ];
                            }
                            if (isset($beforeAfter['after'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'After',
                                    'sample_index' => $sampleIdx,
                                    'result' => $beforeAfter['after']
                                ];
                            }
                        }
                    }
                    
                    // Qualitative value (from raw_values structure)
                    if ($nature === 'QUALITATIVE' && $hasRawValues && isset($sample['raw_values']['qualitative_value'])) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIdx,
                            'result' => $sample['raw_values']['qualitative_value'] ? 1 : 0 // Convert boolean to int
                        ];
                    }
                    // ✅ FIX: Handle direct qualitative_value (most common format - prioritize this)
                    elseif ($nature === 'QUALITATIVE' && isset($sample['qualitative_value']) && $sample['qualitative_value'] !== null) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIdx,
                            'result' => $sample['qualitative_value'] ? 1 : 0
                        ];
                    }
                    
                    // Processed values (pre-processing formulas) - from processed_values
                    if (!empty($sample['processed_values']) && is_array($sample['processed_values'])) {
                        foreach ($sample['processed_values'] as $formulaName => $value) {
                            $rows[] = [
                                'name' => $displayName,
                                'type' => 'Pre Processing Formula',
                                'sample_index' => $sampleIdx,
                                'result' => $value
                            ];
                        }
                    }
                    // ✅ FIX: Handle pre_processing_formula_values (alternative format)
                    elseif (!empty($sample['pre_processing_formula_values']) && is_array($sample['pre_processing_formula_values'])) {
                        foreach ($sample['pre_processing_formula_values'] as $formulaName => $value) {
                            $rows[] = [
                                'name' => $displayName,
                                'type' => 'Pre Processing Formula',
                                'sample_index' => $sampleIdx,
                                'result' => $value
                            ];
                        }
                    }
                }
            }
            
            // 2. Variables - Extract from measurement point variables (FIXED/MANUAL/FORMULA)
            // Note: Variables sudah di-process saat measurement, perlu extract dari item jika ada
            // For now, skip variables as they're already included in formulas
            
            // 3. Aggregation/Joint results
            if ($evaluationType === 'JOINT' && !empty($item['joint_results']) && is_array($item['joint_results'])) {
                foreach ($item['joint_results'] as $jointResult) {
                    if (isset($jointResult['is_final_value']) && $jointResult['is_final_value']) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Aggregation',
                            'sample_index' => 1, // Aggregation always has sample_index = 1
                            'result' => $jointResult['value'] ?? null
                        ];
                    } else {
                        // Non-final joint formulas as Pre Processing Formula
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Pre Processing Formula',
                            'sample_index' => '-',
                            'result' => $jointResult['value'] ?? null
                        ];
                    }
                }
            } else if (isset($item['final_value']) && $item['final_value'] !== null) {
                // Final value from PER_SAMPLE evaluation (e.g., average)
                $rows[] = [
                    'name' => $displayName,
                    'type' => 'Aggregation',
                    'sample_index' => 1,
                    'result' => $item['final_value']
                ];
            }
        }
        
        Log::info('Transform complete - Total rows generated: ' . count($rows) . ' from ' . count($measurementResults) . ' measurement items');
        
        return $rows;
    }
    
    /**
     * Create Excel spreadsheet dengan data measurement
     */
    public static function createExcelFile(array $dataRows, string $sheetName = 'raw_data'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        
        // Set headers
        $headers = ['Name', 'Type', 'Sample Index', 'Result'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        
        // Add data rows
        $row = 2;
        if (empty($dataRows)) {
            Log::warning('No data rows to add to Excel - creating empty file with headers only');
            // Add a message row if no data
            $sheet->setCellValue('A2', 'No data available');
            $sheet->mergeCells('A2:D2');
            $sheet->getStyle('A2')->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '999999']]
            ]);
        } else {
            foreach ($dataRows as $dataRow) {
                $sheet->setCellValue('A' . $row, $dataRow['name'] ?? '');
                $sheet->setCellValue('B' . $row, $dataRow['type'] ?? '');
                $sheet->setCellValue('C' . $row, $dataRow['sample_index'] ?? '-');
                $sheet->setCellValue('D' . $row, $dataRow['result'] ?? '');
                
                // Style data cells
                $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                
                // Right align result column
                $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                $row++;
            }
        }
        
        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set column widths (min width)
        $sheet->getColumnDimension('A')->setWidth(30); // Name
        $sheet->getColumnDimension('B')->setWidth(25); // Type
        $sheet->getColumnDimension('C')->setWidth(15); // Sample Index
        $sheet->getColumnDimension('D')->setWidth(15); // Result
        
        return $spreadsheet;
    }
    
    /**
     * Merge data ke master Excel file (update sheet 'raw_data')
     */
    public static function mergeDataToMasterFile(string $masterFilePath, array $dataRows, string $sheetName = 'raw_data'): Spreadsheet
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($masterFilePath);
        
        // Check if sheet exists, if not create it
        if (!$spreadsheet->sheetNameExists($sheetName)) {
            $newSheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($newSheet);
        }
        
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            // Fallback to active sheet if sheet name not found
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetName);
        }
        
        // Clear existing data in sheet (optional - or append)
        $sheet->getCellCollection()->removeRow(1, 9999);
        
        // Set headers
        $headers = ['Name', 'Type', 'Sample Index', 'Result'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        
        // Add data rows
        $row = 2;
        if (empty($dataRows)) {
            Log::warning('No data rows to merge to master file - only headers will be shown');
            // Add a message row if no data
            $sheet->setCellValue('A2', 'No data available');
            $sheet->mergeCells('A2:D2');
            $sheet->getStyle('A2')->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '999999']]
            ]);
        } else {
            foreach ($dataRows as $dataRow) {
                $sheet->setCellValue('A' . $row, $dataRow['name'] ?? '');
                $sheet->setCellValue('B' . $row, $dataRow['type'] ?? '');
                $sheet->setCellValue('C' . $row, $dataRow['sample_index'] ?? '-');
                $sheet->setCellValue('D' . $row, $dataRow['result'] ?? '');
                
                // Style data cells
                $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                
                // Right align result column
                $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                $row++;
            }
        }
        
        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        
        return $spreadsheet;
    }
    
    /**
     * Get all sheet names from Excel file
     */
    public static function getSheetNames(string $filePath): array
    {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                Log::error('Excel file not found: ' . $filePath);
                return [];
            }

            // Try to detect file type automatically
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($filePath);
            
            $sheetNames = [];
            foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
                $title = $worksheet->getTitle();
                // Filter out empty or invalid sheet names
                if (!empty($title) && strlen($title) < 100) {
                    $sheetNames[] = $title;
                } else {
                    // Fallback to Sheet{index}
                    $sheetNames[] = 'Sheet' . ($index + 1);
                }
            }
            
            Log::info('Sheet names extracted: ' . json_encode($sheetNames) . ' from file: ' . $filePath);
            
            return $sheetNames;
        } catch (\Exception $e) {
            Log::error('Error getting sheet names from file: ' . $filePath . ' - Error: ' . $e->getMessage());
            Log::error('Error trace: ' . $e->getTraceAsString());
            return [];
        }
    }
}

