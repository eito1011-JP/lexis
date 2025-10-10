<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\CommitChangeType;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestActivityAction;
use App\Models\ActivityLogOnPullRequest;
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
use App\Services\CommitService;
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

        $this->service = new CommitService();

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
            'change_type' => CommitChangeType::UPDATED->value,
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
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_update_category_version_status_from_draft_to_pushed(): void
    {
        // Arrange
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
    }

    /**
     * @test
     */
    public function createCommit_should_create_commit_without_parent_commit(): void
    {
        // Arrange
        $editStartVersions = collect();

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
            'parent_commit_id' => null,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_create_commit_with_parent_commit(): void
    {
        // Arrange
        $parentCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subHour(),
        ]);

        $editStartVersions = collect();

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
            'parent_commit_id' => $parentCommit->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_create_document_diffs_when_edit_start_versions_exist(): void
    {
        // Arrange
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

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
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
    }

    /**
     * @test
     */
    public function createCommit_should_create_category_diffs_when_edit_start_versions_exist(): void
    {
        // Arrange
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

        $currentVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity->id,
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
            'user_id' => $this->user->id,
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
            'id' => $editStartVersion->id,
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_created_change_type_when_original_is_null(): void
    {
        // Arrange
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_deleted_change_type_when_current_version_is_deleted(): void
    {
        // Arrange
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

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Deleted Document',
            'description' => 'Deleted Description',
            'is_deleted' => true,
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_deleted_change_type_when_entity_is_deleted(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
            'is_deleted' => true,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Current Title',
            'description' => 'Current Description',
            'is_deleted' => true,
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_updated_change_type_when_both_versions_exist(): void
    {
        // Arrange
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

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::UPDATED->value,
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_title_changes(): void
    {
        // Arrange
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'is_title_changed' => true,
            'is_description_changed' => false,
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
    }

    /**
     * @test
     */
    public function createCommit_should_detect_description_changes(): void
    {
        // Arrange
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'is_title_changed' => false,
            'is_description_changed' => true,
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
    }

    /**
     * @test
     */
    public function createCommit_should_handle_multiple_document_and_category_diffs(): void
    {
        // Arrange
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
        $docCurrent1 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Doc 1 Updated',
            'description' => 'Doc 1 Updated Description',
        ]);
        $editStartVersion1 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity1->id,
            'original_version_id' => $docOriginal1->id,
            'current_version_id' => $docCurrent1->id,
        ]);

        // Document 2
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
        $docCurrent2 = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity2->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Doc 2 Updated',
            'description' => 'Doc 2 Updated Description',
        ]);
        $editStartVersion2 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity2->id,
            'original_version_id' => $docOriginal2->id,
            'current_version_id' => $docCurrent2->id,
        ]);

        // Category 1
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
        $catCurrent1 = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity1->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Cat 1 Updated',
            'description' => 'Cat 1 Updated Description',
        ]);
        $editStartVersion3 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity1->id,
            'original_version_id' => $catOriginal1->id,
            'current_version_id' => $catCurrent1->id,
        ]);

        $editStartVersions = collect([$editStartVersion1, $editStartVersion2, $editStartVersion3]);

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
        ]);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity2->id,
        ]);

        // Check category diffs
        $categoryDiffCount = CommitCategoryDiff::where('commit_id', $result->id)->count();
        $this->assertEquals(1, $categoryDiffCount);

        $this->assertDatabaseHas('commit_category_diffs', [
            'commit_id' => $result->id,
            'category_entity_id' => $categoryEntity1->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommit_should_not_update_version_status_when_not_called_from_createCommitFromUserBranch(): void
    {
        // Arrange
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

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        $currentVersion->refresh();
        // createCommit is called directly, so version status should remain DRAFT
        $this->assertEquals(DocumentStatus::DRAFT->value, $currentVersion->status);

        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
            'change_type' => CommitChangeType::UPDATED->value,
            'is_title_changed' => true,
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
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_handle_multiple_edit_start_versions_with_different_target_types(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $docOriginal = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Document Original',
            'description' => 'Document Original Description',
        ]);
        $docCurrent = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Document Updated',
            'description' => 'Document Updated Description',
        ]);

        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $catOriginal = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Category Original',
            'description' => 'Category Original Description',
        ]);
        $catCurrent = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Category Updated',
            'description' => 'Category Updated Description',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $docOriginal->id,
            'current_version_id' => $docCurrent->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => null,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'entity_id' => $categoryEntity->id,
            'original_version_id' => $catOriginal->id,
            'current_version_id' => $catCurrent->id,
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
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'message' => 'Test commit message',
        ]);

        // Check that both document and category statuses are updated
        $docCurrent->refresh();
        $catCurrent->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $docCurrent->status);
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $catCurrent->status);

        // Check that both diffs are created
        $this->assertDatabaseHas('commit_document_diffs', [
            'commit_id' => $result->id,
            'document_entity_id' => $documentEntity->id,
        ]);
        
        $this->assertDatabaseHas('commit_category_diffs', [
            'commit_id' => $result->id,
            'category_entity_id' => $categoryEntity->id,
        ]);

        $this->assertDatabaseHas('activity_log_on_pull_requests', [
            'commit_id' => $result->id,
            'user_id' => $this->user->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'pull_request_id' => $this->pullRequest->id,
        ]);
    }

    /**
     * @test
     */
    public function createCommitFromUserBranch_should_ignore_edit_start_versions_with_existing_commit_id(): void
    {
        // Arrange
        $existingCommit = Commit::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $docOriginal = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);
        $docCurrent = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->userBranch->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

        // EditStartVersion with existing commit_id (should be ignored)
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'commit_id' => $existingCommit->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'entity_id' => $documentEntity->id,
            'original_version_id' => $docOriginal->id,
            'current_version_id' => $docCurrent->id,
        ]);

        // Act
        $result = $this->service->createCommitFromUserBranch(
            $this->user,
            $this->pullRequest,
            $this->userBranch,
            'Test commit message'
        );

        // Assert
        // Should return null because all EditStartVersions have commit_id
        $this->assertNull($result);
    }
}

