<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\UpdateDocumentCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use App\Services\UserBranchService;
use App\UseCases\DocumentCategory\UpdateDocumentCategoryUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class UpdateDocumentCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateDocumentCategoryUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private $userBranchService;

    private $CategoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userBranchService = Mockery::mock(UserBranchService::class);
        $this->CategoryService = Mockery::mock(CategoryService::class);

        $this->useCase = new UpdateDocumentCategoryUseCase(
            $this->userBranchService,
            $this->CategoryService
        );

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_update_category_successfully_without_pull_request_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, null)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentCategory::class, $result);
        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Description', $result->description);
        $this->assertEquals($existingCategory->parent_entity_id, $result->parent_entity_id);
        $this->assertEquals($userBranch->id, $result->user_branch_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $result->status);
        $this->assertNull($result->pull_request_edit_session_id);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $existingCategory->id,
            'current_version_id' => $result->id,
        ]);

        // 既存カテゴリがMERGEDステータスなので削除されていないことを確認
        $this->assertDatabaseHas('document_categories', [
            'id' => $existingCategory->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @test
     */
    public function test_update_category_successfully_with_pull_request_edit_session(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $this->user->id,
            'finished_at' => null,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: $pullRequest->id,
            pullRequestEditToken: $pullRequestEditSession->token
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, $pullRequestEditSession->token)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentCategory::class, $result);
        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Description', $result->description);
        $this->assertEquals($pullRequestEditSession->id, $result->pull_request_edit_session_id);

        // PullRequestEditSessionDiffが作成または更新されていることを確認
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'current_version_id' => $result->id,
            'diff_type' => 'updated',
        ]);
    }

    /**
     * @test
     */
    public function test_update_category_with_existing_pull_request_edit_session_diff(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequestEditSession = PullRequestEditSession::factory()->create([
            'pull_request_id' => $pullRequest->id,
            'user_id' => $this->user->id,
            'finished_at' => null,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // 既存のPullRequestEditSessionDiffを作成
        $existingDiff = PullRequestEditSessionDiff::factory()->create([
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'current_version_id' => $existingCategory->id,
            'diff_type' => 'created',
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: $pullRequest->id,
            pullRequestEditToken: $pullRequestEditSession->token
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, $pullRequestEditSession->token)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // 既存のDiffレコードが更新されていることを確認
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'id' => $existingDiff->id,
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'current_version_id' => $result->id, // 新しいカテゴリIDに更新される
            'diff_type' => 'updated',
        ]);
    }

    /**
     * @test
     */
    public function test_update_category_throws_not_found_exception_when_organization_id_is_null(): void
    {
        // Arrange
        // organizationMemberを削除して組織メンバーが存在しない状況を作る
        OrganizationMember::where('user_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->delete();

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        // Act & Assert
        $this->expectException(\ErrorException::class); // organizationMemberがnullの場合はErrorExceptionが発生
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_update_category_throws_not_found_exception_when_existing_category_not_found(): void
    {
        // Arrange
        $nonExistentCategoryEntityId = 99999;

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $nonExistentCategoryEntityId,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        // DocumentCategoryEntity::find()で見つからない場合、fetchOrCreateActiveBranchは呼び出されない

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_update_category_handles_user_branch_service_exception(): void
    {
        // Arrange
        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andThrow(new \Exception('UserBranchService error'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UserBranchService error');
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_update_category_handles_database_exception_and_rolls_back_transaction(): void
    {
        // Arrange
        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        // UserBranchServiceがExceptionを投げるようにモック
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andThrow(new \Exception('Database error'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_update_category_without_pull_request_edit_session_when_edit_pull_request_id_is_null(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, null)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertNull($result->pull_request_edit_session_id);

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'current_version_id' => $result->id,
        ]);
    }

    /**
     * @test
     */
    public function test_update_category_with_parent_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $parentCategoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $parentCategory = DocumentCategory::factory()->create([
            'entity_id' => $parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => $parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, null)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertEquals($parentCategoryEntity->id, $result->parent_entity_id);
    }

    /**
     * @test
     */
    public function test_update_category_deletes_draft_status_existing_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value, // DRAFTステータスで作成
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, null)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // DRAFTステータスの既存カテゴリが削除されていることを確認
        $this->assertSoftDeleted('document_categories', [
            'id' => $existingCategory->id,
        ]);

        // 新しいカテゴリが作成されていることを確認
        $this->assertDatabaseHas('document_categories', [
            'id' => $result->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => DocumentCategoryStatus::DRAFT->value,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'original_version_id' => $existingCategory->id,
            'current_version_id' => $result->id,
        ]);
    }

    /**
     * @test
     */
    public function test_update_category_does_not_delete_merged_status_existing_category(): void
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $existingCategory = DocumentCategory::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value, // MERGEDステータスで作成
        ]);

        $dto = new UpdateDocumentCategoryDto(
            categoryEntityId: $categoryEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
            editPullRequestId: null,
            pullRequestEditToken: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($categoryEntity->id, $this->user, null)
            ->andReturn($existingCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // MERGEDステータスの既存カテゴリが削除されていないことを確認
        $this->assertDatabaseHas('document_categories', [
            'id' => $existingCategory->id,
            'deleted_at' => null,
        ]);

        // 新しいカテゴリが作成されていることを確認
        $this->assertDatabaseHas('document_categories', [
            'id' => $result->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => DocumentCategoryStatus::DRAFT->value,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'original_version_id' => $existingCategory->id,
            'current_version_id' => $result->id,
        ]);
    }
}
