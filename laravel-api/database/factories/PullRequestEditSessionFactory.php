<?php

namespace Database\Factories;

use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PullRequestEditSession>
 */
class PullRequestEditSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PullRequestEditSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pull_request_id' => PullRequest::factory(),
            'user_id' => User::factory(),
            'token' => $this->faker->uuid(),
            'started_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'finished_at' => null,
        ];
    }

    /**
     * Indicate that the edit session is finished.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'finished_at' => $this->faker->dateTimeBetween($attributes['started_at'], 'now'),
        ]);
    }

    /**
     * Indicate that the edit session is active (not finished).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'finished_at' => null,
        ]);
    }
}
