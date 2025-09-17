<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\UserBranch;
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
            'user_branch_id' => 1,
            'status' => DocumentStatus::DRAFT->value,
            'description' => $this->faker->text(),
            'category_id' => DocumentCategory::factory(),
            'title' => $this->faker->sentence(),
            'last_edited_by' => $this->faker->name(),
            'last_reviewed_by' => null,
            'is_deleted' => false,
            'deleted_at' => null,
        ];
    }

    /**
     * Indicate that the document version is merged.
     */
    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => 1,
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
            'user_branch_id' => 1,
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
