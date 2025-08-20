<?php

namespace Database\Factories;

use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PullRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_branch_id' => UserBranch::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'github_url' => $this->faker->url(),
            'status' => PullRequestStatus::OPENED->value,
        ];
    }

    /**
     * Indicate that the pull request is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PullRequestStatus::OPENED->value,
        ]);
    }

    /**
     * Indicate that the pull request is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PullRequestStatus::CLOSED->value,
        ]);
    }

    /**
     * Indicate that the pull request is merged.
     */
    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PullRequestStatus::MERGED->value,
        ]);
    }
}
