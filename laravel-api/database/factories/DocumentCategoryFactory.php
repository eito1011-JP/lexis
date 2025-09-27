<?php

namespace Database\Factories;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => DocumentCategoryEntity::factory(),
            'parent_entity_id' => null,
            'organization_id' => Organization::factory(),
            'title' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'status' => DocumentCategoryStatus::MERGED->value,
            'user_branch_id' => null,
            'is_deleted' => false,
            'deleted_at' => null,
        ];
    }

    /**
     * Indicate that the category is a child category.
     */
    public function child(DocumentCategoryEntity $parentEntity): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_entity_id' => $parentEntity->id,
        ]);
    }

    /**
     * Indicate that the category is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);
    }
}
