<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Models\Product;

class TestProductsSeeder extends Seeder
{
	public function run(): void
	{
		$quarter = Quarter::getActiveQuarter() ?? Quarter::getCurrentQuarter();
		$category = ProductCategory::first();

		if (!$quarter || !$category) {
			$this->command->warn('Pastikan ada quarter aktif dan minimal 1 product category.');
			return;
		}

		$dummyPoints = [[
			'setup' => [
				'name' => 'Thickness',
				'name_id' => 'thickness',
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
				'rule' => 'MAX',
				'unit' => 'mm',
				'value' => 10,
				'tolerance_minus' => null,
				'tolerance_plus' => null,
			],
		]];

		for ($i = 1; $i <= 5; $i++) {
			Product::create([
				'quarter_id' => $quarter->id,
				'product_category_id' => $category->id,
				'product_name' => 'Dummy Product '.$i,
				'ref_spec_number' => 'SPEC-'.$i,
				'nom_size_vo' => 'NOM-'.$i,
				'article_code' => 'ART-'.$i,
				'no_document' => null,
				'no_doc_reference' => null,
				'measurement_points' => $dummyPoints,
				'measurement_groups' => [],
			]);
		}

		$this->command->info('5 dummy products created.');
	}
}