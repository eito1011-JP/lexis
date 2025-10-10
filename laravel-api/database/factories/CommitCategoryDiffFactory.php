<?php

namespace Database\Factories;

use App\Enums\CommitChangeType;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\Commit;
use App\Models\CommitCategoryDiff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommitCategoryDiff>
 */
class CommitCategoryDiffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommitCategoryDiff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'category_entity_id' => CategoryEntity::factory(),
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => CategoryVersion::factory(),
            'last_current_version_id' => CategoryVersion::factory(),
        ];
    }

    /**
     * Indicate that the diff is a created type.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => CommitChangeType::CREATED->value,
        ]);
    }

    /**
     * Indicate that the diff is an updated type.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => CommitChangeType::UPDATED->value,
        ]);
    }

    /**
     * Indicate that the diff is a deleted type.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => CommitChangeType::DELETED->value,
        ]);
    }
}

