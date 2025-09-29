<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserBranch>
 */
class UserBranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserBranch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_name' => $this->faker->word(),
            'is_active' => true,
            'organization_id' => Organization::factory(),
        ];
    }

    /**
     * Indicate that the user branch is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
