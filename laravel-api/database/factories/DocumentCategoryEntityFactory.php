<?php

namespace Database\Factories;

use App\Models\DocumentCategoryEntity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCategoryEntity>
 */
class DocumentCategoryEntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentCategoryEntity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'is_deleted' => false,
            'deleted_at' => null,
        ];
    }
}
