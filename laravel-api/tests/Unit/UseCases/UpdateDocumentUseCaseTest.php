<?php

namespace Tests\Unit\UseCases;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Enums\PullRequestStatus;
use App\Exceptions\TargetDocumentNotFoundException;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentDiffService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\Services\VersionEditPermissionService;
use App\UseCases\Document\UpdateDocumentUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateDocumentUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateDocumentUseCase $useCase;

    /** @var \Mockery\MockInterface&DocumentService */
    private DocumentService $documentService;

    /** @var \Mockery\MockInterface&UserBranchService */
    private UserBranchService $userBranchService;

    /** @var \Mockery\MockInterface&DocumentCategoryService */
    private DocumentCategoryService $documentCategoryService;

    /** @var \Mockery\MockInterface&DocumentDiffService */
    private DocumentDiffService $documentDiffService;

    /** @var \Mockery\MockInterface&VersionEditPermissionService */
    private VersionEditPermissionService $versionEditPermissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentService = Mockery::mock(DocumentService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);
        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);
        $this->documentDiffService = Mockery::mock(DocumentDiffService::class);
        $this->versionEditPermissionService = Mockery::mock(VersionEditPermissionService::class);

        // updateFileOrder は入力に応じてそのまま返す簡易モック
        $this->documentService
            ->shouldReceive('updateFileOrder')
            ->andReturnUsing(function ($fileOrder, $oldFileOrder, $categoryId, $userBranchId, $editPullRequestId, $documentId, $userId, $userEmail) {
                return $fileOrder ?? ($oldFileOrder + 1);
            });

        // DocumentCategoryServiceのモックを追加
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->andReturn(1);

        // DocumentDiffServiceのモックを追加（基本的に変更があると仮定）
        $this->documentDiffService
            ->shouldReceive('hasDocumentChanges')
            ->andReturn(true);

        // VersionEditPermissionServiceのモックは各テストで個別に設定

        $this->useCase = new UpdateDocumentUseCase(
            $this->documentService,
            $this->userBranchService,
            $this->documentCategoryService,
            $this->documentDiffService,
            $this->versionEditPermissionService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setUpForSubmittedDocumentByUser(User $user, UserBranch $branch, PullRequest $pullRequest, int $categoryId, string $documentStatus): array
    {
        $existingDocument = $this->createOrUpdateDocument($user, $branch, $documentStatus, $categoryId);

        return [$user, $branch, $pullRequest, $existingDocument];
    }

    /**
     * ドキュメント更新の結果を検証するヘルパーメソッド
     */
    private function assertDocumentUpdated(DocumentVersion $result, DocumentVersion $existingDocument, User $user, array $expectedChanges): void
    {
        $this->assertInstanceOf(DocumentVersion::class, $result);

        // データベースに保存された値を検証
        $this->assertDatabaseHas('document_versions', [
            'id' => $result->id,
            'category_id' => $existingDocument->category_id,
            'user_branch_id' => $expectedChanges['user_branch_id'],
            'file_path' => $existingDocument->file_path,
            'status' => DocumentStatus::DRAFT->value,
            'content' => $expectedChanges['content'],
            'slug' => $expectedChanges['slug'],
            'sidebar_label' => $expectedChanges['sidebar_label'],
            'file_order' => $expectedChanges['file_order'],
            'user_id' => $user->id,
            'last_edited_by' => $user->email,
            'is_public' => $expectedChanges['is_public'],
            'pull_request_edit_session_id' => $expectedChanges['pull_request_edit_session_id'],
        ]);
    }

    private function createOrUpdateDocument(User $user, UserBranch $branch, string $status, int $categoryId, ?int $originalVersionId = null, ?int $pullRequestEditSessionId = null): DocumentVersion
    {
        $document = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'user_branch_id' => $branch->id,
            'status' => $status,
            'category_id' => $categoryId,
            'file_order' => 1,
            'slug' => 'old-slug',
            'sidebar_label' => 'Old Sidebar',
            'is_public' => false,
            'content' => 'old content',
            'last_edited_by' => $user->email,
            'pull_request_edit_session_id' => $pullRequestEditSessionId,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalVersionId ?? $document->id,
            'current_version_id' => $document->id,
        ]);

        return $document;
    }

    private function createUpdateDocumentDto(array $data): UpdateDocumentDto
    {
        // モックリクエストを作成
        $request = Mockery::mock(UpdateDocumentRequest::class);
        $request->shouldReceive('all')->andReturn($data);

        // DTOを作成
        $payload = [
            'category_path' => $data['category_path'] ?? null,
            'current_document_id' => $data['current_document_id'],
            'sidebar_label' => $data['sidebar_label'],
            'content' => $data['content'],
            'is_public' => $data['is_public'],
            'slug' => $data['slug'],
            'file_order' => $data['file_order'] ?? null,
            'edit_pull_request_id' => $data['edit_pull_request_id'] ?? null,
            'pull_request_edit_token' => $data['pull_request_edit_token'] ?? null,
        ];

        return UpdateDocumentDto::fromArray($payload);
    }

    #[Test]
    public function update_merged_document_by_same_user_before_submit_pull_request(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::MERGED->value);

        // update existing document
        $newBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($newBranch->id);

        // 編集権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => false,
                'pull_request_edit_session_id' => null,
            ]);

        // 変更できる箇所は全て変更する
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '', // category_pathは変更できないので、空文字
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $user, [
            'user_branch_id' => $newBranch->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => null,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $newBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は発生しない
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'current_version_id' => $resultDocumentVersion->id,
        ]);
    }

    #[Test]
    public function update_merged_document_by_other_user_before_submit_pull_request(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        // ユーザーAがドキュメントを作成
        $userA = User::factory()->create();
        $branchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branchA->id, 'status' => PullRequestStatus::MERGED->value]);

        [$userA, $branchA, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($userA, $branchA, $pullRequest, 1, DocumentStatus::MERGED->value);

        // ユーザーB(login user)がドキュメントを更新
        $userB = User::factory()->create();
        $newBranch = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), null)
            ->andReturn($newBranch->id);

        // 編集権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => false,
                'pull_request_edit_session_id' => null,
            ]);

        // 変更できる箇所は全て変更する
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '', // category_pathは変更できないので、空文字
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $userB);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 新しいドキュメントバージョンが作成される
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $userB, [
            'user_branch_id' => $newBranch->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => null,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $newBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は発生しない
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'current_version_id' => $resultDocumentVersion->id,
        ]);
    }

    #[Test]
    public function update_draft_document_by_same_user_before_submit_pull_request(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::OPENED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::DRAFT->value);

        // update existing document
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($branch->id);

        // 編集権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => false,
                'pull_request_edit_session_id' => null,
            ]);

        // 変更できる箇所は全て変更する
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '', // category_pathは変更できないので、空文字
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 既存のドキュメントは論理削除される
        $this->assertSoftDeleted('document_versions', [
            'id' => $existingDocument->id,
        ]);

        // 新しいドキュメントバージョンが作成される
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $user, [
            'user_branch_id' => $branch->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => null,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は発生しない
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'current_version_id' => $resultDocumentVersion->id,
        ]);
    }

    #[Test]
    public function update_draft_document_by_same_user_on_edit_session(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);
        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::MERGED->value);

        // 新しいブランチを作成
        $newBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequestB = PullRequest::factory()->create(['user_branch_id' => $newBranch->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $pullRequestB->id, 'user_id' => $user->id]);

        // 既存のドキュメントを編集して、draftにする
        $draftCreatedDocumentOnEditSession = $this->createOrUpdateDocument($user, $newBranch, DocumentStatus::DRAFT->value, 1, $existingDocument->id, $pullRequestEditSession->id);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequestB->id)
            ->andReturn($newBranch->id);

        // 編集セッション用の権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $draftCreatedDocumentOnEditSession->id),
                $newBranch->id,
                Mockery::on(fn ($u) => $u->id === $user->id),
                $pullRequestB->id,
                $pullRequestEditSession->token
            )
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => true,
                'pull_request_edit_session_id' => $pullRequestEditSession->id,
            ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $draftCreatedDocumentOnEditSession->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
            'edit_pull_request_id' => $pullRequestB->id,
            'pull_request_edit_token' => $pullRequestEditSession->token,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 既存のMergedドキュメントは論理削除されない
        $this->assertDatabaseHas('document_versions', ['id' => $existingDocument->id, 'deleted_at' => null]);

        // draftCreatedDocumentOnEditSessionが論理削除される
        $this->assertSoftDeleted('document_versions', [
            'id' => $draftCreatedDocumentOnEditSession->id,
        ]);

        // 新しいドキュメントバージョンが作成される
        $this->assertDocumentUpdated($resultDocumentVersion, $draftCreatedDocumentOnEditSession, $user, [
            'user_branch_id' => $newBranch->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $newBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftCreatedDocumentOnEditSession->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は1件のみ
        $this->assertDatabaseCount('pull_request_edit_session_diffs', 1);

        // セッション差分の内容を検証
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftCreatedDocumentOnEditSession->id,
            'current_version_id' => $resultDocumentVersion->id,
            'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
        ]);
    }

    #[Test]
    public function update_pushed_document_by_same_user_on_edit_session(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);
        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::MERGED->value);

        // 新しいブランチを作成
        $newBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequestB = PullRequest::factory()->create(['user_branch_id' => $newBranch->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $pullRequestB->id, 'user_id' => $user->id]);

        // 既存のドキュメントを編集して、pushedにする
        $pushedCreatedDocumentOnEditSession = $this->createOrUpdateDocument($user, $newBranch, DocumentStatus::PUSHED->value, 1, $existingDocument->id, $pullRequestEditSession->id);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequestB->id)
            ->andReturn($newBranch->id);

        // 編集セッション用の権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $pushedCreatedDocumentOnEditSession->id),
                $newBranch->id,
                Mockery::on(fn ($u) => $u->id === $user->id),
                $pullRequestB->id,
                $pullRequestEditSession->token
            )
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => true,
                'pull_request_edit_session_id' => $pullRequestEditSession->id,
            ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $pushedCreatedDocumentOnEditSession->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
            'edit_pull_request_id' => $pullRequestB->id,
            'pull_request_edit_token' => $pullRequestEditSession->token,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 既存のMergedドキュメントは論理削除されない
        $this->assertDatabaseHas('document_versions', ['id' => $existingDocument->id, 'deleted_at' => null]);

        // pushedCreatedDocumentOnEditSessionも論理削除されない
        $this->assertDatabaseHas('document_versions', ['id' => $pushedCreatedDocumentOnEditSession->id, 'deleted_at' => null]);

        // 新しいドキュメントバージョンが作成される
        $this->assertDocumentUpdated($resultDocumentVersion, $pushedCreatedDocumentOnEditSession, $user, [
            'user_branch_id' => $newBranch->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $newBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedCreatedDocumentOnEditSession->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は1件のみ
        $this->assertDatabaseCount('pull_request_edit_session_diffs', 1);

        // セッション差分の内容を検証
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedCreatedDocumentOnEditSession->id,
            'current_version_id' => $resultDocumentVersion->id,
            'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
        ]);
    }

    #[Test]
    public function update_merged_document_by_same_user_on_edit_session(): void
    {
        // Arrange
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::MERGED->value);

        // 同じユーザーがedit_session上で既存のmergedドキュメントを更新
        $newBranchB = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequestB = PullRequest::factory()->create(['user_branch_id' => $newBranchB->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $pullRequestB->id, 'user_id' => $user->id]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequestB->id)
            ->andReturn($newBranchB->id);

        // 編集セッション用の権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $existingDocument->id),
                $newBranchB->id,
                Mockery::on(fn ($u) => $u->id === $user->id),
                $pullRequestB->id,
                $pullRequestEditSession->token
            )
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => true,
                'pull_request_edit_session_id' => $pullRequestEditSession->id,
            ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
            'edit_pull_request_id' => $pullRequestB->id,
            'pull_request_edit_token' => $pullRequestEditSession->token,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 既存のMergedドキュメントは論理削除されない
        $this->assertDatabaseHas('document_versions', ['id' => $existingDocument->id, 'deleted_at' => null]);

        // 新しいドキュメントバージョンが作成される
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $user, [
            'user_branch_id' => $newBranchB->id,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $newBranchB->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
            'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
        ]);
    }

    #[Test]
    public function update_merged_document_by_other_user_on_edit_session(): void
    {
        // Arrange
        // ユーザーAがドキュメントを作成してマージ
        $userA = User::factory()->create();
        $branchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => false]);
        $pullRequestA = PullRequest::factory()->create(['user_branch_id' => $branchA->id, 'status' => PullRequestStatus::MERGED->value]);

        [$userA, $branchA, $pullRequestA, $existingDocument] = $this->setUpForSubmittedDocumentByUser($userA, $branchA, $pullRequestA, 1, DocumentStatus::MERGED->value);

        // ユーザーB（別のユーザー）が編集セッション上で既存のマージ済みドキュメントを更新
        $userB = User::factory()->create();
        $branchB = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);
        $pullRequestB = PullRequest::factory()->create(['user_branch_id' => $branchB->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $pullRequestB->id, 'user_id' => $userB->id]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), $pullRequestB->id)
            ->andReturn($branchB->id);

        // 編集セッション用の権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $existingDocument->id),
                $branchB->id,
                Mockery::on(fn ($u) => $u->id === $userB->id),
                $pullRequestB->id,
                $pullRequestEditSession->token
            )
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => true,
                'pull_request_edit_session_id' => $pullRequestEditSession->id,
            ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content by userB',
            'slug' => 'new-slug-by-userB',
            'sidebar_label' => 'New Sidebar by UserB',
            'is_public' => true,
            'edit_pull_request_id' => $pullRequestB->id,
            'pull_request_edit_token' => $pullRequestEditSession->token,
        ]);

        // Act
        $result = $this->useCase->execute($dto, $userB);
        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 既存のマージ済みドキュメントは論理削除されない
        $this->assertDatabaseHas('document_versions', ['id' => $existingDocument->id, 'deleted_at' => null]);

        // 新しいドキュメントバージョンが作成される（他のユーザーによる編集）
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $userB, [
            'user_branch_id' => $branchB->id,
            'content' => 'new content by userB',
            'slug' => 'new-slug-by-userB',
            'sidebar_label' => 'New Sidebar by UserB',
            'file_order' => 2,
            'is_public' => true,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
        ]);

        // 編集開始バージョン記録
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branchB->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分
        $this->assertDatabaseCount('pull_request_edit_session_diffs', 1);
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
            'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
        ]);
    }

    #[Test]
    public function update_document_when_edit_session_is_invalid(): void
    {
        // Arrange
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::MERGED->value);

        // 新しいブランチと無効な編集セッション
        $newBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $newPullRequest = PullRequest::factory()->create(['user_branch_id' => $newBranch->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $newPullRequest->id, 'user_id' => $user->id, 'token' => 'correct-token']);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $newPullRequest->id)
            ->andReturn($newBranch->id);

        // 無効なセッションの権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $existingDocument->id),
                $newBranch->id,
                Mockery::on(fn ($u) => $u->id === $user->id),
                $newPullRequest->id,
                'invalid-token'
            )
            ->andThrow(new InvalidArgumentException('無効な編集セッションです'));

        // 無効なトークンでDTOを作成
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
            'edit_pull_request_id' => $newPullRequest->id,
            'pull_request_edit_token' => 'invalid-token',
        ]);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute($dto, $user);
    }

    #[Test]
    public function update_draft_document_on_different_user_branch(): void
    {
        // Arrange
        $userA = User::factory()->create();
        $userBranchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => true]);

        $createdDocumentByUserA = $this->createOrUpdateDocument($userA, $userBranchA, DocumentStatus::DRAFT->value, 1);

        // 別のユーザーが編集したdraftドキュメントを更新
        $userB = User::factory()->create();
        $userBranchB = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), null)
            ->andReturn($userBranchB->id);

        // 編集権限なしのモック設定
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $createdDocumentByUserA->id),
                $userBranchB->id,
                Mockery::on(fn ($u) => $u->id === $userB->id),
                null,
                null
            )
            ->andThrow(new InvalidArgumentException('他のユーザーの未マージドキュメントは編集できません'));

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $createdDocumentByUserA->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content on different branch',
            'slug' => 'new-slug-different-branch',
            'sidebar_label' => 'New Sidebar Different Branch',
            'is_public' => true,
        ]);

        // Act & Assert
        // 他のユーザーが編集したdraftドキュメントは編集できない
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('他のユーザーの未マージドキュメントは編集できません');
        $this->useCase->execute($dto, $userB);
    }

    #[Test]
    public function update_pushed_document_on_different_user_branch(): void
    {
        // Arrange
        $userA = User::factory()->create();
        $userBranchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => true]);
        PullRequest::factory()->create(['user_branch_id' => $userBranchA->id, 'status' => PullRequestStatus::MERGED->value]);

        $createdDocumentByUserA = $this->createOrUpdateDocument($userA, $userBranchA, DocumentStatus::PUSHED->value, 1);

        // 別のユーザーが編集したpushedドキュメントを更新
        $userB = User::factory()->create();
        $userBranchB = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), null)
            ->andReturn($userBranchB->id);

        // 編集権限なしのモック設定
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $createdDocumentByUserA->id),
                $userBranchB->id,
                Mockery::on(fn ($u) => $u->id === $userB->id),
                null,
                null
            )
            ->andThrow(new InvalidArgumentException('他のユーザーの未マージドキュメントは編集できません'));

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $createdDocumentByUserA->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content on different branch',
            'slug' => 'new-slug-different-branch',
            'sidebar_label' => 'New Sidebar Different Branch',
            'is_public' => true,
        ]);

        // Act & Assert
        // 他のユーザーが編集したpushedドキュメントは編集できない
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('他のユーザーの未マージドキュメントは編集できません');
        $this->useCase->execute($dto, $userB);
    }

    #[Test]
    public function update_document_with_same_requests_as_existing_document(): void
    {
        // Arrange
        $userA = User::factory()->create();
        $userBranchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => false]);
        $pullRequestA = PullRequest::factory()->create([
            'user_branch_id' => $userBranchA->id,
            'status' => PullRequestStatus::CLOSED->value,
        ]);

        [$userA, $userBranchA, $pullRequestA, $existing] =
            $this->setUpForSubmittedDocumentByUser($userA, $userBranchA, $pullRequestA, 1, DocumentStatus::MERGED->value);

        // 別ユーザーBが「差分なし」で更新を試みる
        $userB = User::factory()->create();
        $userBranchB = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), null)
            ->andReturn($userBranchB->id);

        // 「差分なし」DTO
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existing->id,
            'category_path' => '', // ここは '' or null でOK（UseCase内で解決＆既存category_idを優先する実装なら影響なし）
            'file_order' => $existing->file_order,
            'content' => $existing->content,
            'slug' => $existing->slug,
            'sidebar_label' => $existing->sidebar_label,
            'is_public' => $existing->is_public,
        ]);

        // 差分なしの場合のモック設定
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->with(
                Mockery::on(fn ($doc) => $doc->id === $existing->id),
                $userBranchB->id,
                Mockery::on(fn ($u) => $u->id === $userB->id),
                null,
                null
            )
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => false,
                'pull_request_edit_session_id' => null,
            ]);

        // 既存のモックをリセットして、差分なしを返すように設定
        $this->documentDiffService = Mockery::mock(DocumentDiffService::class);
        $this->documentDiffService
            ->shouldReceive('hasDocumentChanges')
            ->once()
            ->andReturn(false);

        // UseCaseを再作成してモックを反映
        $this->useCase = new UpdateDocumentUseCase(
            $this->documentService,
            $this->userBranchService,
            $this->documentCategoryService,
            $this->documentDiffService,
            $this->versionEditPermissionService
        );

        // 差分なしなら updateFileOrder は呼ばれない
        $this->documentService->shouldReceive('updateFileOrder')->never();

        // カウント退避（前後差で検証）
        $beforeVersions = DocumentVersion::count();
        $beforeEdits = EditStartVersion::count();
        $beforeDiffs = PullRequestEditSessionDiff::count();

        // Act
        $result = $this->useCase->execute($dto, $userB);

        // Assert: 返り値（no change）だけで DocumentVersion は返さない想定
        $this->assertIsArray($result);
        $this->assertSame('no_changes_exist', $result['result']);

        // 新規バージョンなし・既存は削除されない
        $this->assertSame($beforeVersions, DocumentVersion::count());
        $this->assertDatabaseHas('document_versions', [
            'id' => $existing->id,
            'deleted_at' => null,
        ]);

        // EditStartVersion 追加なし
        $this->assertSame($beforeEdits, EditStartVersion::count());

        // Diff 追加なし
        $this->assertSame($beforeDiffs, PullRequestEditSessionDiff::count());

        // 既存内容はそのまま（オプションの確認）
        $this->assertDatabaseHas('document_versions', [
            'id' => $existing->id,
            'category_id' => $existing->category_id,
            'user_branch_id' => $userBranchA->id,
            'file_path' => $existing->file_path,
            'status' => DocumentStatus::MERGED->value,
            'content' => $existing->content,
            'slug' => $existing->slug,
            'sidebar_label' => $existing->sidebar_label,
            'file_order' => $existing->file_order,
            'user_id' => $userA->id,
            'last_edited_by' => $userA->email,
            'is_public' => $existing->is_public,
            'pull_request_edit_session_id' => null,
        ]);
    }

    #[Test]
    public function update_document_with_partially_same_requests_as_existing_document(): void
    {
        // Arrange
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::OPENED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::DRAFT->value);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($branch->id);

        // 編集権限チェックモック
        $this->versionEditPermissionService
            ->shouldReceive('hasEditPermission')
            ->once()
            ->andReturn([
                'can_edit' => true,
                'has_re_edit_session' => false,
                'pull_request_edit_session_id' => null,
            ]);

        // // 差分があることを示すモック設定
        // $this->documentDiffService = Mockery::mock(DocumentDiffService::class);
        // $this->documentDiffService
        //     ->shouldReceive('hasDocumentChanges')
        //     ->once()
        //     ->andReturn(true);

        // // UseCaseを再作成してモックを反映
        // $this->useCase = new UpdateDocumentUseCase(
        //     $this->documentService,
        //     $this->userBranchService,
        //     $this->documentCategoryService,
        //     $this->documentDiffService,
        //     $this->versionEditPermissionService
        // );

        // 一部の項目のみ変更したDTOを作成
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '',
            'file_order' => $existingDocument->file_order, // 同じ
            'content' => 'updated content', // 変更
            'slug' => $existingDocument->slug, // 同じ
            'sidebar_label' => 'Updated Sidebar', // 変更
            'is_public' => $existingDocument->is_public, // 同じ
        ]);

        // カウント退避（前後差で検証）
        $beforeVersions = DocumentVersion::count();
        $beforeEdits = EditStartVersion::count();
        $beforeDiffs = PullRequestEditSessionDiff::count();

        // Act
        $result = $this->useCase->execute($dto, $user);

        $resultDocumentVersion = $result['document_version'];

        // Assert
        // 元のドキュメントは論理削除される
        $this->assertSoftDeleted('document_versions', ['id' => $existingDocument->id]);

        // 一部変更された新しいドキュメントバージョンが作成される
        // 1つが論理削除され、1つが新規作成されるため、削除されていないレコード数は変わらない
        $this->assertSame($beforeVersions, DocumentVersion::count());
        // 削除されたレコードも含む総数は+1される
        $this->assertSame(1, DocumentVersion::onlyTrashed()->count());
        $this->assertDocumentUpdated($resultDocumentVersion, $existingDocument, $user, [
            'user_branch_id' => $branch->id,
            'content' => 'updated content',
            'slug' => $existingDocument->slug,
            'sidebar_label' => 'Updated Sidebar',
            'file_order' => $existingDocument->file_order,
            'is_public' => $existingDocument->is_public,
            'pull_request_edit_session_id' => null,
        ]);

        // 編集開始バージョンも作成される
        $this->assertSame($beforeEdits + 1, EditStartVersion::count());
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $resultDocumentVersion->id,
        ]);

        // セッション差分は作成されない
        $this->assertSame($beforeDiffs, PullRequestEditSessionDiff::count());
    }

    #[Test]
    public function update_document_throws_exception_when_target_document_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();

        // 存在しないドキュメントIDでDTOを作成
        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 999999, // 存在しないID
            'category_path' => '',
            'file_order' => 1,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
        ]);

        // Act & Assert
        $this->expectException(TargetDocumentNotFoundException::class);
        $this->expectExceptionMessage('編集対象のドキュメントが見つかりません');

        $this->useCase->execute($dto, $user);
    }

    #[Test]
    public function update_document_failed_when_exception_is_thrown(): void
    {
        // Arrange
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::OPENED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1, DocumentStatus::DRAFT->value);

        // UserBranchServiceで例外をスローするように設定
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andThrow(new \Exception('Service error'));

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => $existingDocument->id,
            'category_path' => '',
            'file_order' => 2,
            'content' => 'new content',
            'slug' => 'new-slug',
            'sidebar_label' => 'New Sidebar',
            'is_public' => true,
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service error');
        $this->useCase->execute($dto, $user);
    }
}
