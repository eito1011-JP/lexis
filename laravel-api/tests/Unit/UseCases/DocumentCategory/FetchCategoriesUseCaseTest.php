<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\EditStartVersion;
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
    public function test_returns_draft_and_unedited_merged_categories_when_active_user_branch_exists_without_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: null);

        // 新規作成されたドラフトカテゴリ
        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategory = DocumentCategory::factory()->create([
            'title' => '未編集マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // 編集対象になったマージ済みカテゴリ（表示されない）
        $editedMergedCategory = DocumentCategory::factory()->create([
            'title' => '編集済みマージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // 編集されたドラフトカテゴリ（表示される）
        $editedDraftCategory = DocumentCategory::factory()->create([
            'title' => '編集後ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 編集開始バージョンを作成（編集対象のマージ済みカテゴリを指定）
        // original_version_id ≠ current_version_id なので編集対象として扱われる
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $editedMergedCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('ドラフトカテゴリ', $resultTitles);
        $this->assertContains('編集後ドラフトカテゴリ', $resultTitles);
        $this->assertContains('未編集マージ済みカテゴリ', $resultTitles);
        // 編集対象となったマージ済みカテゴリは表示されない
        $this->assertNotContains('編集済みマージ済みカテゴリ', $resultTitles);
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

        $draftCategoryByOtherUser = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        $pushedCategoryByOtherUser = DocumentCategory::factory()->create([
            'title' => 'プッシュ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => null,
            'organization_id' => $this->organization->id,
        ]);


        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pushedCategory = DocumentCategory::factory()->create([
            'title' => 'プッシュ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
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
        $this->assertCount(2, $result);
        $this->assertEquals($pushedCategory->id, $result->first()->id);
        $this->assertEquals($draftCategory->id, $result->last()->id);
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

        // 再編集の場合（activeなuser_branchがあり、かつpullRequestEditSessionTokenがある）
        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: 'some-token');

        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        $pushedCategory = DocumentCategory::factory()->create([
            'title' => 'プッシュ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
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
        $this->assertCount(2, $result);
        $this->assertEquals($pushedCategory->id, $result->first()->id);
        $this->assertEquals($draftCategory->id, $result->last()->id);
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

    /**
     * @test
     */
    public function test_returns_draft_and_unedited_merged_categories_including_edited_versions(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: null);

        // 新規作成されたドラフトカテゴリ
        $newDraftCategory = DocumentCategory::factory()->create([
            'title' => '新規ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 新規作成時のEditStartVersionを作成（original_version_id = current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $newDraftCategory->id,
            'current_version_id' => $newDraftCategory->id,
        ]);

        // 編集対象となったマージ済みカテゴリ（表示されない）
        $originalMergedCategory = DocumentCategory::factory()->create([
            'title' => '元のマージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // 編集されたドラフトカテゴリ（表示される）
        $editedDraftCategory = DocumentCategory::factory()->create([
            'title' => '編集されたドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 編集開始バージョンを作成（元のマージ済みカテゴリを編集対象として指定）
        // original_version_id ≠ current_version_id なので編集対象として扱われる
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategory = DocumentCategory::factory()->create([
            'title' => '未編集マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('新規ドラフトカテゴリ', $resultTitles);
        $this->assertContains('編集されたドラフトカテゴリ', $resultTitles);
        $this->assertContains('未編集マージ済みカテゴリ', $resultTitles);
        // 編集対象となったマージ済みカテゴリは表示されない
        $this->assertNotContains('元のマージ済みカテゴリ', $resultTitles);
    }

    /**
     * @test
     */
    public function test_distinguishes_between_new_and_edited_categories(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentId: null, pullRequestEditSessionToken: null);

        // ケース1: 新規作成されたマージ済みカテゴリ（表示される）
        $newMergedCategory = DocumentCategory::factory()->create([
            'title' => '新規作成マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // 新規作成時のEditStartVersion（original_version_id = current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $newMergedCategory->id,
            'current_version_id' => $newMergedCategory->id,
        ]);

        // ケース2: 実際に編集されたマージ済みカテゴリ（元のカテゴリは表示されない）
        $originalMergedCategory = DocumentCategory::factory()->create([
            'title' => '編集対象元マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        $editedDraftCategory = DocumentCategory::factory()->create([
            'title' => '編集後ドラフトカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 編集時のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // ケース3: 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategory = DocumentCategory::factory()->create([
            'title' => '未編集マージ済みカテゴリ',
            'parent_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        $resultTitles = $result->pluck('title')->toArray();
        
        // 表示されるもの
        $this->assertContains('新規作成マージ済みカテゴリ', $resultTitles);
        $this->assertContains('編集後ドラフトカテゴリ', $resultTitles);
        $this->assertContains('未編集マージ済みカテゴリ', $resultTitles);
        
        // 表示されないもの（編集対象となった元のマージ済みカテゴリ）
        $this->assertNotContains('編集対象元マージ済みカテゴリ', $resultTitles);
    }
}
