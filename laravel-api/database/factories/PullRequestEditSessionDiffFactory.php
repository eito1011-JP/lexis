<?php

namespace Database\Factories;

use App\Enums\PullRequestEditSessionDiffTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PullRequestEditSessionDiff>
 */
class PullRequestEditSessionDiffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PullRequestEditSessionDiff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $targetType = $this->faker->randomElement(PullRequestEditSessionDiffTargetType::values());
        $diffType = $this->faker->randomElement(['created', 'updated', 'deleted']);

        return [
            'pull_request_edit_session_id' => PullRequestEditSession::factory(),
            'target_type' => $targetType,
            'diff_type' => $diffType,
            'original_version_id' => null,
            'current_version_id' => null,
        ];
    }

    /**
     * ドキュメントタイプのdiffを作成
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => PullRequestEditSessionDiffTargetType::DOCUMENT->value,
        ]);
    }

    /**
     * カテゴリタイプのdiffを作成
     */
    public function category(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => PullRequestEditSessionDiffTargetType::CATEGORY->value,
        ]);
    }

    /**
     * 作成タイプのdiffを作成
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'diff_type' => 'created',
            'original_version_id' => null,
        ]);
    }

    /**
     * 更新タイプのdiffを作成
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'diff_type' => 'updated',
        ]);
    }

    /**
     * 削除タイプのdiffを作成
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'diff_type' => 'deleted',
            'current_version_id' => null,
        ]);
    }

    /**
     * ドキュメントバージョンとの関連を設定
     */
    public function withDocumentVersions(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => PullRequestEditSessionDiffTargetType::DOCUMENT->value,
            'original_version_id' => DocumentVersion::factory(),
            'current_version_id' => DocumentVersion::factory(),
        ]);
    }

    /**
     * カテゴリバージョンとの関連を設定
     */
    public function withCategoryVersions(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => PullRequestEditSessionDiffTargetType::CATEGORY->value,
            'original_version_id' => DocumentCategory::factory(),
            'current_version_id' => DocumentCategory::factory(),
        ]);
    }
}
