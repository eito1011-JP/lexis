<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Consts\Flag;
use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\DocumentCategory\FetchCategoriesUseCase;
use App\Services\UserBranchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FetchCategoriesUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private FetchCategoriesUseCase $useCase;

    private User $user;

    private Organization $organization;

    private CategoryEntity $firstEntity;

    private UserBranchService $userBranchService;

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

        // firstEntityを作成
        $this->firstEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @test
     */
    public function test_returns_merged_categories_when_user_branch_does_not_exist(): void
    {
        // Arrange
        $dto = new FetchCategoriesDto(parentEntityId: null);

        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // 最初のエンティティに関連するマージ済みカテゴリ（表示される）
        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $this->firstEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $userBranch->id,
        ]);

        $mergedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        // 他のエンティティに関連するドラフトカテゴリ（表示されない）
        $draftCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $draftCategory = CategoryVersion::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $draftCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        // user_branchが存在しない場合、MERGEDステータスのカテゴリのみが返される
        $this->assertCount(1, $result);
        $this->assertEquals('マージ済みカテゴリ', $result->first()->title);
        $this->assertEquals($mergedCategory->id, $result->first()->id);
        $this->assertEquals($this->firstEntity->id, $result->first()->entity_id);
    }

    /**
     * @test
     */
    public function test_returns_draft_and_unedited_merged_categories_when_active_user_branch_exists_without_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        // 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $unEditedMergedCategory = CategoryVersion::factory()->create([
            'title' => '未編集マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $unEditedMergedCategoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);
        $unEditedMergedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $unEditedMergedCategory->id,
            'current_version_id' => $unEditedMergedCategory->id,
        ]);

        // 編集対象になったマージ済みカテゴリ（表示されない）
        $editedMergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $editedMergedCategory = CategoryVersion::factory()->create([
            'title' => '編集済みマージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $editedMergedCategoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);
        $editedMergedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $editedMergedCategory->id,
            'current_version_id' => $editedMergedCategory->id,
        ]);

        // 編集されたドラフトカテゴリ（表示される）
        $editedDraftCategory = CategoryVersion::factory()->create([
            'title' => '編集後ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'entity_id' => $editedMergedCategoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);
        $editedDraftCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $editedMergedCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(2, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('編集後ドラフトカテゴリ', $resultTitles);
        $this->assertContains('未編集マージ済みカテゴリ', $resultTitles);
        // 編集対象となったマージ済みカテゴリは表示されない
        $this->assertNotContains('編集済みマージ済みカテゴリ', $resultTitles);
    }

    /**
     * @test
     */
    public function test_returns_categories_from_organization_entity(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        // 同じエンティティに関連するカテゴリ（表示される）
        $category1Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category1 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ1',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category1Entity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->subHour(),
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category1->id,
            'current_version_id' => $category1->id,
        ]);
        $category2Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category2 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ2',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category2Entity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category2->id,
            'current_version_id' => $category2->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(2, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('カテゴリ1', $resultTitles);
        $this->assertContains('カテゴリ2', $resultTitles);
    }

    /**
     * @test
     */
    public function test_categories_are_retrieved_in_created_at_ascending_order(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        $category3Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category3 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ3',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category3Entity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->addMinutes(2),
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category3->id,
            'current_version_id' => $category3->id,
        ]);

        $category1Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category1 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ1',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category1Entity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category1->id,
            'current_version_id' => $category1->id,
        ]);

        $category2Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category2 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ2',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category2Entity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->addMinutes(1),
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category2->id,
            'current_version_id' => $category2->id,
        ]);
        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('カテゴリ1', $result[0]->title);
        $this->assertEquals('カテゴリ2', $result[1]->title);
        $this->assertEquals('カテゴリ3', $result[2]->title);
    }

    /**
     * @test
     */
    public function test_latest_draft_category_with_user_branch(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        // ケース1: ドラフトカテゴリ（表示されない）
        $originalDraftCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $originalDraftCategory = CategoryVersion::factory()->create([
            'title' => '元のドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $originalDraftCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDraftCategory->id,
            'current_version_id' => $originalDraftCategory->id,
        ]);

        // ケース2: draftカテゴリを再編集したカテゴリ(表示される)
        $editedDraftCategory = CategoryVersion::factory()->create([
            'title' => '編集後ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $originalDraftCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 編集時のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDraftCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(1, $result);
        $resultTitles = $result->pluck('title')->toArray();

        // 表示されるもの
        $this->assertContains('編集後ドラフトカテゴリ', $resultTitles);

        // 表示されないもの
        $this->assertNotContains('元のドラフトカテゴリ', $resultTitles);
    }

        /**
     * @test
     */
    public function test_returns_pushed_category_after_activate_user_branch(): void
    {
        // Arrange
        $previousUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => PullRequestStatus::OPENED->value,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        // ケース1: PR作成前に提出したカテゴリ(表示される)
        $firstCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $firstCategory = CategoryVersion::factory()->create([
            'title' => 'PR作成前に提出したカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $firstCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // PR作成前のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $firstCategory->id,
            'current_version_id' => $firstCategory->id,
        ]);

        // ケース2: mergedカテゴリ(表示される)
        $secondCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $secondCategory = CategoryVersion::factory()->create([
            'title' => 'mergedカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $secondCategoryEntity->id,
            'user_branch_id' => $previousUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $secondCategory->id,
            'current_version_id' => $secondCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(2, $result);
        $resultTitles = $result->pluck('title')->toArray();

        // 表示されるもの
        $this->assertContains('PR作成前に提出したカテゴリ', $resultTitles);
        $this->assertContains('mergedカテゴリ', $resultTitles);
    }

    /**
     * @test
     */
    public function test_returns_draft_category_after_activate_user_branch(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => PullRequestStatus::OPENED->value,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null);

        // ケース1: PR作成前に提出したカテゴリ(表示されない)
        $firstCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $firstCategory = CategoryVersion::factory()->create([
            'title' => 'PR作成前に提出したカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $firstCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'is_deleted' => Flag::TRUE,
        ]);

        // PR作成前のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $firstCategory->id,
            'current_version_id' => $firstCategory->id,
        ]);

        // ケース2: 再編集で作成したカテゴリ(表示される)
        $secondCategory = CategoryVersion::factory()->create([
            'title' => '再編集で作成したカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $firstCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'is_deleted' => Flag::FALSE,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $firstCategory->id,
            'current_version_id' => $secondCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user, $this->userBranchService);

        // Assert
        $this->assertCount(1, $result);
        $resultTitles = $result->pluck('title')->toArray();

        // 表示されるもの
        $this->assertContains('再編集で作成したカテゴリ', $resultTitles);
    }
}
