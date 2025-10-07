<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryService $service;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private CategoryEntity $categoryEntity;

    private CategoryVersion $mergedCategory;

    private EditStartVersion $mergedCategoryEditStartVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CategoryService;

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // MERGEDカテゴリを作成
        $this->categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->mergedCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Test Category',
            'description' => 'Test description',
        ]);

        $this->mergedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $this->mergedCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_successfully_when_no_active_user_branch(): void
    {
        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->mergedCategory->id, $result->id);
        $this->assertEquals($this->categoryEntity->id, $result->entity_id);
        $this->assertEquals('Test Category', $result->title);
        $this->assertEquals('Test description', $result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_returns_null_when_category_belongs_to_different_organization(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();
        $otherCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        CategoryVersion::factory()->create([
            'entity_id' => $otherCategoryEntity->id,
            'organization_id' => $otherOrganization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Other Organization Category',
            'description' => 'Category from different organization',
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $otherCategoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_with_null_description(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Category Without Description',
            'description' => null,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($category->id, $result->id);
        $this->assertEquals($categoryEntity->id, $result->entity_id);
        $this->assertEquals('Category Without Description', $result->title);
        $this->assertNull($result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_with_empty_description(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Category With Empty Description',
            'description' => '',
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($category->id, $result->id);
        $this->assertEquals($categoryEntity->id, $result->entity_id);
        $this->assertEquals('Category With Empty Description', $result->title);
        $this->assertEquals('', $result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_with_active_user_branch_returns_draft_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $draftCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Draft Category',
            'description' => 'Draft description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        // draftカテゴリが取得される
        $this->assertEquals($draftCategory->id, $result->id);
        $this->assertEquals('Draft Category', $result->title);
        $this->assertEquals('Draft description', $result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_with_active_other_user_branch_returns_merged_category(): void
    {
        // Arrange
        $otherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $draftCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
            'title' => 'Draft Category',
            'description' => 'Draft description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        // mergedカテゴリが取得される
        $this->assertEquals($this->mergedCategory->id, $result->id);
        $this->assertEquals('Test Category', $result->title);
        $this->assertEquals('Test description', $result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_with_edit_start_version_returns_current_version(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $currentCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Current Version Category',
            'description' => 'Current version description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($currentCategory->id, $result->id);
        $this->assertEquals('Current Version Category', $result->title);
        $this->assertEquals('Current version description', $result->description);
    }

        /**
     * @test
     */
    public function test_get_category_by_work_context_with_edit_start_version_returns_descendant_current_version(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $parentCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Pushed Category',
            'description' => 'Pushed description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryVersion::factory()->create([
            'parent_entity_id' => $parentCategory->entity_id,
            'entity_id' => $childCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Child Category',
            'description' => 'Child description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $childCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $childCategoryEntity->id,
            $this->user,
            null
        );

        // Assert
        $this->assertNotNull($result);
        // PUSHEDカテゴリが取得される
        $this->assertEquals($childCategory->id, $result->id);
        $this->assertEquals('Child Category', $result->title);
        $this->assertEquals('Child description', $result->description);
    }

    /**
     * @test
     */
    public function test_get_category_by_work_context_should_return_latest_draft_when_multiple_edit_start_versions_exist(): void
    {
        // Arrange
        $previousUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);
        // アクティブなユーザーブランチを作成
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        // 同じentity_idで2つ目のマージ済みカテゴリを作成（同じカテゴリの別バージョン）
        $secondMergedCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'user_branch_id' => $previousUserBranch->id,
            'title' => 'Second Merged Category',
            'description' => 'Second merged description',
        ]);

        // 2つ目のマージ済みカテゴリでEditStartVersionを作成
        $secondEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $secondMergedCategory->id,
        ]);

        // ドラフトカテゴリを作成（2つ目のマージ済みカテゴリの編集版）
        $draftCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Draft Category',
            'description' => 'Draft description',
        ]);

        // EditStartVersionを更新してcurrent_version_idをドラフトカテゴリに変更
        $thirdEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $secondMergedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        // 最新のドラフトカテゴリが取得されるべき
        $this->assertNotNull($result);
        $this->assertEquals($draftCategory->id, $result->id);
        $this->assertEquals('Draft Category', $result->title);
        $this->assertEquals('Draft description', $result->description);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $result->status);
    }
}
