<?php

namespace Tests\Unit\UseCases;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Enums\PullRequestStatus;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\UseCases\Document\UpdateDocumentUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentService = Mockery::mock(DocumentService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);

        // updateFileOrder は入力に応じてそのまま返す簡易モック
        $this->documentService
            ->shouldReceive('updateFileOrder')
            ->andReturnUsing(function ($fileOrder, $categoryId, $oldFileOrder, $userBranchId, $documentId) {
                return $fileOrder ?? ($oldFileOrder + 1);
            });

        // DocumentCategoryServiceのモックを追加
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->andReturn(1);

        $this->useCase = new UpdateDocumentUseCase(
            $this->documentService,
            $this->userBranchService,
            $this->documentCategoryService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setUpForSubmittedDocumentByUser(User $user, UserBranch $branch, PullRequest $pullRequest, int $categoryId): array
    {
        $existingDocument = $this->createDocument($user, $branch, DocumentStatus::MERGED->value, $categoryId);

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

    private function createDocument(User $user, UserBranch $branch, string $status, int $categoryId, ?int $originalVersionId = null, ?int $pullRequestEditSessionId = null): DocumentVersion
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
    public function update_document_by_same_user_before_submit_pull_request(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1);

        // update existing document
        $newBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($newBranch->id);

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

        // Assert
        $this->assertDocumentUpdated($result, $existingDocument, $user, [
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
            'current_version_id' => $result->id,
        ]);

        // セッション差分は発生しない
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'current_version_id' => $result->id,
        ]);
    }

    #[Test]
    public function update_document_by_other_user_before_submit_pull_request(): void
    {
        // Arrange
        // 既存のドキュメントがある状態をset up
        // ユーザーAがドキュメントを作成
        $userA = User::factory()->create();
        $branchA = UserBranch::factory()->create(['user_id' => $userA->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branchA->id, 'status' => PullRequestStatus::MERGED->value]);

        [$userA, $branchA, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($userA, $branchA, $pullRequest, 1);

        // ユーザーB(login user)がドキュメントを更新
        $userB = User::factory()->create();
        $newBranch = UserBranch::factory()->create(['user_id' => $userB->id, 'is_active' => true]);
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), null)
            ->andReturn($newBranch->id);

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

        // Assert
        $this->assertDocumentUpdated($result, $existingDocument, $userB, [
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
            'current_version_id' => $result->id,
        ]);

        // セッション差分は発生しない
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'current_version_id' => $result->id,
        ]);
    }

    #[Test]
    public function update_merged_document_by_same_user_on_edit_session(): void
    {
        // Arrange
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id, 'status' => PullRequestStatus::MERGED->value]);

        [$user, $branch, $pullRequest, $existingDocument] = $this->setUpForSubmittedDocumentByUser($user, $branch, $pullRequest, 1);

        // 同じユーザーがedit_session上で既存のmergedドキュメントを更新
        // updateを行うPRのset up
        $newBranchB = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $this->createDocument($user, $newBranchB, DocumentStatus::PUSHED->value, 1);
        $pullRequestB = PullRequest::factory()->create(['user_branch_id' => $newBranchB->id, 'status' => PullRequestStatus::OPENED->value]);
        $pullRequestEditSession = PullRequestEditSession::factory()->create(['pull_request_id' => $pullRequestB->id]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequestB->id)
            ->andReturn($newBranchB->id);

        // 実際のPullRequestEditSessionデータを作成（モックの代わり）
        $pullRequestEditSession->update([
            'pull_request_id' => $pullRequestB->id,
            'user_id' => $user->id,
            'token' => 'token',
            'finished_at' => null,
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
            'pull_request_edit_token' => 'token',
        ]);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertDocumentUpdated($result, $existingDocument, $user, [
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
            'current_version_id' => $result->id,
        ]);

        // セッション差分
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $existingDocument->id,
            'current_version_id' => $result->id,
            'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
        ]);
    }

    #[Test]
    public function update_draft_document_by_same_user_on_edit_session(): void {}

    #[Test]
    public function update_pushed_document_by_same_user_on_edit_session(): void {}

    #[Test]
    public function update_merged_document_by_other_user_on_edit_session(): void {}

    #[Test]
    public function update_document_with_same_requests_as_existing_document(): void {}

    #[Test]
    public function update_document_with_partially_same_requests_as_existing_document(): void {}

    #[Test]
    public function update_document_returns_error_when_target_not_found(): void {}

    #[Test]
    public function update_document_failed_when_exception_is_thrown(): void {}
}
