<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Consts\Flag;
use App\Enums\CommitChangeType;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestActivityAction;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\Commit;
use App\Models\CommitCategoryDiff;
use App\Models\CommitDocumentDiff;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\CommitService;
use App\Services\UserBranchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommitServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CommitService $service;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    private PullRequest $pullRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $userBranchService = new UserBranchService();
        $this->service = new CommitService($userBranchService);

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $this->userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $this->pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_return_null_when_no_edit_start_versions_exist(): void
    {
        // Act
        $result = $this->service->createCommitFromUserBranch(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            'Test commit message'
        );

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_create_commit_when_edit_start_versions_exist(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 前回のコミット時に作成されたバージョン（現在はMERGED状態）
        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        // 前回のコミット
        $previousCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        // 前回のコミット時のEditStartVersion（current_version_idにoriginalVersionが設定される）
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => true,
            'is_description_changed' => true,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        // 現在のバージョン（今回の編集内容）
        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

        // 今回の編集開始時に作成されたEditStartVersion（commit_idはまだnull）
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        // Act
        $result = $this->service->createCommitFromUserBranch(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'parent_commit_id' => $previousCommit->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::UPDATED->value,
            'is_title_changed' => true,
            'is_description_changed' => true,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_update_category_version_status_from_draft_to_pushed(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $previousCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        CommitCategoryDiff::factory()->create([
            'commit_id' => $previousCommit->id,
            'category_entity_id' => $categoryEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        $currentVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        // Act
        $result = $this->service->createCommitFromUserBranch(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $currentVersion->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $currentVersion->status);

        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'user_id' => $this->user->id,
            'parent_commit_id' => $previousCommit->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_category_diffs', [
            'commit_id' => $result->id,
            'category_entity_id' => $categoryEntity->id,
            'change_type' => CommitChangeType::UPDATED->value,
            'is_title_changed' => true,
            'is_description_changed' => true,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_create_commit_without_parent_commit(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            collect([$editStartVersion]),
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'parent_commit_id' => null,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'id' => $editStartVersion->id,
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }


    /**
     * @test
     */
    public function createCommit_should_detect_created_change_type_when_original_is_null(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'New Document',
            'description' => 'New Description',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $currentVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            collect([$editStartVersion]),
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'user_id' => $this->user->id,
            'parent_commit_id' => null,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $currentVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'id' => $editStartVersion->id,
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_detect_deleted_change_type_when_current_version_is_deleted(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $previousCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Deleted Document',
            'description' => 'Deleted Description',
            'is_deleted' => Flag::TRUE,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            collect([$editStartVersion]),
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'user_id' => $this->user->id,   
            'parent_commit_id' => $previousCommit->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::DELETED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'id' => $editStartVersion->id,
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_detect_title_changes(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Same Description',
        ]);

        $previousCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
            'description' => 'Same Description',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        $editStartVersions = collect([$editStartVersion]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            $editStartVersions,
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'parent_commit_id' => $previousCommit->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'is_title_changed' => true,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'id' => $editStartVersion->id,
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_detect_description_changes(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Same Title',
            'description' => 'Original Description',
        ]);

        $previousCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $originalVersion->id,
        ]);

        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $originalVersion->id,
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Same Title',
            'description' => 'Updated Description',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        $editStartVersions = collect([$editStartVersion]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            $editStartVersions,
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'parent_commit_id' => $previousCommit->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'is_title_changed' => false,
            'is_description_changed' => true,
            'first_original_version_id' => $originalVersion->id,
            'last_current_version_id' => $currentVersion->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'id' => $editStartVersion->id,
            'commit_id' => $result->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_handle_multiple_document_and_category_diffs(): void
    {
        // Arrange
        // UserBranchSessionを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        // Document 1
        $documentEntity1 = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $docOriginal1 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Doc 1 Original',
            'description' => 'Doc 1 Original Description',
        ]);
        $previousCommit1 = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);
        $docOriginal1EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit1->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity1->id,
            'original_version_id' => $docOriginal1->id,
            'current_version_id' => $docOriginal1->id,
        ]);
        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit1->id,
            'document_entity_id' => $documentEntity1->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $docOriginal1->id,
            'last_current_version_id' => $docOriginal1->id,
        ]);

        // Updated Document 1
        $docCurrent1 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Doc 1 Updated',
            'description' => 'Doc 1 Updated Description',
        ]);
        $docCurrent1EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity1->id,
            'original_version_id' => $docOriginal1->id,
            'current_version_id' => $docCurrent1->id,
        ]);

        // Original Document 2
        $documentEntity2 = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $docOriginal2 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity2->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Doc 2 Original',
            'description' => 'Doc 2 Original Description',
        ]);
        $docOriginal2EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit1->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity2->id,
            'original_version_id' => $docOriginal2->id,
            'current_version_id' => $docOriginal2->id,
        ]);
        CommitDocumentDiff::factory()->create([
            'commit_id' => $previousCommit1->id,
            'document_entity_id' => $documentEntity2->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $docOriginal2->id,
            'last_current_version_id' => $docOriginal2->id,
        ]);

        // Updated Document 2
        $docCurrent2 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity2->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Doc 2 Updated',
            'description' => 'Doc 2 Updated Description',
        ]);
        $docCurrent2EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity2->id,
            'original_version_id' => $docOriginal2->id,
            'current_version_id' => $docCurrent2->id,
        ]);

        // Original Category 1
        $categoryEntity1 = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $catOriginal1 = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Cat 1 Original',
            'description' => 'Cat 1 Original Description',
        ]);
        $catOriginal1EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $previousCommit1->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity1->id,
            'original_version_id' => $catOriginal1->id,
            'current_version_id' => $catOriginal1->id,
        ]);
        CommitCategoryDiff::factory()->create([
            'commit_id' => $previousCommit1->id,
            'category_entity_id' => $categoryEntity1->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $catOriginal1->id,
            'last_current_version_id' => $catOriginal1->id,
        ]);

        // Updated Category 1
        $catCurrent1 = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Cat 1 Updated',
            'description' => 'Cat 1 Updated Description',
        ]);
        $catCurrent1EditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity1->id,
            'original_version_id' => $catOriginal1->id,
            'current_version_id' => $catCurrent1->id,
        ]);

        $editStartVersions = collect([$docOriginal1EditStartVersion, $docCurrent1EditStartVersion, $docOriginal2EditStartVersion, $docCurrent2EditStartVersion, $catOriginal1EditStartVersion, $catCurrent1EditStartVersion]);

        // Act
        $result = $this->service->createCommit(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            $editStartVersions,
            'Test commit message'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(Commit::class, $result);
        
        $this->assertDatabaseHas('commits', [
            'id' => $result->id,
            'parent_commit_id' => $previousCommit1->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        // Check document diffs
        $documentDiffCount = CommitDocumentDiff::where('commit_id', $result->id)->count();
        $this->assertEquals(2, $documentDiffCount);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity1->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => true,
            'is_description_changed' => true,
            'first_original_version_id' => $docOriginal1->id,
            'last_current_version_id' => $docCurrent1->id,
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity2->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => true,
            'is_description_changed' => true,
            'first_original_version_id' => $docOriginal2->id,
            'last_current_version_id' => $docCurrent2->id,
        ]);

        // Check category diffs
        $categoryDiffCount = CommitCategoryDiff::where('commit_id', $result->id)->count();
        $this->assertEquals(1, $categoryDiffCount);

        $this->assertDatabaseHas('commit_category_diffs', [
            'commit_id' => $result->id,
            'category_entity_id' => $categoryEntity1->id,
            'change_type' => CommitChangeType::CREATED->value,
            'is_title_changed' => false,
            'is_description_changed' => false,
            'first_original_version_id' => $catOriginal1->id,
            'last_current_version_id' => $catCurrent1->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);

        // UserBranchSessionが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }
}

