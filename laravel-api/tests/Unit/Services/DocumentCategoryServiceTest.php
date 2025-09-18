<?php

namespace Tests\Unit\Services;

use App\Models\DocumentCategory;
use App\Models\Organization;
use App\Services\DocumentCategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentCategoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentCategoryService $service;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentCategoryService;
        $this->organization = Organization::factory()->create();
    }

    /**
     * @test
     */
    public function create_category_path_親カテゴリがない場合はnullを返す()
    {
        // Arrange
        $category = DocumentCategory::factory()->create([
            'title' => 'ルートカテゴリ',
            'parent_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->service->createCategoryPath($category);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function create_category_path_単一の親カテゴリがある場合はそのタイトルを返す()
    {
        // Arrange
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = DocumentCategory::factory()->create([
            'title' => '子カテゴリ',
            'parent_id' => $parentCategory->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->service->createCategoryPath($childCategory);

        // Assert
        $this->assertEquals('親カテゴリ', $result);
    }

    /**
     * @test
     */
    public function create_category_path_複数レベルの階層がある場合は正しいパスを返す()
    {
        // Arrange
        $grandParentCategory = DocumentCategory::factory()->create([
            'title' => '祖父カテゴリ',
            'parent_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => $grandParentCategory->id,
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = DocumentCategory::factory()->create([
            'title' => '子カテゴリ',
            'parent_id' => $parentCategory->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->service->createCategoryPath($childCategory);

        // Assert
        $this->assertEquals('祖父カテゴリ/親カテゴリ', $result);
    }

    /**
     * @test
     */
    public function create_category_path_3レベル以上の深い階層でも正しいパスを返す()
    {
        // Arrange
        $level1 = DocumentCategory::factory()->create([
            'title' => 'レベル1',
            'parent_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        $level2 = DocumentCategory::factory()->create([
            'title' => 'レベル2',
            'parent_id' => $level1->id,
            'organization_id' => $this->organization->id,
        ]);

        $level3 = DocumentCategory::factory()->create([
            'title' => 'レベル3',
            'parent_id' => $level2->id,
            'organization_id' => $this->organization->id,
        ]);

        $level4 = DocumentCategory::factory()->create([
            'title' => 'レベル4',
            'parent_id' => $level3->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->service->createCategoryPath($level4);

        // Assert
        $this->assertEquals('レベル1/レベル2/レベル3', $result);
    }
}
