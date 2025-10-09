<?php

namespace Database\Factories;

use App\Models\Commit;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commit>
 */
class CommitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Commit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_commit_id' => null,
            'user_branch_id' => UserBranch::factory(),
            'user_id' => User::factory(),
            'message' => $this->faker->text(50),
        ];
    }

    /**
     * Indicate that the commit has a parent commit.
     */
    public function withParent(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_commit_id' => Commit::factory(),
        ]);
    }
}

