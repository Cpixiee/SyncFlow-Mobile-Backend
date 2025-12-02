<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tool;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tool>
 */
class ToolFactory extends Factory
{
    protected $model = Tool::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_name' => $this->faker->randomElement(['Digital Caliper', 'Micrometer', 'Height Gauge', 'Optical Sensor', 'Laser Scanner']) . ' ' . $this->faker->numberBetween(1, 100),
            'tool_model' => $this->faker->randomElement(['Mitutoyo CD-6', 'Mahr Micromar 40 EWR', 'Keyence LK-G5001', 'Keyence LS-7000', 'Mitutoyo 293-340']),
            'tool_type' => $this->faker->randomElement([ToolType::OPTICAL, ToolType::MECHANICAL]),
            'last_calibration_at' => $this->faker->optional(0.8)->dateTimeBetween('-6 months', 'now'),
            'imei' => strtoupper($this->faker->unique()->bothify('???-###-???')),
            'status' => ToolStatus::ACTIVE, // Default status is ACTIVE
        ];
    }

    /**
     * Indicate that the tool is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ToolStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the tool is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ToolStatus::INACTIVE,
        ]);
    }

    /**
     * Indicate that the tool is optical.
     */
    public function optical(): static
    {
        return $this->state(fn (array $attributes) => [
            'tool_type' => ToolType::OPTICAL,
        ]);
    }

    /**
     * Indicate that the tool is mechanical.
     */
    public function mechanical(): static
    {
        return $this->state(fn (array $attributes) => [
            'tool_type' => ToolType::MECHANICAL,
        ]);
    }

    /**
     * Indicate that the tool has calibration data.
     */
    public function withCalibration(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_calibration_at' => Carbon::now()->subMonths(rand(1, 6)),
        ]);
    }
}

