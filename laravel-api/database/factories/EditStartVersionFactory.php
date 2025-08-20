<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EditStartVersion>
 */
class EditStartVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EditStartVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_branch_id' => UserBranch::factory(),
            'target_type' => 'document',
            'original_version_id' => DocumentVersion::factory(),
            'current_version_id' => DocumentVersion::factory(),
            'is_deleted' => false,
            'deleted_at' => null,
        ];
    }

    /**
     * Indicate that the edit start version is for a document.
     */
    public function forDocument(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'document',
            'original_version_id' => DocumentVersion::factory(),
        ]);
    }

    /**
     * Indicate that the edit start version is for a category.
     */
    public function forCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'category',
            'original_version_id' => DocumentCategory::factory(),
        ]);
    }

    /**
     * Indicate that the edit start version has a current version.
     */
    public function withCurrentVersion(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_version_id' => $this->getCurrentVersionId($attributes['target_type']),
        ]);
    }

    /**
     * Get the appropriate current version ID based on target type.
     */
    private function getCurrentVersionId(string $targetType): int
    {
        if ($targetType === 'document') {
            return DocumentVersion::factory()->create()->id;
        }

        return DocumentCategory::factory()->create()->id;
    }
}
