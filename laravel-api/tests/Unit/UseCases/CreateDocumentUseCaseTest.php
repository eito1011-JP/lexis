<?php

namespace Tests\Unit\UseCases;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateDocumentUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private CreateDocumentUseCase $useCase;

    private DocumentService $documentService;

    private UserBranchService $userBranchService;

    private PullRequestEditSessionService $pullRequestEditSessionService;

    private DocumentCategoryService $documentCategoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userBranchService = Mockery::mock(UserBranchService::class);
        $this->pullRequestEditSessionService = Mockery::mock(PullRequestEditSessionService::class);
        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);
        $this->documentService = Mockery::mock(DocumentService::class);

        // DocumentServiceのモックメソッドを設定
        $this->documentService
            ->shouldReceive('normalizeFileOrder')
            ->andReturnUsing(function ($fileOrder, $categoryId) {
                return $fileOrder ?? 1;
            });

        $this->documentService
            ->shouldReceive('generateDocumentFilePath')
            ->andReturnUsing(function ($categoryPath, $slug) {
                return $categoryPath ? "{$categoryPath}/{$slug}.md" : "{$slug}.md";
            });

        $this->useCase = new CreateDocumentUseCase(
            $this->documentService,
            $this->userBranchService,
            $this->pullRequestEditSessionService,
            $this->documentCategoryService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUserAndActiveBranch(): array
    {
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return [$user, $branch];
    }

    #[Test]
    public function create_document_before_submit_pr(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserAndActiveBranch();

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($branch->id);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $request = [
            'category_path' => '',
            'slug' => 'new-doc',
            'sidebar_label' => 'New Doc',
            'content' => 'hello',
            'is_public' => true,
            'file_order' => null,
            // file_order 未指定で自動採番させる
        ];

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertTrue($result['success']);
        $doc = $result['document'];
        $this->assertInstanceOf(DocumentVersion::class, $doc);
        $this->assertSame($user->id, $doc->user_id);
        $this->assertSame($branch->id, $doc->user_branch_id);
        $this->assertNull($doc->pull_request_edit_session_id);
        $this->assertSame(DocumentStatus::DRAFT->value, $doc->status);

        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $doc->id,
            'current_version_id' => $doc->id,
        ]);
    }

    #[Test]
    public function create_document_belongs_to_category_before_submit_pr(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserAndActiveBranch();

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), null)
            ->andReturn($branch->id);

        $request = [
            'category_path' => 'parent/child',
            'slug' => 'new-doc',
            'sidebar_label' => 'New Doc',
            'content' => 'hello',
            'is_public' => true,
            'file_order' => null,
            // file_order 未指定で自動採番させる
        ];

        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
            'status' => 'merged',
        ]);

        $child = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $parent->id,
            'status' => 'merged',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($child->id);

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertTrue($result['success']);
        $doc = $result['document'];
        $this->assertInstanceOf(DocumentVersion::class, $doc);
        $this->assertSame($user->id, $doc->user_id);
        $this->assertSame($branch->id, $doc->user_branch_id);
        $this->assertNull($doc->pull_request_edit_session_id);
        $this->assertSame(DocumentStatus::DRAFT->value, $doc->status);
        $this->assertSame($child->id, $doc->category_id);
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $doc->id,
            'current_version_id' => $doc->id,
        ]);
    }

    #[Test]
    public function create_document_after_submit_pr_on_edit_session(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserAndActiveBranch();
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id]);
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $user->id,
            'finished_at' => null,
        ]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequest->id)
            ->andReturn($branch->id);

        $this->pullRequestEditSessionService
            ->shouldReceive('getPullRequestEditSessionId')
            ->once()
            ->with($pullRequest->id, 'valid-token', $user->id)
            ->andReturn($session->id);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $request = [
            'edit_pull_request_id' => $pullRequest->id,
            'pull_request_edit_token' => 'valid-token',
            'category_path' => '',
            'slug' => 'session-doc',
            'sidebar_label' => 'Session Doc',
            'content' => 'with session',
            'is_public' => false,
            'file_order' => 1,
        ];

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertTrue($result['success']);
        $doc = $result['document'];

        $this->assertDatabaseHas('document_versions', [
            'id' => $doc->id,
            'category_id' => 1,
            'user_id' => $user->id,
            'user_branch_id' => $branch->id,
            'pull_request_edit_session_id' => $session->id,
            'sidebar_label' => 'Session Doc',
            'content' => 'with session',
            'is_public' => false,
            'file_order' => 1,
            'slug' => 'session-doc',
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $branch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $doc->id,
            'current_version_id' => $doc->id,
        ]);

        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $session->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $doc->id,
            'diff_type' => 'created',
        ]);
    }

    #[Test]
    public function create_document_belongs_to_category_after_submit_pr_on_edit_session(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserAndActiveBranch();
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id]);
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $user->id,
            'finished_at' => null,
        ]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequest->id)
            ->andReturn($branch->id);

        $this->pullRequestEditSessionService
            ->shouldReceive('getPullRequestEditSessionId')
            ->once()
            ->with($pullRequest->id, 'valid-token', $user->id)
            ->andReturn($session->id);

        $request = [
            'edit_pull_request_id' => $pullRequest->id,
            'pull_request_edit_token' => 'valid-token',
            'category_path' => 'parent/child',
            'slug' => 'session-doc',
            'sidebar_label' => 'Session Doc',
            'content' => 'with session',
            'is_public' => false,
            'file_order' => 1,
        ];

        // 事前にカテゴリチェーンを作っておく（getIdFromPath が辿れるように）。
        // ルートは既存の DEFAULT_CATEGORY_ID(=1) を想定して parent をぶら下げる。
        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
            'status' => 'merged',
        ]);
        $child = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $parent->id,
            'status' => 'merged',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($child->id);

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertTrue($result['success']);
        /** @var DocumentVersion $doc */
        $doc = $result['document'];
        $this->assertSame($session->id, $doc->pull_request_edit_session_id);

        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $session->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'current_version_id' => $doc->id,
            'diff_type' => 'created',
        ]);
    }

    #[Test]
    public function create_document_after_submit_pr_on_edit_session_but_token_is_not_specified(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserAndActiveBranch();
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $branch->id]);

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), $pullRequest->id)
            ->andReturn($branch->id);

        $this->pullRequestEditSessionService
            ->shouldReceive('getPullRequestEditSessionId')
            ->never();

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $request = [
            'edit_pull_request_id' => $pullRequest->id,
            // 'pull_request_edit_token' => null,
            'slug' => 'no-token-doc',
            'file_order' => null,
        ];

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertTrue($result['success']);
        /** @var DocumentVersion $doc */
        $doc = $result['document'];
        $this->assertNull($doc->pull_request_edit_session_id);

        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            // いずれのレコードも作成されない
            'current_version_id' => $doc->id,
        ]);
    }

    #[Test]
    public function create_document_failed_when_exception_is_thrown(): void
    {
        // Arrange
        [$user] = $this->createUserAndActiveBranch();

        // fetchOrCreateActiveBranch が例外を投げるケース
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andThrow(new \Exception('boom'));

        $request = [
            'category_path' => '',
            'slug' => 'will-fail',
        ];

        // Act
        $result = $this->useCase->execute($request, $user);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertSame('ドキュメントの作成に失敗しました', $result['error']);
    }
}
