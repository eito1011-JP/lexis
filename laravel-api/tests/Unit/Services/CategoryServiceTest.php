<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
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

        // 基本的なカテゴリエンティティとMERGEDカテゴリを作成
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
    public function test_get_category_by_work_context_with_pull_request_edit_session_token_returns_pushed_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $pushedCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Pushed Category',
            'description' => 'Pushed description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $pushedCategory->id,
        ]);

        // Act
        $result = $this->service->getCategoryByWorkContext(
            $this->categoryEntity->id,
            $this->user,
            'test-token'
        );

        // Assert
        $this->assertNotNull($result);
        // PUSHEDカテゴリが取得される
        $this->assertEquals($pushedCategory->id, $result->id);
        $this->assertEquals('Pushed Category', $result->title);
        $this->assertEquals('Pushed description', $result->description);
    }

    /**
     * @test
     */
    public function test_get_descendant_documents_by_work_context_when_no_active_user_branch(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Test Document',
            'description' => 'Test description',
            'user_branch_id' => null,
        ]);

        // Act
        $result = $this->service->getDescendantDocumentsByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($mergedDocument->id, $result->first()->id);
        $this->assertEquals('Test Document', $result->first()->title);
    }

    /**
     * @test
     */
    public function test_get_descendant_documents_by_work_context_when_no_pull_request_edit_session_token(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Merged Document',
            'description' => 'Merged description',
            'user_branch_id' => null,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Draft Document',
            'description' => 'Draft description',
        ]);

        // Act
        $result = $this->service->getDescendantDocumentsByWorkContext(
            $this->categoryEntity->id,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(2, $result);
        $titles = $result->pluck('title')->toArray();
        $this->assertContains('Merged Document', $titles);
        $this->assertContains('Draft Document', $titles);
    }

    /**
     * @test
     */
    public function test_get_descendant_documents_by_work_context_with_pull_request_edit_session_token(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Merged Document',
            'description' => 'Merged description',
            'user_branch_id' => null,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Pushed Document',
            'description' => 'Pushed description',
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'title' => 'Draft Document',
            'description' => 'Draft description',
        ]);

        // Act
        $result = $this->service->getDescendantDocumentsByWorkContext(
            $this->categoryEntity->id,
            $this->user,
            'test-token'
        );

        // Assert
        $this->assertCount(3, $result);
        $titles = $result->pluck('title')->toArray();
        $this->assertContains('Merged Document', $titles);
        $this->assertContains('Pushed Document', $titles);
        $this->assertContains('Draft Document', $titles);
    }

    /**
     * @test
     */
    public function test_get_descendant_documents_by_work_context_recursively_gets_child_category_documents(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        // 親カテゴリ直下のドキュメント
        $parentDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $parentDocument = DocumentVersion::factory()->create([
            'entity_id' => $parentDocumentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Parent Document',
            'description' => 'Parent description',
            'user_branch_id' => null,
        ]);

        // 子カテゴリを作成
        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryVersion::factory()->create([
            'entity_id' => $childCategoryEntity->id,
            'parent_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Child Category',
            'description' => 'Child category description',
        ]);

        // 子カテゴリ直下のドキュメント
        $childDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childDocument = DocumentVersion::factory()->create([
            'entity_id' => $childDocumentEntity->id,
            'category_entity_id' => $childCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Child Document',
            'description' => 'Child description',
            'user_branch_id' => null,
        ]);

        // 孫カテゴリを作成
        $grandChildCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $grandChildCategory = CategoryVersion::factory()->create([
            'entity_id' => $grandChildCategoryEntity->id,
            'parent_entity_id' => $childCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Grand Child Category',
            'description' => 'Grand child category description',
        ]);

        // 孫カテゴリ直下のドキュメント
        $grandChildDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $grandChildDocument = DocumentVersion::factory()->create([
            'entity_id' => $grandChildDocumentEntity->id,
            'category_entity_id' => $grandChildCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Grand Child Document',
            'description' => 'Grand child description',
            'user_branch_id' => null,
        ]);

        // Act
        $result = $this->service->getDescendantDocumentsByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertCount(3, $result);
        $titles = $result->pluck('title')->toArray();
        $this->assertContains('Parent Document', $titles);
        $this->assertContains('Child Document', $titles);
        $this->assertContains('Grand Child Document', $titles);
    }

    /**
     * @test
     */
    public function test_get_descendant_documents_by_work_context_returns_empty_collection_when_no_documents(): void
    {
        // Arrange: カテゴリのみ作成し、ドキュメントを作成しない

        // Act
        $result = $this->service->getDescendantDocumentsByWorkContext(
            $this->categoryEntity->id,
            $this->user
        );

        // Assert
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }
}
