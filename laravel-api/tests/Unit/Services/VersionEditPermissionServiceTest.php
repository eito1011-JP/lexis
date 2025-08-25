<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\PullRequestEditSession;
use App\Services\VersionEditPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VersionEditPermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private VersionEditPermissionService $service;

    private $mockPullRequestEditSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPullRequestEditSession = Mockery::mock(PullRequestEditSession::class);
        $this->service = new VersionEditPermissionService($this->mockPullRequestEditSession);
    }

    /**
     * 編集セッションが指定された場合の権限チェックのテスト
     */
    public function test_has_edit_permission_with_valid_edit_session(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::DRAFT->value);

        $user = (object) ['id' => 1];
        $userBranchId = 2;
        $editPullRequestId = 100;
        $pullRequestEditToken = 'valid-token';
        $expectedSessionId = 10;

        // Mock validSession
        $mockSession = Mockery::mock(PullRequestEditSession::class);
        $mockSession->id = $expectedSessionId;

        // Mock PullRequestEditSession::findValidSession
        PullRequestEditSession::shouldReceive('findValidSession')
            ->with($editPullRequestId, $pullRequestEditToken, $user->id)
            ->andReturn($mockSession);

        // Act
        $result = $this->service->hasEditPermission(
            $existingDocument,
            $userBranchId,
            $user,
            $editPullRequestId,
            $pullRequestEditToken
        );

        // Assert
        $this->assertTrue($result['can_edit']);
        $this->assertEquals($expectedSessionId, $result['pull_request_edit_session_id']);
        $this->assertTrue($result['is_edit_session_specified']);
    }

    /**
     * 無効な編集セッションが指定された場合のテスト
     */
    public function test_has_edit_permission_with_invalid_edit_session(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $user = (object) ['id' => 1];
        $userBranchId = 2;
        $editPullRequestId = 100;
        $pullRequestEditToken = 'invalid-token';

        // Mock PullRequestEditSession::findValidSession
        PullRequestEditSession::shouldReceive('findValidSession')
            ->with($editPullRequestId, $pullRequestEditToken, $user->id)
            ->andReturn(null);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('無効な編集セッションです');

        $this->service->hasEditPermission(
            $existingDocument,
            $userBranchId,
            $user,
            $editPullRequestId,
            $pullRequestEditToken
        );
    }

    /**
     * 編集セッションが指定されていない場合のテスト
     */
    public function test_has_edit_permission_without_edit_session(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::MERGED->value);

        $user = (object) ['id' => 1];
        $userBranchId = 1; // 同じユーザーブランチ

        // Act
        $result = $this->service->hasEditPermission(
            $existingDocument,
            $userBranchId,
            $user
        );

        // Assert
        $this->assertTrue($result['can_edit']);
        $this->assertNull($result['pull_request_edit_session_id']);
        $this->assertFalse($result['is_edit_session_specified']);
    }

    /**
     * 編集権限がない場合のテスト
     */
    public function test_has_edit_permission_with_no_permission(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::DRAFT->value);

        $user = (object) ['id' => 1];
        $userBranchId = 2; // 異なるユーザーブランチ

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('他のユーザーの未マージドキュメントは編集できません');

        $this->service->hasEditPermission(
            $existingDocument,
            $userBranchId,
            $user
        );
    }

    /**
     * canEditDocument - 編集セッション中の場合のテスト
     */
    public function test_can_edit_document_with_edit_session(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $userBranchId = 2;
        $pullRequestEditSessionId = 10;

        // Act
        $result = $this->service->canEditDocument(
            $existingDocument,
            $userBranchId,
            $pullRequestEditSessionId
        );

        // Assert
        $this->assertTrue($result);
    }

    /**
     * canEditDocument - 同じユーザーブランチの場合のテスト
     */
    public function test_can_edit_document_with_same_user_branch(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);

        $userBranchId = 1;

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * canEditDocument - 他のユーザーのDRAFTドキュメントの場合のテスト
     */
    public function test_can_edit_document_with_other_user_draft_document(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::DRAFT->value);

        $userBranchId = 2;

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * canEditDocument - 他のユーザーのPUSHEDドキュメントの場合のテスト
     */
    public function test_can_edit_document_with_other_user_pushed_document(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::PUSHED->value);

        $userBranchId = 2;

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * canEditDocument - 他のユーザーのMERGEDドキュメントの場合のテスト
     */
    public function test_can_edit_document_with_other_user_merged_document(): void
    {
        // Arrange
        $existingDocument = Mockery::mock(DocumentVersion::class);
        $existingDocument->shouldReceive('getAttribute')->with('user_branch_id')->andReturn(1);
        $existingDocument->shouldReceive('getAttribute')->with('status')->andReturn(DocumentStatus::MERGED->value);

        $userBranchId = 2;

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
