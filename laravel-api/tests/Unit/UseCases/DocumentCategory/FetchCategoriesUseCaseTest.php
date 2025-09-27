<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\EditStartVersion;
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

    private DocumentCategoryEntity $firstEntity;

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

        // 最初のエンティティを作成（FetchCategoriesUseCaseが最初のエンティティを取得するため）
        $this->firstEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }


    /**
     * 最初のエンティティに関連するカテゴリを作成するヘルパーメソッド
     * （FetchCategoriesUseCaseが最初のエンティティのカテゴリのみを返すため）
     */
    private function createCategoryInFirstEntity(array $attributes): DocumentCategory
    {
        return DocumentCategory::factory()->create(array_merge([
            'entity_id' => $this->firstEntity->id,
            'organization_id' => $this->organization->id,
        ], $attributes));
    }

    /**
     * @test
     */
    public function test_returns_merged_categories_when_user_branch_does_not_exist(): void
    {
        // Arrange
        $dto = new FetchCategoriesDto(parentEntityId: null);

        // 最初のエンティティに関連するマージ済みカテゴリ（表示される）
        $mergedCategory = $this->createCategoryInFirstEntity([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 他のエンティティに関連するドラフトカテゴリ（表示されない）
        // 注意：現在のFetchCategoriesUseCaseの実装では、最初のエンティティのカテゴリのみを返すため、
        // このテストケースでは実際には別のエンティティを作成せず、同じエンティティを使用します
        $draftCategory = $this->createCategoryInFirstEntity([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

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
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: null);

        // 新規作成されたドラフトカテゴリ（最初のエンティティに関連）
        $draftCategory = $this->createCategoryInFirstEntity([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '未編集マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 編集対象になったマージ済みカテゴリ（表示されない）
        $editedMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '編集済みマージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 編集されたドラフトカテゴリ（表示される）
        $editedDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '編集後ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
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

        // 他のユーザーを作成
        $otherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        // 他のユーザーのuser_branchを作成
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: 'some-token');

        // 他のユーザーが作成したドラフトカテゴリ（表示されない）
        $draftCategoryByOtherUser = $this->createCategoryInFirstEntity([
            'title' => '他ユーザーのドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        // 他のユーザーが作成したプッシュ済みカテゴリ（表示されない）
        $pushedCategoryByOtherUser = $this->createCategoryInFirstEntity([
            'title' => '他ユーザーのプッシュ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        // 現在のユーザーのドラフトカテゴリ（表示される）
        $draftCategory = $this->createCategoryInFirstEntity([
            'title' => '自分のドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 現在のユーザーのプッシュ済みカテゴリ（表示される）
        $pushedCategory = $this->createCategoryInFirstEntity([
            'title' => '自分のプッシュ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 未編集のマージ済みカテゴリ（表示される）
        $mergedCategory = $this->createCategoryInFirstEntity([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        
        // 他のユーザーのカテゴリが表示されていないことを確認
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('自分のプッシュ済みカテゴリ', $resultTitles);
        $this->assertContains('自分のドラフトカテゴリ', $resultTitles);
        $this->assertContains('マージ済みカテゴリ', $resultTitles);
        $this->assertNotContains('他ユーザーのドラフトカテゴリ', $resultTitles);
        $this->assertNotContains('他ユーザーのプッシュ済みカテゴリ', $resultTitles);
    }

    /**
     * @test
     */
    public function test_returns_categories_from_organization_entity(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: 'some-token');

        // 同じエンティティに関連するカテゴリ（表示される）
        $category1 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ1',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'created_at' => now()->subHour(),
        ]);

        $category2 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ2',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'created_at' => now(),
        ]);

        // 3つ目のカテゴリ（表示される）
        $category3 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ3',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // pullRequestEditSessionTokenがある場合、PUSHED、DRAFT、MERGEDステータスのカテゴリが返される
        $this->assertCount(3, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('カテゴリ1', $resultTitles);
        $this->assertContains('カテゴリ2', $resultTitles);
        $this->assertContains('カテゴリ3', $resultTitles);
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

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: 'some-token');

        $category3 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ3',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now()->addMinutes(2),
        ]);

        $category1 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ1',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);

        $category2 = $this->createCategoryInFirstEntity([
            'title' => 'カテゴリ2',
            'parent_entity_id' => null,
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
    public function test_returns_merged_and_updated_categories_when_active_user_branch_exists_with_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        // 再編集の場合（activeなuser_branchがあり、かつpullRequestEditSessionTokenがある）
        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: 'some-token');

        $mergedCategory = $this->createCategoryInFirstEntity([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $pushedCategory = $this->createCategoryInFirstEntity([
            'title' => 'プッシュ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $userBranch->id,
        ]);

        $draftCategory = $this->createCategoryInFirstEntity([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(3, $result);
        $resultTitles = $result->pluck('title')->toArray();
        $this->assertContains('マージ済みカテゴリ', $resultTitles);
        $this->assertContains('プッシュ済みカテゴリ', $resultTitles);
        $this->assertContains('ドラフトカテゴリ', $resultTitles);
    }

    /**
     * @test
     */
    public function test_filters_only_by_organization_id(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();
        $dto = new FetchCategoriesDto(parentEntityId: null);

        $correctOrgCategory = $this->createCategoryInFirstEntity([
            'title' => '正しい組織のカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 他の組織のエンティティとカテゴリを作成（表示されない）
        $otherEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        $wrongOrgCategory = DocumentCategory::factory()->create([
            'entity_id' => $otherEntity->id,
            'title' => '他の組織のカテゴリ',
            'parent_entity_id' => null,
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

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: null);

        // 新規作成されたドラフトカテゴリ
        $newDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '新規ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 新規作成時のEditStartVersionを作成（original_version_id = current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $newDraftCategory->id,
            'current_version_id' => $newDraftCategory->id,
        ]);

        // 編集対象となったマージ済みカテゴリ（表示されない）
        $originalMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '元のマージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 編集されたドラフトカテゴリ（表示される）
        $editedDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '編集されたドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
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
        $unEditedMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '未編集マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
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

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: null);

        // ケース1: 新規作成されたマージ済みカテゴリ（表示される）
        $newMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '新規作成マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 新規作成時のEditStartVersion（original_version_id = current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $newMergedCategory->id,
            'current_version_id' => $newMergedCategory->id,
        ]);

        // ケース2: 実際に編集されたマージ済みカテゴリ（元のカテゴリは表示されない）
        $originalMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '編集対象元マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $editedDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '編集後ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 編集時のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // ケース3: 編集対象になっていないマージ済みカテゴリ（表示される）
        $unEditedMergedCategory = $this->createCategoryInFirstEntity([
            'title' => '未編集マージ済みカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::MERGED->value,
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

        /**
     * @test
     */
    public function test_latest_draft_category_with_user_branch(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchCategoriesDto(parentEntityId: null, pullRequestEditSessionToken: null);

        // ケース1: ドラフトカテゴリ（表示されない）
        $originalDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '元のドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 新規作成時のEditStartVersion（original_version_id = current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDraftCategory->id,
            'current_version_id' => $originalDraftCategory->id,
        ]);

        // ケース2: draftカテゴリを再編集したカテゴリ(表示される)
        $editedDraftCategory = $this->createCategoryInFirstEntity([
            'title' => '編集後ドラフトカテゴリ',
            'parent_entity_id' => null,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 編集時のEditStartVersion（original_version_id ≠ current_version_id）
        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDraftCategory->id,
            'current_version_id' => $editedDraftCategory->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result);
        $resultTitles = $result->pluck('title')->toArray();

        // 表示されるもの
        $this->assertContains('編集後ドラフトカテゴリ', $resultTitles);

        // 表示されないもの
        $this->assertNotContains('元のドラフトカテゴリ', $resultTitles);
    }
}
