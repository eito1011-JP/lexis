<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\DocumentCategory\FetchCategoriesUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FetchCategoriesUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private FetchCategoriesUseCase $useCase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new FetchCategoriesUseCase;

        // 組織とユーザーを作成
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // ユーザーを組織に関連付け
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @test
     */
    public function test_returns_merged_categories_when_user_branch_does_not_exist(): void
    {
        // Arrange
        $dto = new FetchCategoriesDto(parentId: null);

        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('マージ済みカテゴリ', $result->first()->title);
        $this->assertEquals($mergedCategory->id, $result->first()->id);
    }

    /**
     * @test
     */
    public function test_returns_merged_categories_when_active_user_branch_exists_without_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: null);

        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('マージ済みカテゴリ', $result->first()->title);
    }

    /**
     * @test
     */
    public function test_returns_draft_categories_when_active_user_branch_exists_with_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: 'some-token');

        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('ドラフトカテゴリ', $result->first()->title);
    }

    /**
     * @test
     */
    public function test_returns_child_categories_when_parent_id_is_specified(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: $parentCategory->id, pullRequestEditSessionToken: 'some-token');

        $childCategory1 = DocumentCategory::factory()->create([
            'title' => '子カテゴリ1',
            'parent_id' => $parentCategory->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->subHour(),
        ]);

        $childCategory2 = DocumentCategory::factory()->create([
            'title' => '子カテゴリ2',
            'parent_id' => $parentCategory->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('子カテゴリ1', $result[0]->title);
        $this->assertEquals('子カテゴリ2', $result[1]->title);
    }

    /**
     * @test
     */
    public function test_categories_are_retrieved_in_created_at_ascending_order(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: 'some-token');

        $category3 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ3',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->addMinutes(2),
        ]);

        $category1 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ1',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);

        $category2 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ2',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->addMinutes(1),
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('カテゴリ1', $result[0]->title);
        $this->assertEquals('カテゴリ2', $result[1]->title);
        $this->assertEquals('カテゴリ3', $result[2]->title);
    }

    /**
     * @test
     */
    public function test_returns_merged_categories_when_active_user_branch_exists_with_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        // 再編集の場合（activeなuser_branchがあり、かつpullRequestEditSessionTokenがnull）
        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: null);

        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('マージ済みカテゴリ', $result->first()->title);
    }

    /**
     * @test
     */
    public function test_filters_only_by_organization_id(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();
        $dto = new FetchCategoriesDto(parentId: null);

        $correctOrgCategory = DocumentCategory::factory()->create([
            'title' => '正しい組織のカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        $wrongOrgCategory = DocumentCategory::factory()->create([
            'title' => '他の組織のカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $otherOrganization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('正しい組織のカテゴリ', $result->first()->title);
    }
}
