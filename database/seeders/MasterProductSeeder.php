<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MasterProduct;
use App\Models\ProductCategory;

class MasterProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get category IDs
        $wireTestCategory = ProductCategory::where('name', 'Wire Test Reguler')->first();
        $shieldWireTestCategory = ProductCategory::where('name', 'Shield Wire Test')->first();
        $tubeTestCategory = ProductCategory::where('name', 'Tube Test')->first();

        if (!$wireTestCategory || !$tubeTestCategory) {
            $this->command->error('Product categories not found. Please seed product categories first.');
            return;
        }

        $wireTestCategoryId = $wireTestCategory->id;
        $shieldWireTestCategoryId = $shieldWireTestCategory ? $shieldWireTestCategory->id : $wireTestCategoryId;
        $tubeTestCategoryId = $tubeTestCategory->id;

        // Master products data
        $masterProducts = [
            // Wire Test Reguler
            ['product_spec_name' => 'AVSSH 0.3F', 'product_name' => 'AVSSH', 'color' => 'F', 'size' => '0.3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSSH 0.5F', 'product_name' => 'AVSSH', 'color' => 'F', 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSSH 0.75F', 'product_name' => 'AVSSH', 'color' => 'F', 'size' => '0.75', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSSH 1.25F', 'product_name' => 'AVSSH', 'color' => 'F', 'size' => '1.25', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'IVSSH 0.3F', 'product_name' => 'IVSSH', 'color' => 'F', 'size' => '0.3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'IVSSH 0.5F', 'product_name' => 'IVSSH', 'color' => 'F', 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'IVSSH 0.75F', 'product_name' => 'IVSSH', 'color' => 'F', 'size' => '0.75', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'IVSSH 1.25F', 'product_name' => 'IVSSH', 'color' => 'F', 'size' => '1.25', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CIVUS 0.35', 'product_name' => 'CIVUS', 'color' => null, 'size' => '0.35', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CIVUS 0.5 W', 'product_name' => 'CIVUS', 'color' => 'W', 'size' => '0.5', 'product_category_id' => $wireTestCategoryId, 'no_document' => 'QAA/4-2032/2012', 'no_doc_reference' => 'YPES-11-01-254', 'article_code' => '1801R703940'],
            ['product_spec_name' => 'CIVUS 0.75', 'product_name' => 'CIVUS', 'color' => null, 'size' => '0.75', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CIVUS 1.0', 'product_name' => 'CIVUS', 'color' => null, 'size' => '1', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CIVUS 1.25', 'product_name' => 'CIVUS', 'color' => null, 'size' => '1.25', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AC CIVUS 0.35', 'product_name' => 'AC CIVUS', 'color' => null, 'size' => '0.35', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AC CIVUS 0.5', 'product_name' => 'AC CIVUS', 'color' => null, 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CAVS 0.3', 'product_name' => 'CAVS', 'color' => null, 'size' => '0.3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CAVS 0.5', 'product_name' => 'CAVS', 'color' => null, 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CAVS 0.85', 'product_name' => 'CAVS', 'color' => null, 'size' => '0.85', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'CAVS 1.25', 'product_name' => 'CAVS', 'color' => null, 'size' => '1.25', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AC CAVS 0.3', 'product_name' => 'AC CAVS', 'color' => null, 'size' => '0.3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AC CAVS 0.5', 'product_name' => 'AC CAVS', 'color' => null, 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSS 2F', 'product_name' => 'AVSS', 'color' => 'F', 'size' => '2', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 0.5', 'product_name' => 'AVS', 'color' => null, 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 0.85', 'product_name' => 'AVS', 'color' => null, 'size' => '0.85', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 1.25', 'product_name' => 'AVS', 'color' => null, 'size' => '1.25', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 3', 'product_name' => 'AVS', 'color' => null, 'size' => '3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 2', 'product_name' => 'AVS', 'color' => null, 'size' => '2', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVS 5', 'product_name' => 'AVS', 'color' => null, 'size' => '5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AV 8', 'product_name' => 'AV', 'color' => null, 'size' => '8', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSS 0.3F', 'product_name' => 'AVSS', 'color' => 'F', 'size' => '0.3', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSS 0.5F', 'product_name' => 'AVSS', 'color' => 'F', 'size' => '0.5', 'product_category_id' => $wireTestCategoryId],
            ['product_spec_name' => 'AVSS 0.75F', 'product_name' => 'AVSS', 'color' => 'F', 'size' => '0.75', 'product_category_id' => $wireTestCategoryId],
            
            // Shield Wire Test
            ['product_spec_name' => 'AVSSCS 0.3FX2', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.3FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.3FX5', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.3FX5', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.3FX6', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.3FX6', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.5FX2', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.5FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.5FX3', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.5FX3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.5FX6', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '0.5FX6', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS 0.75FX2', 'product_name' => 'AVSSCS', 'color' => null, 'size' => '.75FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.3FX1', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.3FX1', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.3FX2', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.3FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.3FX4', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.3FX4', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.5FX1', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.5FX1', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.5FX2', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.5FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.5FX3', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.5FX3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 0.5FX4', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '0.5FX4', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSCS-S 1.25FX2', 'product_name' => 'AVSSCS-S', 'color' => null, 'size' => '.25FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSHCS 0.3FX2', 'product_name' => 'AVSSHCS', 'color' => null, 'size' => '0.3FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSHCS 0.5FX2', 'product_name' => 'AVSSHCS', 'color' => null, 'size' => '0.5FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSHCS 0.5FX6', 'product_name' => 'AVSSHCS', 'color' => null, 'size' => '0.5FX6', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'AVSSHCS 1.25FX2', 'product_name' => 'AVSSHCS', 'color' => null, 'size' => '.25FX2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS 0.3X3', 'product_name' => 'CAVSAS', 'color' => null, 'size' => '0.3X3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.3X3', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.3X3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.3X4', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.3X4', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.3X5', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.3X5', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.5X1', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.5X1', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.5X2', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.5X2', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CAVSAS-S 0.5X3', 'product_name' => 'CAVSAS-S', 'color' => null, 'size' => '0.5X3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.35X1', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.35X1', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.35X3', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.35X3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.35X4', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.35X4', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.5X3', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.5X3', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.5X4', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.5X4', 'product_category_id' => $shieldWireTestCategoryId],
            ['product_spec_name' => 'CIVUSAS-S 0.75X2', 'product_name' => 'CIVUSAS-S', 'color' => null, 'size' => '0.75X2', 'product_category_id' => $shieldWireTestCategoryId],
            
            // Tube Test
            ['product_spec_name' => 'COT B 5', 'product_name' => 'COT', 'color' => 'B', 'size' => '5', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 7', 'product_name' => 'COT', 'color' => 'B', 'size' => '7', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 10', 'product_name' => 'COT', 'color' => 'B', 'size' => '10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 13', 'product_name' => 'COT', 'color' => 'B', 'size' => '13', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 15', 'product_name' => 'COT', 'color' => 'B', 'size' => '15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 19', 'product_name' => 'COT', 'color' => 'B', 'size' => '19', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 22', 'product_name' => 'COT', 'color' => 'B', 'size' => '22', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT B 25', 'product_name' => 'COT', 'color' => 'B', 'size' => '25', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 5', 'product_name' => 'COT', 'color' => 'FR', 'size' => '5', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 7', 'product_name' => 'COT', 'color' => 'FR', 'size' => '7', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 10', 'product_name' => 'COT', 'color' => 'FR', 'size' => '10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 13', 'product_name' => 'COT', 'color' => 'FR', 'size' => '13', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 15', 'product_name' => 'COT', 'color' => 'FR', 'size' => '15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 19', 'product_name' => 'COT', 'color' => 'FR', 'size' => '19', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 22', 'product_name' => 'COT', 'color' => 'FR', 'size' => '22', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT FR 25', 'product_name' => 'COT', 'color' => 'FR', 'size' => '25', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT RF R03', 'product_name' => 'COT', 'color' => 'RF', 'size' => '3', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'COT RF 25PL', 'product_name' => 'COT RF', 'color' => 'L', 'size' => '25', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'CORRU Y-17', 'product_name' => 'CORRU', 'color' => null, 'size' => '17', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'CORRU 09PL', 'product_name' => 'CORRU', 'color' => 'L', 'size' => '9', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 5', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '5', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 7', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '7', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 10', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 13', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '13', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 15', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 19', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '19', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 22', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '22', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'HCOT DL 25', 'product_name' => 'HCOT', 'color' => 'DL', 'size' => '25', 'product_category_id' => $tubeTestCategoryId],
            
            // VO products
            ['product_spec_name' => 'VO B 3X4', 'product_name' => 'VO', 'color' => 'B', 'size' => '3X4', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 4X5', 'product_name' => 'VO', 'color' => 'B', 'size' => '4X5', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 5X6', 'product_name' => 'VO', 'color' => 'B', 'size' => '5X6', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 6X7', 'product_name' => 'VO', 'color' => 'B', 'size' => '6X7', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 6X8', 'product_name' => 'VO', 'color' => 'B', 'size' => '6X8', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 7X8', 'product_name' => 'VO', 'color' => 'B', 'size' => '7X8', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 8X9', 'product_name' => 'VO', 'color' => 'B', 'size' => '8X9', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 8X10', 'product_name' => 'VO', 'color' => 'B', 'size' => '8X10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 9X10', 'product_name' => 'VO', 'color' => 'B', 'size' => '9X10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 9X11', 'product_name' => 'VO', 'color' => 'B', 'size' => '9X11', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 10X11', 'product_name' => 'VO', 'color' => 'B', 'size' => '10X11', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 10X12', 'product_name' => 'VO', 'color' => 'B', 'size' => '10X12', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 11X12', 'product_name' => 'VO', 'color' => 'B', 'size' => '11X12', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 12X13', 'product_name' => 'VO', 'color' => 'B', 'size' => '12X13', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 12X14', 'product_name' => 'VO', 'color' => 'B', 'size' => '12X14', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 13X14', 'product_name' => 'VO', 'color' => 'B', 'size' => '13X14', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 13X15', 'product_name' => 'VO', 'color' => 'B', 'size' => '13X15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 14X15', 'product_name' => 'VO', 'color' => 'B', 'size' => '14X15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 14X16', 'product_name' => 'VO', 'color' => 'B', 'size' => '14X16', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 16X17', 'product_name' => 'VO', 'color' => 'B', 'size' => '16X17', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 16X18', 'product_name' => 'VO', 'color' => 'B', 'size' => '16X18', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 18X19', 'product_name' => 'VO', 'color' => 'B', 'size' => '18X19', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 18X20', 'product_name' => 'VO', 'color' => 'B', 'size' => '18X20', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 32X33', 'product_name' => 'VO', 'color' => 'B', 'size' => '32X33', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 32X34', 'product_name' => 'VO', 'color' => 'B', 'size' => '32X34', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 19X20', 'product_name' => 'VO', 'color' => 'B', 'size' => '19X20', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 20X21', 'product_name' => 'VO', 'color' => 'B', 'size' => '20X21', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 20X22', 'product_name' => 'VO', 'color' => 'B', 'size' => '20X22', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 22X23', 'product_name' => 'VO', 'color' => 'B', 'size' => '22X23', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 22X24', 'product_name' => 'VO', 'color' => 'B', 'size' => '22X24', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 24X25', 'product_name' => 'VO', 'color' => 'B', 'size' => '24X25', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 24X26', 'product_name' => 'VO', 'color' => 'B', 'size' => '24X26', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 26X27', 'product_name' => 'VO', 'color' => 'B', 'size' => '26X27', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 26X28', 'product_name' => 'VO', 'color' => 'B', 'size' => '26X28', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 28X29', 'product_name' => 'VO', 'color' => 'B', 'size' => '28X29', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 28X30', 'product_name' => 'VO', 'color' => 'B', 'size' => '28X30', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 30X31', 'product_name' => 'VO', 'color' => 'B', 'size' => '30X31', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO B 30X32', 'product_name' => 'VO', 'color' => 'B', 'size' => '30X32', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 4X5', 'product_name' => 'VO', 'color' => 'HR', 'size' => '4X5', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 6X7', 'product_name' => 'VO', 'color' => 'HR', 'size' => '6X7', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 6X8', 'product_name' => 'VO', 'color' => 'HR', 'size' => '6X8', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 7X8', 'product_name' => 'VO', 'color' => 'HR', 'size' => '7X8', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 8X9', 'product_name' => 'VO', 'color' => 'HR', 'size' => '8X9', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 8X10', 'product_name' => 'VO', 'color' => 'HR', 'size' => '8X10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 9X10', 'product_name' => 'VO', 'color' => 'HR', 'size' => '9X10', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 10X11', 'product_name' => 'VO', 'color' => 'HR', 'size' => '10X11', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 10X12', 'product_name' => 'VO', 'color' => 'HR', 'size' => '10X12', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 11X12', 'product_name' => 'VO', 'color' => 'HR', 'size' => '11X12', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 12X13', 'product_name' => 'VO', 'color' => 'HR', 'size' => '12X13', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 12X14', 'product_name' => 'VO', 'color' => 'HR', 'size' => '12X14', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 14X15', 'product_name' => 'VO', 'color' => 'HR', 'size' => '14X15', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 14X16', 'product_name' => 'VO', 'color' => 'HR', 'size' => '14X16', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 16X17', 'product_name' => 'VO', 'color' => 'HR', 'size' => '16X17', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 16X18', 'product_name' => 'VO', 'color' => 'HR', 'size' => '16X18', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 20X22', 'product_name' => 'VO', 'color' => 'HR', 'size' => '20X22', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 22X24', 'product_name' => 'VO', 'color' => 'HR', 'size' => '22X24', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 24X26', 'product_name' => 'VO', 'color' => 'HR', 'size' => '24X26', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 26X28', 'product_name' => 'VO', 'color' => 'HR', 'size' => '26X28', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 30X31', 'product_name' => 'VO', 'color' => 'HR', 'size' => '30X31', 'product_category_id' => $tubeTestCategoryId],
            ['product_spec_name' => 'VO HR 32X34', 'product_name' => 'VO', 'color' => 'HR', 'size' => '32X34', 'product_category_id' => $tubeTestCategoryId],
        ];

        foreach ($masterProducts as $product) {
            MasterProduct::updateOrCreate(
                ['product_spec_name' => $product['product_spec_name']],
                $product
            );
        }

        $this->command->info('Master products seeded successfully!');
    }
}
