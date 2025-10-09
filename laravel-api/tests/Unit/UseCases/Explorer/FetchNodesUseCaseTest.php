<?php

namespace Tests\Unit\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Enums\EditStartVersionTargetType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentCategoryStatus;
use App\Enums\PullRequestStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\UseCases\Explorer\FetchNodesUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FetchNodesUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private FetchNodesUseCase $useCase;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $categoryService = new CategoryService();
        $documentService = new DocumentService($categoryService);
        $this->useCase = new FetchNodesUseCase(
            $categoryService,
            $documentService
        );

        // 組織とユーザーを作成
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // ユーザーを組織に関連付け
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // アクティブなユーザーブランチを作成
        $this->userBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * マージ済みカテゴリとドキュメントを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_merged_and_draft_nodes_when_has_active_user_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id,
        );

        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // ドラフト状態のカテゴリを作成
        $draftCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftCategory = CategoryVersion::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(2, $result['categories']);
        $this->assertCount(2, $result['documents']);

        // マージ済みカテゴリのアサート
        $this->assertEquals($mergedCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['title']);

        // ドラフトカテゴリのアサート
        $this->assertEquals('ドラフトカテゴリ', $result['categories'][1]['title']);
        // マージ済みドキュメントのアサート
        $this->assertEquals($mergedDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('マージ済みドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::MERGED->value, $result['documents'][0]['status']);

        // ドラフトドキュメントのアサート
        $this->assertEquals('ドラフトドキュメント', $result['documents'][1]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][1]['status']);
    }

    /**
     * ドラフト状態のカテゴリとドキュメントを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_draft_nodes_when_edit_merged_nodes(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id,
        );

        // ドラフト状態のカテゴリとドキュメントを作成
        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $draftCategory = CategoryVersion::factory()->create([
            'title' => 'アップデートカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $mergedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentEntity->id,
            'status' => 'merged',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        Log::info($result['categories']);
        $this->assertEquals($draftCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('アップデートカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $result['categories'][0]['status']);

        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][0]['status']);
    }

    /**
     * 編集セッションが見つからない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_merged_nodes_when_does_not_have_active_user_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        // アクティブなユーザーブランチのセッションを削除（非アクティブにする）
        UserBranchSession::where('user_branch_id', $this->userBranch->id)->delete();

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // ドラフト状態のカテゴリとドキュメント（取得されない）
        $draftCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftCategory = CategoryVersion::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        // カテゴリとドキュメントのタイトルで確認
        $categoryTitles = collect($result['categories'])->pluck('title')->toArray();
        $documentTitles = collect($result['documents'])->pluck('title')->toArray();

        $this->assertContains('マージ済みカテゴリ', $categoryTitles);
        $this->assertContains('マージ済みドキュメント', $documentTitles);
        $this->assertNotContains('ドラフトカテゴリ', $categoryTitles);
        $this->assertNotContains('ドラフトドキュメント', $documentTitles);
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $result['categories'][0]['status']);
        $this->assertEquals(DocumentStatus::MERGED->value, $result['documents'][0]['status']);
    }

    /**
     * アクティブなユーザーブランチが存在しない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_merged_and_draft_nodes_when_has_pull_request_and_active_user_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($mergedCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $result['categories'][0]['status']);

        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][0]['status']);
    }

    /**
     * カテゴリとドキュメントが空の場合のテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_empty_arrays_when_no_nodes_exist(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(0, $result['categories']);
        $this->assertCount(0, $result['documents']);
        $this->assertIsArray($result['categories']);
        $this->assertIsArray($result['documents']);
    }

    /**
     * データの並び順をテスト（id昇順）
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_nodes_ordered_by_id_ascending(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // IDが大きい順に作成して、結果では小さい順になることを確認
        $category2Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category2 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ2',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $category2Entity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category2->id,
            'current_version_id' => $category2->id,
        ]);


        $category1Entity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $category1 = CategoryVersion::factory()->create([
            'title' => 'カテゴリ1',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $category1Entity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category1->id,
            'current_version_id' => $category1->id,
        ]);

        $document2Entity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $document2 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント2',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $document2Entity->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document2->id,
            'current_version_id' => $document2->id,
        ]);

        $document1Entity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $document1 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント1',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $document1Entity->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document1->id,
            'current_version_id' => $document1->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(2, $result['categories']);
        $this->assertCount(2, $result['documents']);

        // カテゴリがID昇順で並んでいることを確認
        $this->assertTrue($result['categories'][0]['id'] < $result['categories'][1]['id']);
        // ドキュメントがID昇順で並んでいることを確認
        $this->assertTrue($result['documents'][0]['id'] < $result['documents'][1]['id']);
    }

    /**
     * ドラフトデータ取得時に正しいユーザーIDとブランチIDでフィルタリングされることをテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_filter_draft_nodes_by_correct_user_and_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        // 別のユーザーとブランチを作成
        $otherUser = User::factory()->create();
        $otherUserBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // 正しいユーザーとブランチのドラフトデータ（取得される）
        $correctCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $correctCategory = CategoryVersion::factory()->create([
            'title' => '正しいユーザーのカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $correctCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $correctCategory->id,
            'current_version_id' => $correctCategory->id,
        ]);

        $correctDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $correctDocument = DocumentVersion::factory()->create([
            'title' => '正しいユーザーのドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $correctDocumentEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $correctDocument->id,
            'current_version_id' => $correctDocument->id,
        ]);

        // 他のユーザーとブランチのドラフトデータ（取得されない）
        $otherCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $otherCategory = CategoryVersion::factory()->create([
            'title' => '他のユーザーのカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $otherCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $otherCategory->id,
            'current_version_id' => $otherCategory->id,
        ]);

        $otherDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $otherDocument = DocumentVersion::factory()->create([
            'title' => '他のユーザーのドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $otherDocumentEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_id' => $otherUser->id,
            'user_branch_id' => $otherUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $otherDocument->id,
            'current_version_id' => $otherDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($correctCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('正しいユーザーのカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $result['categories'][0]['status']);

        $this->assertEquals($correctDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('正しいユーザーのドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][0]['status']);
    }

    /**
     * アクティブなuser_branchが存在し、現在のactive user_branchとは違う非アクティブなbranchで作られたmergedなdocumentとカテゴリも表示されることを検証するテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_return_merged_document_and_category_from_different_branches(): void
    {
        // Arrange
        // 別のユーザーを作成
        $otherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        // 別のユーザーブランチを作成（非アクティブ）
        $otherUserBranch = UserBranch::factory()->create([
            'creator_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
        ]);
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        // 親カテゴリを作成
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // 現在のユーザーブランチで作成されたマージ済みドキュメント
        $mergedDocumentFromCurrentBranchEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $mergedDocumentFromCurrentBranch = DocumentVersion::factory()->create([
            'title' => '現在ブランチのマージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentFromCurrentBranchEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocumentFromCurrentBranch->id,
            'current_version_id' => $mergedDocumentFromCurrentBranch->id,
        ]);

        // 別のブランチで作成されたマージ済みドキュメント（別のuser_branchのEditStartVersionあり）
        $mergedDocumentFromOtherBranchEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $mergedDocumentFromOtherBranch = DocumentVersion::factory()->create([
            'title' => '別ブランチのマージ済みドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentFromOtherBranchEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocumentFromOtherBranch->id,
            'current_version_id' => $mergedDocumentFromOtherBranch->id,
        ]);
        $mergedCategoryFromOtherBranchEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        // 別のブランチで作成されたマージ済みカテゴリ（別のuser_branchのEditStartVersionあり）
        $mergedCategoryFromOtherBranch = CategoryVersion::factory()->create([
            'title' => '別ブランチのマージ済みカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryFromOtherBranchEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategoryFromOtherBranch->id,
            'current_version_id' => $mergedCategoryFromOtherBranch->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']); // 別ブランチで作成されたマージ済みカテゴリが1つ
        $this->assertCount(2, $result['documents']); // 両方のマージ済みドキュメントが表示される

        // カテゴリの検証
        $this->assertEquals($mergedCategoryFromOtherBranch->id, $result['categories'][0]['id']);
        $this->assertEquals('別ブランチのマージ済みカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $result['categories'][0]['status']);

        // ドキュメントの検証（順序は保証されないのでIDで確認）
        $documentIds = array_column($result['documents'], 'id');
        $this->assertContains($mergedDocumentFromCurrentBranch->id, $documentIds);
        $this->assertContains($mergedDocumentFromOtherBranch->id, $documentIds);

        // 各ドキュメントの詳細を確認
        foreach ($result['documents'] as $document) {
            $this->assertEquals(DocumentStatus::MERGED->value, $document['status']);
            if ($document['id'] === $mergedDocumentFromCurrentBranch->id) {
                $this->assertEquals('現在ブランチのマージ済みドキュメント', $document['title']);
            } elseif ($document['id'] === $mergedDocumentFromOtherBranch->id) {
                $this->assertEquals('別ブランチのマージ済みドキュメント', $document['title']);
            }
        }
    }

    /**
     * 例外が発生した場合の処理をテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_should_log_and_rethrow_exception_when_error_occurs(): void
    {
        // Arrange
        Log::shouldReceive('error')->once();

        // CategoryServiceをモックして例外を発生させる
        $mockCategoryService = $this->createMock(CategoryService::class);
        $mockCategoryService->method('getCategoryByWorkContext')
            ->willThrowException(new \Exception('Database error'));

        $mockDocumentService = $this->createMock(DocumentService::class);

        // モックされたサービスを持つUseCaseインスタンスを作成
        $useCase = new FetchNodesUseCase($mockCategoryService, $mockDocumentService);

        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        // 子カテゴリを作成（例外を発生させるため）
        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $childCategory = CategoryVersion::factory()->create([
            'title' => '子カテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $childCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $childCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');
        $useCase->execute($dto, $this->user);
    }

        /**
     * @test
     */
    public function test_returns_pushed_and_merged_nodes_after_activate_user_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // PR作成前に提出したカテゴリ（取得される）
        $pushedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $pushedCategory = CategoryVersion::factory()->create([
            'title' => 'PR作成前に提出したカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $pushedCategoryEntity->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $pushedCategory->id,
            'current_version_id' => $pushedCategory->id,
        ]);

        $pushedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'title' => 'PR作成前に提出したドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $pushedDocumentEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        // mergedなカテゴリ(取得される)
        $previousUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
        $mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $mergedCategory = CategoryVersion::factory()->create([
            'title' => 'mergedなカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'user_branch_id' => $previousUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        // mergedなドキュメント(取得される)
        $mergedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'mergedなドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $mergedDocumentEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => $previousUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(2, $result['categories']);
        $this->assertCount(2, $result['documents']);

        // カテゴリの検証
        $this->assertEquals($pushedCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('PR作成前に提出したカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $result['categories'][0]['status']);

        $this->assertEquals($mergedCategory->id, $result['categories'][1]['id']);
        $this->assertEquals('mergedなカテゴリ', $result['categories'][1]['title']);
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $result['categories'][1]['status']);

        // ドキュメントの検証
        $this->assertEquals($pushedDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('PR作成前に提出したドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][0]['status']);

        $this->assertEquals($mergedDocument->id, $result['documents'][1]['id']);
        $this->assertEquals('mergedなドキュメント', $result['documents'][1]['title']);
        $this->assertEquals(DocumentStatus::MERGED->value, $result['documents'][1]['status']);
    }

    /**
     * @test
     */
    public function test_returns_draft_and_pushed_nodes_after_activate_user_branch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $parentCategoryEntity->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'organization_id' => $this->organization->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryEntityId: $parentCategoryEntity->id
        );

        // PR作成前に提出したカテゴリ（取得されない）
        $pushedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $pushedCategory = CategoryVersion::factory()->create([
            'title' => 'PR作成前に提出したカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $pushedCategoryEntity->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $pushedCategory->id,
            'current_version_id' => $pushedCategory->id,
        ]);

        $pushedDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'title' => 'PR作成前に提出したドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $pushedDocumentEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        // draftなカテゴリ(取得される)
        $draftCategory = CategoryVersion::factory()->create([
            'title' => 'draftなカテゴリ',
            'parent_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $pushedCategoryEntity->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $pushedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // draftなドキュメント(取得される)
        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'draftなドキュメント',
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        // カテゴリの検証
        $this->assertEquals($draftCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('draftなカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $result['categories'][0]['status']);

        // ドキュメントの検証
        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('draftなドキュメント', $result['documents'][0]['title']);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result['documents'][0]['status']);

    }
}
