<?php

namespace Tests\Unit\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Models\UserBranch;
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
        $this->useCase = new FetchNodesUseCase;

        // 組織とユーザーを作成
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // ユーザーを組織に関連付け
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // アクティブなユーザーブランチを作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * マージ済みカテゴリとドキュメントを取得する（pullRequestEditSessionTokenなし）
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedAndDraftNodesWhenHasActiveUserBranch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // ドラフト状態のカテゴリとドキュメント
        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
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
        $this->assertEquals('merged', $result['documents'][0]['status']);
        $this->assertEquals($this->user->id, $result['documents'][0]['last_edited_by']);

        // ドラフトドキュメントのアサート
        $this->assertEquals('ドラフトドキュメント', $result['documents'][1]['title']);
        $this->assertEquals('draft', $result['documents'][1]['status']);
    }

    /**
     * ドラフト状態のカテゴリとドキュメントを取得する（pullRequestEditSessionTokenあり、編集セッション存在）
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnDraftNodesWhenEditMergedNodes(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // ドラフト状態のカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'アップデートカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
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

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
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

        $this->assertEquals($draftCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('アップデートカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals('draft', $result['categories'][0]['status']);

        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['title']);
        $this->assertEquals('draft', $result['documents'][0]['status']);
    }

    /**
     * 編集セッションが見つからない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedNodesWhenDoesNotHaveActiveUserBranch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        // アクティブなユーザーブランチを非アクティブにする
        $this->userBranch->update(['is_active' => false]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // ドラフト状態のカテゴリとドキュメント（取得されない）
        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
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
        $this->assertEquals('merged', $result['categories'][0]['status']);
        $this->assertEquals('merged', $result['documents'][0]['status']);
    }

    /**
     * アクティブなユーザーブランチが存在しない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedAndDraftNodesWhenHasPullRequestAndActiveUserBranch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
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
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: 'some-token'
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $mergedCategory->id,
            'current_version_id' => $mergedCategory->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
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
        $this->assertEquals('merged', $result['categories'][0]['status']);

        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['title']);
        $this->assertEquals('draft', $result['documents'][0]['status']);
    }

    /**
     * カテゴリとドキュメントが空の場合のテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnEmptyArraysWhenNoNodesExist(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
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
    public function executeShouldReturnNodesOrderedByIdAscending(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // IDが大きい順に作成して、結果では小さい順になることを確認
        $category2 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ2',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category2->id,
            'current_version_id' => $category2->id,
        ]);

        $category1 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ1',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category1->id,
            'current_version_id' => $category1->id,
        ]);

        $document2 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント2',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document2->id,
            'current_version_id' => $document2->id,
        ]);

        $document1 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント1',
            'category_id' => $parentCategory->id,
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
    public function executeShouldFilterDraftNodesByCorrectUserAndBranch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        // 別のユーザーとブランチを作成
        $otherUser = User::factory()->create();
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // 正しいユーザーとブランチのドラフトデータ（取得される）
        $correctCategory = DocumentCategory::factory()->create([
            'title' => '正しいユーザーのカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $correctCategory->id,
            'current_version_id' => $correctCategory->id,
        ]);

        $correctDocument = DocumentVersion::factory()->create([
            'title' => '正しいユーザーのドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $correctDocument->id,
            'current_version_id' => $correctDocument->id,
        ]);

        // 他のユーザーとブランチのドラフトデータ（取得されない）
        $otherCategory = DocumentCategory::factory()->create([
            'title' => '他のユーザーのカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $otherUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $otherCategory->id,
            'current_version_id' => $otherCategory->id,
        ]);

        $otherDocument = DocumentVersion::factory()->create([
            'title' => '他のユーザーのドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
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
        $this->assertEquals('draft', $result['categories'][0]['status']);

        $this->assertEquals($correctDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('正しいユーザーのドキュメント', $result['documents'][0]['title']);
        $this->assertEquals('draft', $result['documents'][0]['status']);
    }

    /**
     * アクティブなuser_branchが存在し、現在のactive user_branchとは違う非アクティブなbranchで作られたmergedなdocumentとカテゴリも表示されることを検証するテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedDocumentAndCategoryFromDifferentBranches(): void
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
            'user_id' => $otherUser->id,
            'is_active' => false,
            'organization_id' => $this->organization->id,
        ]);

        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_entity_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // 現在のユーザーブランチで作成されたマージ済みドキュメント
        $mergedDocumentFromCurrentBranch = DocumentVersion::factory()->create([
            'title' => '現在ブランチのマージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
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
        $mergedDocumentFromOtherBranch = DocumentVersion::factory()->create([
            'title' => '別ブランチのマージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $otherUser->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocumentFromOtherBranch->id,
            'current_version_id' => $mergedDocumentFromOtherBranch->id,
        ]);

        // 別のブランチで作成されたマージ済みカテゴリ（別のuser_branchのEditStartVersionあり）
        $mergedCategoryFromOtherBranch = DocumentCategory::factory()->create([
            'title' => '別ブランチのマージ済みカテゴリ',
            'parent_entity_id' => $parentCategory->id,
            'status' => 'merged',
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
        $this->assertEquals('merged', $result['categories'][0]['status']);

        // ドキュメントの検証（順序は保証されないのでIDで確認）
        $documentIds = array_column($result['documents'], 'id');
        $this->assertContains($mergedDocumentFromCurrentBranch->id, $documentIds);
        $this->assertContains($mergedDocumentFromOtherBranch->id, $documentIds);

        // 各ドキュメントの詳細を確認
        foreach ($result['documents'] as $document) {
            $this->assertEquals('merged', $document['status']);
            if ($document['id'] === $mergedDocumentFromCurrentBranch->id) {
                $this->assertEquals('現在ブランチのマージ済みドキュメント', $document['title']);
                $this->assertEquals($this->user->id, $document['last_edited_by']);
            } elseif ($document['id'] === $mergedDocumentFromOtherBranch->id) {
                $this->assertEquals('別ブランチのマージ済みドキュメント', $document['title']);
                $this->assertEquals($otherUser->id, $document['last_edited_by']);
            }
        }
    }
    

    /**
     * 例外が発生した場合の処理をテスト
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldLogAndRethrowExceptionWhenErrorOccurs(): void
    {
        // Arrange
        Log::shouldReceive('error')->once();

        // ユーザーのuserBranchesリレーションをモックして例外を発生させる
        $mockUser = $this->createMock(User::class);
        $mockUser->method('userBranches')->willThrowException(new \Exception('Database error'));

        $dto = new FetchNodesDto(
            categoryId: 1,
            pullRequestEditSessionToken: null
        );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');
        $this->useCase->execute($dto, $mockUser);
    }
}
