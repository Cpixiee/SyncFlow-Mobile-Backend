<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Models\Product;

class QualitativeProductsSeeder extends Seeder
{
    /**
     * Seed qualitative judgement test products
     */
    public function run(): void
    {
        $quarter = Quarter::getActiveQuarter() ?? Quarter::getCurrentQuarter();
        
        if (!$quarter) {
            $this->command->warn('Tidak ada quarter aktif. Jalankan QuarterSeeder terlebih dahulu.');
            return;
        }

        // Get categories
        $tubeTest = ProductCategory::where('name', 'Tube Test')->first();
        $wireTestReguler = ProductCategory::where('name', 'Wire Test Reguler')->first();

        if (!$tubeTest || !$wireTestReguler) {
            $this->command->warn('Product categories belum ada. Jalankan ProductCategorySeeder terlebih dahulu.');
            return;
        }

        // Product 1: Visual Inspection untuk Tube Test
        $this->createVisualInspectionProduct($quarter, $tubeTest);

        // Product 2: Color Judgement untuk Wire Test
        $this->createColorJudgementProduct($quarter, $wireTestReguler);

        // Product 3: Surface Quality Check untuk Tube Test
        $this->createSurfaceQualityProduct($quarter, $tubeTest);

        // Product 4: Mixed Quantitative & Qualitative
        $this->createMixedProduct($quarter, $wireTestReguler);

        $this->command->info('✅ Qualitative judgement test products created successfully!');
    }

    /**
     * Create Visual Inspection Product (Pure Qualitative)
     */
    private function createVisualInspectionProduct($quarter, $category)
    {
        $measurementPoints = [
            [
                'setup' => [
                    'name' => 'Visual Appearance',
                    'name_id' => 'visual_appearance',
                    'sample_amount' => 5,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Visual Quality',
                        'options' => ['Good', 'Fair', 'Poor'],
                        'passing_criteria' => 'All samples must be Good or Fair'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
            [
                'setup' => [
                    'name' => 'Scratch Presence',
                    'name_id' => 'scratch_presence',
                    'sample_amount' => 5,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Scratch Detection',
                        'options' => ['No Scratch', 'Minor Scratch', 'Major Scratch'],
                        'passing_criteria' => 'No Major Scratch allowed'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
        ];

        Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'VO',
            'ref_spec_number' => 'VISUAL-001',
            'nom_size_vo' => '2.5mm',
            'article_code' => 'ART-VISUAL-001',
            'no_document' => 'DOC-VISUAL-001',
            'no_doc_reference' => null,
            'measurement_points' => $measurementPoints,
            'measurement_groups' => [],
        ]);

        $this->command->info('  ✓ Visual Inspection Product created');
    }

    /**
     * Create Color Judgement Product (Qualitative)
     */
    private function createColorJudgementProduct($quarter, $category)
    {
        $measurementPoints = [
            [
                'setup' => [
                    'name' => 'Wire Color',
                    'name_id' => 'wire_color',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Color Match',
                        'options' => ['Exact Match', 'Close Match', 'No Match'],
                        'passing_criteria' => 'Exact Match or Close Match'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
            [
                'setup' => [
                    'name' => 'Color Consistency',
                    'name_id' => 'color_consistency',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Consistency Level',
                        'options' => ['Uniform', 'Slightly Varied', 'Inconsistent'],
                        'passing_criteria' => 'Must be Uniform'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
        ];

        Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'CAVS',
            'ref_spec_number' => 'COLOR-001',
            'nom_size_vo' => '1.5mm',
            'article_code' => 'ART-COLOR-001',
            'no_document' => null,
            'no_doc_reference' => null,
            'measurement_points' => $measurementPoints,
            'measurement_groups' => [],
        ]);

        $this->command->info('  ✓ Color Judgement Product created');
    }

    /**
     * Create Surface Quality Check Product (Qualitative)
     */
    private function createSurfaceQualityProduct($quarter, $category)
    {
        $measurementPoints = [
            [
                'setup' => [
                    'name' => 'Surface Roughness',
                    'name_id' => 'surface_roughness',
                    'sample_amount' => 4,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Roughness Level',
                        'options' => ['Smooth', 'Slightly Rough', 'Very Rough'],
                        'passing_criteria' => 'Smooth or Slightly Rough'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
            [
                'setup' => [
                    'name' => 'Defect Presence',
                    'name_id' => 'defect_presence',
                    'sample_amount' => 4,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Defect Status',
                        'options' => ['No Defect', 'Minor Defect', 'Major Defect'],
                        'passing_criteria' => 'No Major Defect'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
            [
                'setup' => [
                    'name' => 'Overall Quality',
                    'name_id' => 'overall_quality',
                    'sample_amount' => 4,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Quality Grade',
                        'options' => ['Grade A', 'Grade B', 'Grade C', 'Reject'],
                        'passing_criteria' => 'Grade A or B'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
        ];

        Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'COT',
            'ref_spec_number' => 'SURFACE-001',
            'nom_size_vo' => '3.0mm',
            'article_code' => 'ART-SURFACE-001',
            'no_document' => 'DOC-SURFACE-001',
            'no_doc_reference' => 'REF-SURFACE-001',
            'measurement_points' => $measurementPoints,
            'measurement_groups' => [],
        ]);

        $this->command->info('  ✓ Surface Quality Product created');
    }

    /**
     * Create Mixed Quantitative & Qualitative Product
     */
    private function createMixedProduct($quarter, $category)
    {
        $measurementPoints = [
            // Quantitative measurement
            [
                'setup' => [
                    'name' => 'Wire Diameter',
                    'name_id' => 'wire_diameter',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUANTITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'PER_SAMPLE',
                'evaluation_setting' => [
                    'per_sample_setting' => [
                        'is_raw_data' => true,
                        'pre_processing_formula_name' => null
                    ]
                ],
                'rule_evaluation_setting' => [
                    'rule' => 'BETWEEN',
                    'unit' => 'mm',
                    'value' => 1.5,
                    'tolerance_minus' => 0.1,
                    'tolerance_plus' => 0.1,
                ],
            ],
            // Qualitative measurement
            [
                'setup' => [
                    'name' => 'Insulation Quality',
                    'name_id' => 'insulation_quality',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Insulation Condition',
                        'options' => ['Excellent', 'Good', 'Fair', 'Poor'],
                        'passing_criteria' => 'Excellent or Good'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
            // Another qualitative
            [
                'setup' => [
                    'name' => 'Connector Fit',
                    'name_id' => 'connector_fit',
                    'sample_amount' => 3,
                    'source' => 'MANUAL',
                    'type' => 'SINGLE',
                    'nature' => 'QUALITATIVE',
                ],
                'variables' => [],
                'pre_processing_formulas' => [],
                'evaluation_type' => 'SKIP_CHECK',
                'evaluation_setting' => [
                    'qualitative_setting' => [
                        'label' => 'Fit Quality',
                        'options' => ['Perfect Fit', 'Loose', 'Tight', 'Cannot Fit'],
                        'passing_criteria' => 'Perfect Fit or Tight'
                    ]
                ],
                'rule_evaluation_setting' => null,
            ],
        ];

        Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'CIVUS',
            'ref_spec_number' => 'MIXED-001',
            'nom_size_vo' => '1.5mm',
            'article_code' => 'ART-MIXED-001',
            'no_document' => null,
            'no_doc_reference' => null,
            'measurement_points' => $measurementPoints,
            'measurement_groups' => [],
        ]);

        $this->command->info('  ✓ Mixed Quantitative & Qualitative Product created');
    }
}

