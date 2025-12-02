<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\LoginUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $types = [
            'TOOL_CALIBRATION_DUE',
            'PRODUCT_OUT_OF_SPEC',
            'NEW_ISSUE',
            'ISSUE_OVERDUE',
            'NEW_COMMENT',
            'MONTHLY_TARGET_WARNING'
        ];

        $referenceTypes = [
            'tool',
            'product_measurement',
            'issue',
            'issue_comment',
            'monthly_target'
        ];

        $type = $this->faker->randomElement($types);
        $referenceType = $this->faker->randomElement($referenceTypes);

        return [
            'type' => $type,
            'title' => $this->getTitleForType($type),
            'message' => $this->faker->paragraph(3),
            'reference_type' => $referenceType,
            'reference_id' => $this->faker->numberBetween(1, 100),
            'metadata' => $this->getMetadataForType($type),
            'user_id' => LoginUser::factory(),
            'is_read' => $this->faker->boolean(30), // 30% chance of being read
            'read_at' => $this->faker->boolean(30) ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
        ];
    }

    /**
     * Get appropriate title based on notification type
     */
    private function getTitleForType(string $type): string
    {
        return match ($type) {
            'TOOL_CALIBRATION_DUE' => 'Kalibrasi Alat Akan Jatuh Tempo',
            'PRODUCT_OUT_OF_SPEC' => 'Produk Out of Spec Terdeteksi',
            'NEW_ISSUE' => 'Issue Baru Dibuat',
            'ISSUE_OVERDUE' => 'Issue Overdue',
            'NEW_COMMENT' => 'Komentar Baru pada Issue',
            'MONTHLY_TARGET_WARNING' => 'Target Bulanan per Minggu Ini Belum Tercapai',
            default => 'Notification',
        };
    }

    /**
     * Get appropriate metadata based on notification type
     */
    private function getMetadataForType(string $type): array
    {
        return match ($type) {
            'TOOL_CALIBRATION_DUE' => [
                'tool_id' => $this->faker->numberBetween(1, 50),
                'tool_name' => $this->faker->words(2, true),
                'tool_model' => 'MODEL-' . $this->faker->randomNumber(3),
                'next_calibration_at' => now()->addDays($this->faker->numberBetween(1, 7))->toISOString(),
                'days_remaining' => $this->faker->numberBetween(1, 7),
            ],
            'PRODUCT_OUT_OF_SPEC' => [
                'measurement_id' => 'MSR-' . strtoupper($this->faker->bothify('########')),
                'batch_number' => 'BATCH-' . date('Ymd') . '-' . strtoupper($this->faker->bothify('######')),
                'product_name' => $this->faker->word,
                'out_of_spec_items' => [
                    [
                        'item_name' => 'Thickness',
                        'value' => $this->faker->randomFloat(2, 1, 5),
                        'min' => 2.0,
                        'max' => 3.0,
                        'unit' => 'mm',
                    ]
                ],
            ],
            'NEW_ISSUE' => [
                'issue_id' => $this->faker->numberBetween(1, 100),
                'title' => $this->faker->sentence,
                'priority' => $this->faker->randomElement(['LOW', 'MEDIUM', 'HIGH']),
                'status' => 'OPEN',
                'created_by' => $this->faker->numberBetween(1, 20),
            ],
            'ISSUE_OVERDUE' => [
                'issue_id' => $this->faker->numberBetween(1, 100),
                'title' => $this->faker->sentence,
                'status' => $this->faker->randomElement(['OPEN', 'IN_PROGRESS']),
                'due_date' => now()->subDays($this->faker->numberBetween(1, 10))->toISOString(),
                'days_overdue' => $this->faker->numberBetween(1, 10),
            ],
            'NEW_COMMENT' => [
                'comment_id' => $this->faker->numberBetween(1, 500),
                'issue_id' => $this->faker->numberBetween(1, 100),
                'issue_title' => $this->faker->sentence,
                'commenter_name' => $this->faker->userName,
                'comment_preview' => $this->faker->text(200),
            ],
            'MONTHLY_TARGET_WARNING' => [
                'month' => now()->format('F Y'),
                'current_week' => $this->faker->numberBetween(1, 4),
                'total_weeks' => 4,
                'monthly_target' => 100,
                'actual_inspections' => $this->faker->numberBetween(30, 70),
                'expected_percentage' => $this->faker->numberBetween(60, 80),
                'actual_percentage' => $this->faker->numberBetween(30, 50),
                'gap_percentage' => $this->faker->numberBetween(10, 30),
            ],
            default => [],
        };
    }

    /**
     * Create unread notification
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Create read notification
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create notification of specific type
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'title' => $this->getTitleForType($type),
            'metadata' => $this->getMetadataForType($type),
        ]);
    }
}
