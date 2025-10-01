<?php

namespace Tests\Unit\UseCases\Document;

use App\Consts\Flag;
use App\Dto\UseCase\Document\DestroyDocumentDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\UseCases\Document\DestroyDocumentUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DestroyDocumentUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private DestroyDocumentUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $userBranch;

    private DocumentCategoryEntity $documentCategoryEntity;

    private DocumentEntity $documentEntity;

    private DocumentVersion $existingDocument;

    private DocumentCategory $existingDocumentCategory;

    private EditStartVersion $existingDocumentCategoryEditStartVersion;

    private EditStartVersion $existingDocumentEditStartVersion;

    private $documentService;

    private $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスのモック作成
        $this->documentService = Mockery::mock(DocumentService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        $this->useCase = new DestroyDocumentUseCase(
            $this->userBranchService,
            $this->documentService
        );

        // テストデータの準備
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberの作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // UserBranchの作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // DocumentEntityの作成
        $this->documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryEntityの作成
        $this->documentCategoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryの作成
        $this->existingDocumentCategory = DocumentCategory::factory()->create([
            'entity_id' => $this->documentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $this->existingDocumentCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingDocumentCategory->id,
            'current_version_id' => $this->existingDocumentCategory->id,
        ]);

        // 既存のDocumentVersionを作成
        $this->existingDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'category_entity_id' => $this->documentCategoryEntity->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Existing Document',
            'description' => 'Existing description',
        ]);

        $this->existingDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $this->existingDocument->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_destroys_document_without_pull_request(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals($this->documentEntity->id, $result->entity_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
        $this->assertEquals($this->existingDocument->description, $result->description);
        $this->assertEquals($this->existingDocument->category_entity_id, $result->category_entity_id);
        $this->assertEquals($this->existingDocument->title, $result->title);
        $this->assertEquals(Flag::TRUE, $result->is_deleted);
        $this->assertNotNull($result->deleted_at);
        $this->assertNull($result->pull_request_edit_session_id);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
        ]);

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $result->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_destroys_document_with_pull_request_edit_session(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditToken = 'test-token';
        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $this->user->id,
            'token' => $pullRequestEditToken,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: $pullRequest->id,
            pull_request_edit_token: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals($this->documentEntity->id, $result->entity_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
        $this->assertEquals($this->existingDocument->description, $result->description);
        $this->assertEquals($this->existingDocument->category_entity_id, $result->category_entity_id);
        $this->assertEquals($this->existingDocument->title, $result->title);
        $this->assertEquals(Flag::TRUE, $result->is_deleted);
        $this->assertNotNull($result->deleted_at);
        $this->assertEquals($pullRequestEditSession->id, $result->pull_request_edit_session_id);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
        ]);

        // PullRequestEditSessionDiffが作成されていることを確認
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
            'diff_type' => 'deleted',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_draft_document_deletes_existing_document(): void
    {
        // Arrange
        // DRAFTステータスの既存ドキュメントを作成
        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'category_entity_id' => $this->documentCategoryEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'title' => 'Draft Document',
            'description' => 'Draft description',
        ]);

        $draftDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn($draftDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals(Flag::TRUE, $result->is_deleted);
        $this->assertNotNull($result->deleted_at);

        // DRAFTドキュメントが削除されていることを確認
        $this->assertSoftDeleted('document_versions', [
            'id' => $draftDocument->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_non_draft_document_does_not_delete_existing_document(): void
    {
        // Arrange
        // MERGEDステータスの既存ドキュメント（デフォルトセットアップで作成済み）
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals(Flag::TRUE, $result->is_deleted);
        $this->assertNotNull($result->deleted_at);

        // MERGEDドキュメントは削除されていないことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $this->existingDocument->id,
            'deleted_at' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        // OrganizationMemberを削除して組織が見つからない状況を作る
        OrganizationMember::where('user_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->delete();

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_document_entity_not_found(): void
    {
        // Arrange
        $nonExistentEntityId = 999999;
        $dto = new DestroyDocumentDto(
            document_entity_id: $nonExistentEntityId,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_existing_document_not_found(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn(null); // ドキュメントが見つからない

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_invalid_pull_request_edit_session(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        // 無効なトークンで実行
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: $pullRequest->id,
            pull_request_edit_token: 'invalid-token'
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, 'invalid-token')
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertNull($result->pull_request_edit_session_id); // 無効なセッションIDは設定されない

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $result->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_finished_pull_request_edit_session(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditToken = 'test-token';
        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $this->user->id,
            'token' => $pullRequestEditToken,
            'finished_at' => now(), // 終了済みのセッション
        ]);

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: $pullRequest->id,
            pull_request_edit_token: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertNull($result->pull_request_edit_session_id); // 終了済みセッションは無視される

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $result->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_different_user_pull_request_edit_session(): void
    {
        // Arrange
        $anotherUser = User::factory()->create();
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditToken = 'test-token';
        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $anotherUser->id, // 別のユーザー
            'token' => $pullRequestEditToken,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: $pullRequest->id,
            pull_request_edit_token: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertNull($result->pull_request_edit_session_id); // 別ユーザーのセッションは無視される

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $result->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_rollback_on_exception_from_user_branch_service(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andThrow(new \Exception('UserBranchService error'));

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UserBranchService error');

        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto, $this->user);
        } catch (\Exception $e) {
            // ロールバックが実行されているかを確認
            $this->assertEquals($initialDocumentCount, DocumentVersion::count());
            $this->assertEquals($initialEditStartVersionCount, EditStartVersion::count());

            throw $e;
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_rollback_on_exception_from_document_service(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->andThrow(new \Exception('DocumentService error'));

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DocumentService error');

        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto, $this->user);
        } catch (\Exception $e) {
            // ロールバックが実行されているかを確認
            $this->assertEquals($initialDocumentCount, DocumentVersion::count());
            $this->assertEquals($initialEditStartVersionCount, EditStartVersion::count());

            throw $e;
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_rollback_on_exception_during_document_creation(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn($this->existingDocument);

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // データベースエラーを強制的に発生させるため、存在しないカテゴリIDを設定
        $this->existingDocument->category_entity_id = 999999;

        // Act & Assert
        $this->expectException(\Exception::class);

        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto, $this->user);
        } catch (\Exception $e) {
            // ロールバックが実行されているかを確認
            $this->assertEquals($initialDocumentCount, DocumentVersion::count());
            $this->assertEquals($initialEditStartVersionCount, EditStartVersion::count());

            throw $e;
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_verifies_correct_data_is_passed_to_services(): void
    {
        // Arrange
        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: null,
            pull_request_edit_token: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, null)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert - すべてのサービスが正しいパラメータで呼び出されたことを確認
        $this->assertInstanceOf(DocumentVersion::class, $result);

        // DocumentVersionが正しいデータで作成されたことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $result->id,
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'status' => DocumentStatus::DRAFT->value,
            'description' => $this->existingDocument->description,
            'category_entity_id' => $this->existingDocument->category_entity_id,
            'title' => $this->existingDocument->title,
            'is_deleted' => Flag::TRUE,
            'pull_request_edit_session_id' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_updates_or_creates_pull_request_edit_session_diff(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditToken = 'test-token';
        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $this->user->id,
            'token' => $pullRequestEditToken,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        // 既存のPullRequestEditSessionDiffを作成
        $existingDiff = PullRequestEditSessionDiff::create([
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $this->existingDocument->id,
            'diff_type' => 'updated',
        ]);

        $dto = new DestroyDocumentDto(
            document_entity_id: $this->documentEntity->id,
            edit_pull_request_id: $pullRequest->id,
            pull_request_edit_token: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);

        // 既存のDiffが更新されていることを確認
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'id' => $existingDiff->id,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
            'diff_type' => 'deleted',
        ]);

        // 古いDiffレコードが削除されていないことを確認（updateOrCreateなので）
        $this->assertEquals(1, PullRequestEditSessionDiff::where('pull_request_edit_session_id', $pullRequestEditSession->id)
            ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
            ->where('original_version_id', $this->existingDocument->id)
            ->count());
    }
}
