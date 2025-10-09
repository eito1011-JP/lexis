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
            'creator_id' => User::factory(),
            'branch_name' => $this->faker->word(),
            'organization_id' => Organization::factory(),
        ];
    }

    /**
     * アクティブなセッションを持つユーザーブランチを作成
     */
    public function withActiveSession(): static
    {
        return $this->afterCreating(function (UserBranch $userBranch) {
            $userBranch->sessions()->create([
                'user_id' => $userBranch->creator_id,
                'user_branch_id' => $userBranch->id,
            ]);
        });
    }
}
