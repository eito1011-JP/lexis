<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\DocumentCategory\GetCategoryUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GetCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private GetCategoryUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private DocumentCategoryEntity $categoryEntity;

    private DocumentCategory $mergedCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new GetCategoryUseCase;

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // 基本的なカテゴリエンティティとMERGEDカテゴリを作成
        $this->categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->mergedCategory = DocumentCategory::factory()->create([
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
    public function test_get_category_successfully_when_no_active_user_branch(): void
    {
        // Arrange
        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($this->mergedCategory->id, $result['id']);
        $this->assertEquals($this->categoryEntity->id, $result['entity_id']);
        $this->assertEquals('Test Category', $result['title']);
        $this->assertEquals('Test description', $result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_throws_not_found_exception_when_category_entity_not_exists(): void
    {
        // Arrange
        $nonExistentCategoryEntityId = 99999;

        $dto = new GetCategoryDto([
            'category_entity_id' => $nonExistentCategoryEntityId,
            'user' => $this->user,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('カテゴリエンティティが見つかりません。');
        $this->useCase->execute($dto);
    }

    /**
     * @test
     */
    public function test_get_category_throws_not_found_exception_when_category_belongs_to_different_organization(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();
        $otherCategoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        DocumentCategory::factory()->create([
            'entity_id' => $otherCategoryEntity->id,
            'organization_id' => $otherOrganization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Other Organization Category',
            'description' => 'Category from different organization',
        ]);

        $dto = new GetCategoryDto([
            'category_entity_id' => $otherCategoryEntity->id,
            'user' => $this->user,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('カテゴリが見つかりません。');
        $this->useCase->execute($dto);
    }

    /**
     * @test
     */
    public function test_get_category_with_null_description(): void
    {
        // Arrange
        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Category Without Description',
            'description' => null,
        ]);

        $dto = new GetCategoryDto([
            'category_entity_id' => $categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($category->id, $result['id']);
        $this->assertEquals($categoryEntity->id, $result['entity_id']);
        $this->assertEquals('Category Without Description', $result['title']);
        $this->assertNull($result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_with_empty_description(): void
    {
        // Arrange
        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Category With Empty Description',
            'description' => '',
        ]);

        $dto = new GetCategoryDto([
            'category_entity_id' => $categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($category->id, $result['id']);
        $this->assertEquals($categoryEntity->id, $result['entity_id']);
        $this->assertEquals('Category With Empty Description', $result['title']);
        $this->assertEquals('', $result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_with_active_user_branch_returns_draft_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $draftCategory = DocumentCategory::factory()->create([
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

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        // draftカテゴリが取得される
        $this->assertEquals($draftCategory->id, $result['id']);
        $this->assertEquals('Draft Category', $result['title']);
        $this->assertEquals('Draft description', $result['description']);
    }

        /**
     * @test
     */
    public function test_get_category_with_active_other_user_branch_returns_merged_category(): void
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

        $draftCategory = DocumentCategory::factory()->create([
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

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        // mergedカテゴリが取得される
        $this->assertEquals($this->mergedCategory->id, $result['id']);
        $this->assertEquals('Test Category', $result['title']);
        $this->assertEquals('Test description', $result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_with_edit_start_version_returns_current_version(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $currentCategory = DocumentCategory::factory()->create([
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

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($currentCategory->id, $result['id']);
        $this->assertEquals('Current Version Category', $result['title']);
        $this->assertEquals('Current version description', $result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_with_pull_request_edit_session_token_returns_pushed_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $pushedCategory = DocumentCategory::factory()->create([
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

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
            'pull_request_edit_session_token' => 'test-token',
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        // PUSHEDカテゴリが取得される
        $this->assertEquals($pushedCategory->id, $result['id']);
        $this->assertEquals('Pushed Category', $result['title']);
        $this->assertEquals('Pushed description', $result['description']);
    }
}
