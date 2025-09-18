<?php

namespace Tests\Unit\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
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
    public function executeShouldReturnMergedNodesWhenNoPullRequestEditSessionToken(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: null
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // ドラフト状態のカテゴリとドキュメント（取得されない）
        DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($mergedCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['sidebar_label']);

        $this->assertEquals($mergedDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('マージ済みドキュメント', $result['documents'][0]['title']);
        $this->assertEquals('マージ済みドキュメント', $result['documents'][0]['sidebar_label']);
        $this->assertEquals('merged', $result['documents'][0]['status']);
        $this->assertEquals($this->user->id, $result['documents'][0]['last_edited_by']);
    }

    /**
     * ドラフト状態のカテゴリとドキュメントを取得する（pullRequestEditSessionTokenあり、編集セッション存在）
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnDraftNodesWhenPullRequestEditSessionTokenExistsAndSessionFound(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $token = 'test-edit-session-token';
        
        // プルリクエスト編集セッションを作成
        $editSession = PullRequestEditSession::factory()->create([
            'token' => $token,
            'user_id' => $this->user->id,
            'pull_request_id' => \App\Models\PullRequest::factory()->create([
                'user_branch_id' => $this->userBranch->id,
                'organization_id' => $this->organization->id,
            ])->id,
        ]);

        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: $token
        );

        // ドラフト状態のカテゴリとドキュメントを作成
        $draftCategory = DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // マージ済みカテゴリとドキュメント（取得されない）
        DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($draftCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('ドラフトカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals('ドラフトカテゴリ', $result['categories'][0]['sidebar_label']);

        $this->assertEquals($draftDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['title']);
        $this->assertEquals('ドラフトドキュメント', $result['documents'][0]['sidebar_label']);
        $this->assertEquals('draft', $result['documents'][0]['status']);
        $this->assertEquals($this->user->id, $result['documents'][0]['last_edited_by']);
    }

    /**
     * 編集セッションが見つからない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedNodesWhenPullRequestEditSessionTokenProvidedButSessionNotFound(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $nonExistentToken = 'non-existent-token';
        
        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: $nonExistentToken
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // ドラフト状態のカテゴリとドキュメント（取得されない）
        DocumentCategory::factory()->create([
            'title' => 'ドラフトカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'title' => 'ドラフトドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
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
        
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['title']);
        $this->assertEquals('マージ済みドキュメント', $result['documents'][0]['title']);
    }

    /**
     * アクティブなユーザーブランチが存在しない場合、マージ済みデータを取得する
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function executeShouldReturnMergedNodesWhenNoActiveUserBranch(): void
    {
        // Arrange
        // 親カテゴリを作成
        $parentCategory = DocumentCategory::factory()->create([
            'title' => '親カテゴリ',
            'parent_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        // アクティブなユーザーブランチを非アクティブにする
        $this->userBranch->update(['is_active' => false]);
        
        // 新しいユーザーを作成（アクティブなユーザーブランチなし）
        $userWithoutActiveBranch = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $userWithoutActiveBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: 'some-token'
        );

        // マージ済みカテゴリとドキュメントを作成
        $mergedCategory = DocumentCategory::factory()->create([
            'title' => 'マージ済みカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'title' => 'マージ済みドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'last_edited_by' => $userWithoutActiveBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $userWithoutActiveBranch);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($mergedCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('マージ済みカテゴリ', $result['categories'][0]['title']);

        $this->assertEquals($mergedDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('マージ済みドキュメント', $result['documents'][0]['title']);
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
            'parent_id' => null,
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
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result['categories']);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result['documents']);
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
            'parent_id' => null,
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
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $category1 = DocumentCategory::factory()->create([
            'title' => 'カテゴリ1',
            'parent_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $document2 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント2',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);

        $document1 = DocumentVersion::factory()->create([
            'title' => 'ドキュメント1',
            'category_id' => $parentCategory->id,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
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
            'parent_id' => null,
            'status' => 'merged',
            'organization_id' => $this->organization->id,
        ]);
        
        $token = 'test-edit-session-token';
        
        // 別のユーザーとブランチを作成
        $otherUser = User::factory()->create();
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        // プルリクエスト編集セッションを作成
        $editSession = PullRequestEditSession::factory()->create([
            'token' => $token,
            'user_id' => $this->user->id,
            'pull_request_id' => \App\Models\PullRequest::factory()->create([
                'user_branch_id' => $this->userBranch->id,
                'organization_id' => $this->organization->id,
            ])->id,
        ]);

        $dto = new FetchNodesDto(
            categoryId: $parentCategory->id,
            pullRequestEditSessionToken: $token
        );

        // 正しいユーザーとブランチのドラフトデータ（取得される）
        $correctCategory = DocumentCategory::factory()->create([
            'title' => '正しいユーザーのカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
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

        // 他のユーザーとブランチのドラフトデータ（取得されない）
        DocumentCategory::factory()->create([
            'title' => '他のユーザーのカテゴリ',
            'parent_id' => $parentCategory->id,
            'status' => 'draft',
            'user_branch_id' => $otherUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'title' => '他のユーザーのドキュメント',
            'category_id' => $parentCategory->id,
            'status' => 'draft',
            'user_id' => $otherUser->id,
            'user_branch_id' => $otherUserBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertCount(1, $result['documents']);

        $this->assertEquals($correctCategory->id, $result['categories'][0]['id']);
        $this->assertEquals('正しいユーザーのカテゴリ', $result['categories'][0]['title']);

        $this->assertEquals($correctDocument->id, $result['documents'][0]['id']);
        $this->assertEquals('正しいユーザーのドキュメント', $result['documents'][0]['title']);
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
