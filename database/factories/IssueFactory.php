<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\LoginUser;
use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Issue>
 */
class IssueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Issue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_name' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement([IssueStatus::PENDING, IssueStatus::ON_GOING, IssueStatus::SOLVED]),
            'created_by' => LoginUser::factory(),
            'due_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+30 days'),
        ];
    }

    /**
     * State for pending status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::PENDING,
        ]);
    }

    /**
     * State for on going status
     */
    public function onGoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::ON_GOING,
        ]);
    }

    /**
     * State for solved status
     */
    public function solved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::SOLVED,
        ]);
    }

    /**
     * State for issue with due date
     */
    public function withDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * State for issue without due date
     */
    public function withoutDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => null,
        ]);
    }
}

