<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Consts\Flag;
use App\Dto\UseCase\DocumentCategory\DestroyDocumentCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use App\Services\UserBranchService;
use App\UseCases\DocumentCategory\DestroyDocumentCategoryUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class DestroyDocumentCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private DestroyDocumentCategoryUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $userBranch;

    private CategoryEntity $categoryEntity;

    private CategoryVersion $existingCategory;

    private $CategoryService;

    private $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスのモック作成
        $this->CategoryService = Mockery::mock(CategoryService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        $this->useCase = new DestroyDocumentCategoryUseCase(
            $this->userBranchService,
            $this->CategoryService
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

        // CategoryEntityの作成
        $this->categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 既存のCategoryVersionを作成
        $this->existingCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Existing Category',
            'description' => 'Existing description',
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
    public function test_execute_successfully_destroys_category_without_pull_request(): void
    {
        // Arrange
        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, null)
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, null)
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, null)
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, null)
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('document_versions', $result);
        $this->assertArrayHasKey('category_versions', $result);
        $this->assertCount(0, $result['document_versions']);
        $this->assertCount(1, $result['category_versions']); // カテゴリ自体が削除される

        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->categoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $deletedCategory->status);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_execute_successfully_destroys_category_with_pull_request_edit_session(): void
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

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: $pullRequest->id,
            pullRequestEditToken: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id, $pullRequest->id)
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->with($this->categoryEntity->id, $this->user, $pullRequestEditToken)
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result['category_versions']);

        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($pullRequestEditSession->id, $deletedCategory->pull_request_edit_session_id);

        // PullRequestEditSessionDiffが作成されていることを確認
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingCategory->id,
            'current_version_id' => $deletedCategory->id,
            'diff_type' => 'deleted',
        ]);
    }

    /**
     * @test
     */
    public function test_execute_destroys_descendant_documents_and_categories(): void
    {
        // Arrange
        // 子カテゴリを作成
        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryVersion::factory()->create([
            'entity_id' => $childCategoryEntity->id,
            'parent_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // ドキュメントを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $document = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection([$document]));

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection([$childCategory]));

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertCount(1, $result['document_versions']); // ドキュメントが削除される
        $this->assertCount(2, $result['category_versions']); // 子カテゴリ + カテゴリ自体

        // ドキュメントが削除されていることを確認
        $deletedDocument = $result['document_versions'][0];
        $this->assertEquals(Flag::TRUE, $deletedDocument->is_deleted);
        $this->assertNotNull($deletedDocument->deleted_at);

        // 子カテゴリが削除されていることを確認
        $deletedChildCategory = $result['category_versions'][0];
        $this->assertEquals(Flag::TRUE, $deletedChildCategory->is_deleted);
        $this->assertNotNull($deletedChildCategory->deleted_at);

        // EditStartVersionsが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_execute_deletes_draft_status_category(): void
    {
        // Arrange
        $draftCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($draftCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // DRAFTステータスのカテゴリが物理削除されていることを確認
        $this->assertSoftDeleted('category_versions', [
            'id' => $draftCategory->id,
        ]);

        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['category_versions']);
    }

    /**
     * @test
     */
    public function test_execute_does_not_delete_merged_status_category(): void
    {
        // Arrange
        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory); // MERGEDステータス

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // MERGEDステータスのカテゴリは物理削除されていないことを確認
        $this->assertDatabaseHas('category_versions', [
            'id' => $this->existingCategory->id,
            'deleted_at' => null,
        ]);

        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['category_versions']);
    }

    /**
     * @test
     */
    public function test_execute_deletes_draft_status_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection([$draftDocument]));

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // DRAFTステータスのドキュメントが物理削除されていることを確認
        $this->assertSoftDeleted('document_versions', [
            'id' => $draftDocument->id,
        ]);

        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['document_versions']);
    }

    /**
     * @test
     */
    public function test_execute_does_not_delete_merged_status_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection([$mergedDocument]));

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // MERGEDステータスのドキュメントは物理削除されていないことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $mergedDocument->id,
            'deleted_at' => null,
        ]);

        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['document_versions']);
    }

    /**
     * @test
     */
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        OrganizationMember::where('user_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->delete();

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_execute_throws_not_found_exception_when_organization_id_is_null(): void
    {
        // Arrange
        // organizationMemberのorganization_idをnullに設定
        $this->organizationMember->update(['organization_id' => null]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_execute_throws_not_found_exception_when_category_entity_not_found(): void
    {
        // Arrange
        $nonExistentEntityId = 999999;
        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $nonExistentEntityId,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_execute_throws_not_found_exception_when_existing_category_not_found(): void
    {
        // Arrange
        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn(null); // カテゴリが見つからない

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_execute_with_invalid_pull_request_edit_session(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: $pullRequest->id,
            pullRequestEditToken: 'invalid-token'
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $deletedCategory = $result['category_versions'][0];
        $this->assertNull($deletedCategory->pull_request_edit_session_id); // 無効なトークンなのでnull

        // PullRequestEditSessionDiffが作成されていないことを確認
        $this->assertDatabaseMissing('pull_request_edit_session_diffs', [
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'current_version_id' => $deletedCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_execute_handles_exception_and_rolls_back_transaction(): void
    {
        // Arrange
        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: null,
            pullRequestEditToken: null
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andThrow(new \Exception('UserBranchService error'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UserBranchService error');

        $initialCategoryCount = CategoryVersion::count();
        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto, $this->user);
        } catch (\Exception $e) {
            // ロールバックが実行されているかを確認
            $this->assertEquals($initialCategoryCount, CategoryVersion::count());
            $this->assertEquals($initialDocumentCount, DocumentVersion::count());
            $this->assertEquals($initialEditStartVersionCount, EditStartVersion::count());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function test_execute_creates_pull_request_edit_session_diff_for_all_items(): void
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
            'finished_at' => null,
        ]);

        // 子カテゴリとドキュメントを作成
        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryVersion::factory()->create([
            'entity_id' => $childCategoryEntity->id,
            'parent_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $document = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyDocumentCategoryDto(
            categoryEntityId: $this->categoryEntity->id,
            editPullRequestId: $pullRequest->id,
            pullRequestEditToken: $pullRequestEditToken
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingCategory);

        $this->CategoryService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection([$document]));

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection([$childCategory]));

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // ドキュメントのPullRequestEditSessionDiffが作成されていることを確認
        $deletedDocument = $result['document_versions'][0];
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $deletedDocument->id,
            'diff_type' => 'deleted',
        ]);

        // 子カテゴリのPullRequestEditSessionDiffが作成されていることを確認
        $deletedChildCategory = $result['category_versions'][0];
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $deletedChildCategory->id,
            'diff_type' => 'deleted',
        ]);

        // カテゴリ自体のPullRequestEditSessionDiffが作成されていることを確認
        $deletedCategory = $result['category_versions'][1];
        $this->assertDatabaseHas('pull_request_edit_session_diffs', [
            'pull_request_edit_session_id' => $pullRequestEditSession->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingCategory->id,
            'current_version_id' => $deletedCategory->id,
            'diff_type' => 'deleted',
        ]);
    }
}

