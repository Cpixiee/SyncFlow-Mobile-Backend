<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\ProductMeasurement;
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
        
        foreach ($measurementResults as $item) {
            $itemNameId = $item['measurement_item_name_id'];
            $measurementPoint = $product->getMeasurementPointByNameId($itemNameId);
            
            if (!$measurementPoint) {
                continue; // Skip if measurement point not found
            }
            
            $displayName = $measurementPoint['setup']['name'] ?? $itemNameId;
            $type = $measurementPoint['setup']['type'] ?? 'SINGLE';
            $evaluationType = $measurementPoint['evaluation_type'] ?? 'PER_SAMPLE';
            $nature = $measurementPoint['setup']['nature'] ?? 'QUANTITATIVE';
            
            // 1. Process samples (Single/Before/After)
            if (!empty($item['samples'])) {
                foreach ($item['samples'] as $sample) {
                    $sampleIndex = $sample['sample_index'] ?? null;
                    
                    // Raw values - SINGLE type
                    if ($type === 'SINGLE' && isset($sample['raw_values']['single_value'])) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIndex,
                            'result' => $sample['raw_values']['single_value']
                        ];
                    }
                    
                    // Raw values - BEFORE_AFTER type
                    if ($type === 'BEFORE_AFTER' && isset($sample['raw_values']['before_after_value'])) {
                        $beforeAfter = $sample['raw_values']['before_after_value'];
                        
                        if (is_array($beforeAfter)) {
                            if (isset($beforeAfter['before'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'Before',
                                    'sample_index' => $sampleIndex,
                                    'result' => $beforeAfter['before']
                                ];
                            }
                            if (isset($beforeAfter['after'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'After',
                                    'sample_index' => $sampleIndex,
                                    'result' => $beforeAfter['after']
                                ];
                            }
                        } else {
                            // Handle case where before_after_value is not array (legacy format)
                            if (isset($sample['before_after_value']['before'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'Before',
                                    'sample_index' => $sampleIndex,
                                    'result' => $sample['before_after_value']['before']
                                ];
                            }
                            if (isset($sample['before_after_value']['after'])) {
                                $rows[] = [
                                    'name' => $displayName,
                                    'type' => 'After',
                                    'sample_index' => $sampleIndex,
                                    'result' => $sample['before_after_value']['after']
                                ];
                            }
                        }
                    }
                    
                    // Qualitative value
                    if ($nature === 'QUALITATIVE' && isset($sample['raw_values']['qualitative_value'])) {
                        $rows[] = [
                            'name' => $displayName,
                            'type' => 'Single',
                            'sample_index' => $sampleIndex,
                            'result' => $sample['raw_values']['qualitative_value'] ? 1 : 0 // Convert boolean to int
                        ];
                    }
                    
                    // Processed values (pre-processing formulas)
                    if (!empty($sample['processed_values']) && is_array($sample['processed_values'])) {
                        foreach ($sample['processed_values'] as $formulaName => $value) {
                            $rows[] = [
                                'name' => $displayName,
                                'type' => 'Pre Processing Formula',
                                'sample_index' => $sampleIndex,
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
        foreach ($dataRows as $dataRow) {
            $sheet->setCellValue('A' . $row, $dataRow['name']);
            $sheet->setCellValue('B' . $row, $dataRow['type']);
            $sheet->setCellValue('C' . $row, $dataRow['sample_index'] ?? '-');
            $sheet->setCellValue('D' . $row, $dataRow['result']);
            
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
        foreach ($dataRows as $dataRow) {
            $sheet->setCellValue('A' . $row, $dataRow['name']);
            $sheet->setCellValue('B' . $row, $dataRow['type']);
            $sheet->setCellValue('C' . $row, $dataRow['sample_index'] ?? '-');
            $sheet->setCellValue('D' . $row, $dataRow['result']);
            
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
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
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
            
            return $sheetNames;
        } catch (\Exception $e) {
            \Log::error('Error getting sheet names: ' . $e->getMessage());
            return [];
        }
    }
}

