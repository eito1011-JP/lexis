<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\DocumentVersionEntity;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_branch_id' => null,
            'entity_id' => DocumentVersionEntity::factory(),
            'status' => DocumentStatus::DRAFT->value,
            'description' => $this->faker->text(),
            'category_entity_id' => null, // スキーマに合わせて修正
            'title' => $this->faker->sentence(),
            'is_deleted' => false,
            'deleted_at' => null,
            'organization_id' => Organization::factory(),
        ];
    }

    /**
     * Indicate that the document version is merged.
     */
    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);
    }

    /**
     * Indicate that the document version is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::DRAFT->value,
        ]);
    }

    /**
     * Indicate that the document version is pushed.
     */
    public function pushed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::PUSHED->value,
        ]);
    }

    /**
     * Indicate that the document version is for mainline (no user branch).
     */
    public function mainline(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_branch_id' => null,
        ]);
    }

    /**
     * Indicate that the document version has a user branch.
     */
    public function withUserBranch(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_branch_id' => UserBranch::factory(),
        ]);
    }
}
