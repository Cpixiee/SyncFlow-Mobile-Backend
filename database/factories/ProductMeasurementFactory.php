<?php

namespace Database\Factories;

use App\Models\ProductMeasurement;
use App\Models\Product;
use App\Models\LoginUser;
use App\Enums\MeasurementType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductMeasurementFactory extends Factory
{
    protected $model = ProductMeasurement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => null,
            'sample_count' => 3,
            'measurement_type' => MeasurementType::FULL_MEASUREMENT,
            'status' => 'TODO',
            'sample_status' => 'NOT_COMPLETE',
            'overall_result' => null,
            'measurement_results' => null,
            'measured_by' => LoginUser::factory(),
            'measured_at' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'notes' => null,
        ];
    }

    public function withStatus(string $status): self
    {
        return $this->state(function (array $attributes) use ($status) {
            return [
                'status' => $status,
            ];
        });
    }

    public function withBatchNumber(string $batchNumber): self
    {
        return $this->state(function (array $attributes) use ($batchNumber) {
            return [
                'batch_number' => $batchNumber,
                'status' => 'IN_PROGRESS',
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'COMPLETED',
                'sample_status' => 'OK',
                'overall_result' => true,
                'measured_at' => now(),
            ];
        });
    }
}

