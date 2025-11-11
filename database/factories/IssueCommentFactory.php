<?php

namespace Database\Factories;

use App\Models\IssueComment;
use App\Models\Issue;
use App\Models\LoginUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IssueComment>
 */
class IssueCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = IssueComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => LoginUser::factory(),
            'comment' => $this->faker->paragraph(2),
        ];
    }
}

