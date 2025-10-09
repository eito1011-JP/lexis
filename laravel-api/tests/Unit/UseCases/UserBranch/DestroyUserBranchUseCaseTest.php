<?php

namespace Tests\Unit\UseCases\UserBranch;

use App\Dto\UseCase\UserBranch\DestroyUserBranchDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use App\UseCases\UserBranch\DestroyUserBranchUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DestroyUserBranchUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private DestroyUserBranchUseCase $useCase;

    private User $user;

    private Organization $organization;

    private UserBranch $activeUserBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $userBranchService = new UserBranchService();
        $this->useCase = new DestroyUserBranchUseCase($userBranchService);

        // テストデータの準備
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // アクティブなユーザーブランチを作成
        $this->activeUserBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    #[Test]
    public function execute_successfully_deletes_active_user_branch(): void
    {
        // Arrange
        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        $this->assertDatabaseMissing('user_branches', [
            'id' => $this->activeUserBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_not_found(): void
    {
        // Arrange
        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: 99999
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_is_inactive(): void
    {
        // Arrange
        $inactiveUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $inactiveUserBranch->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 非アクティブなブランチは削除されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $inactiveUserBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_belongs_to_different_user(): void
    {
        // Arrange
        $anotherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        $anotherUserBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $anotherUserBranch->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 他のユーザーのブランチは削除されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $anotherUserBranch->id,
            'creator_id' => $anotherUser->id,
        ]);
    }

    #[Test]
    public function execute_deletes_only_specified_user_branch_when_multiple_active_branches_exist(): void
    {
        // Arrange
        $secondActiveUserBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // 指定したブランチが削除されていることを確認
        $this->assertDatabaseMissing('user_branches', [
            'id' => $this->activeUserBranch->id,
        ]);

        // 2つ目のブランチは削除されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $secondActiveUserBranch->id,
        ]);
    }

    #[Test]
    public function execute_verifies_all_conditions_are_checked(): void
    {
        // Arrange - すべての条件が満たされている正常なケース
        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // ユーザーブランチが物理削除されていることを確認
        $this->assertDatabaseMissing('user_branches', [
            'id' => $this->activeUserBranch->id,
        ]);

        // 削除カウントの確認
        $remainingBranches = UserBranch::where('creator_id', $this->user->id)->count();
        $this->assertEquals(0, $remainingBranches);
    }

    #[Test]
    public function execute_deletes_draft_and_pushed_document_versions(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $pushedVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        $mergedVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // draft と pushed のバージョンは物理削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $draftVersion->id,
        ]);
        $this->assertDatabaseMissing('document_versions', [
            'id' => $pushedVersion->id,
        ]);

        // merged のバージョンは残る
        $this->assertDatabaseHas('document_versions', [
            'id' => $mergedVersion->id,
        ]);
    }

    #[Test]
    public function execute_deletes_draft_and_pushed_category_versions(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $draftVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $pushedVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        $mergedVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // draft と pushed のバージョンは物理削除される
        $this->assertDatabaseMissing('category_versions', [
            'id' => $draftVersion->id,
        ]);
        $this->assertDatabaseMissing('category_versions', [
            'id' => $pushedVersion->id,
        ]);

        // merged のバージョンは残る
        $this->assertDatabaseHas('category_versions', [
            'id' => $mergedVersion->id,
        ]);
    }

    #[Test]
    public function execute_deletes_orphan_document_entities(): void
    {
        // Arrange - 孤児となるentityを作成
        $orphanEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // このentityには削除されるdraftのversionのみが紐付いている
        DocumentVersion::factory()->create([
            'entity_id' => $orphanEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        // 別のentityは他のversionも持っている（削除されない）
        $nonOrphanEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'entity_id' => $nonOrphanEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        DocumentVersion::factory()->create([
            'entity_id' => $nonOrphanEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // 孤児となったentityは削除される
        $this->assertDatabaseMissing('document_entities', [
            'id' => $orphanEntity->id,
        ]);

        // 他のversionを持つentityは残る
        $this->assertDatabaseHas('document_entities', [
            'id' => $nonOrphanEntity->id,
        ]);
    }

    #[Test]
    public function execute_deletes_orphan_category_entities(): void
    {
        // Arrange - 孤児となるentityを作成
        $orphanEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // このentityには削除されるdraftのversionのみが紐付いている
        CategoryVersion::factory()->create([
            'entity_id' => $orphanEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        // 別のentityは他のversionも持っている（削除されない）
        $nonOrphanEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        CategoryVersion::factory()->create([
            'entity_id' => $nonOrphanEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        CategoryVersion::factory()->create([
            'entity_id' => $nonOrphanEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // 孤児となったentityは削除される
        $this->assertDatabaseMissing('category_entities', [
            'id' => $orphanEntity->id,
        ]);

        // 他のversionを持つentityは残る
        $this->assertDatabaseHas('category_entities', [
            'id' => $nonOrphanEntity->id,
        ]);
    }

    #[Test]
    public function execute_cascades_delete_pull_requests(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // user_branch削除時にpull_requestsも自動的に削除される（CASCADE）
        $this->assertDatabaseMissing('pull_requests', [
            'id' => $pullRequest->id,
        ]);
    }

    #[Test]
    public function execute_cascades_delete_edit_start_versions(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $originalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $currentVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $editStartVersion = EditStartVersion::create([
            'user_branch_id' => $this->activeUserBranch->id,
            'entity_id' => $documentEntity->id,
            'target_type' => 'document',
            'original_version_id' => $originalVersion->id,
            'current_version_id' => $currentVersion->id,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // user_branch削除時にedit_start_versionsも自動的に削除される（CASCADE）
        $this->assertDatabaseMissing('edit_start_versions', [
            'id' => $editStartVersion->id,
        ]);
    }

    #[Test]
    public function execute_does_not_affect_other_organizations_data(): void
    {
        // Arrange - 他の組織のデータを作成
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
        ]);

        $otherUserBranch = UserBranch::factory()->withActiveSession()->create([
            'creator_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
        ]);

        $otherDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $otherDocumentVersion = DocumentVersion::factory()->create([
            'entity_id' => $otherDocumentEntity->id,
            'organization_id' => $otherOrganization->id,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $otherCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $otherCategoryVersion = CategoryVersion::factory()->create([
            'entity_id' => $otherCategoryEntity->id,
            'organization_id' => $otherOrganization->id,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert - 他の組織のデータは削除されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $otherUserBranch->id,
        ]);
        $this->assertDatabaseHas('document_entities', [
            'id' => $otherDocumentEntity->id,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'id' => $otherDocumentVersion->id,
        ]);
        $this->assertDatabaseHas('category_entities', [
            'id' => $otherCategoryEntity->id,
        ]);
        $this->assertDatabaseHas('category_versions', [
            'id' => $otherCategoryVersion->id,
        ]);
    }

    #[Test]
    public function execute_handles_soft_deleted_versions_correctly(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 論理削除されたバージョン（withTrashed()でのみ取得可能）
        $softDeletedVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $softDeletedVersion->delete();

        // 通常のバージョン
        $normalVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        // MERGEDバージョン（残るべき）
        $mergedVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // 論理削除されていたバージョンは物理削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $softDeletedVersion->id,
        ]);

        // 通常のDRAFTバージョンも物理削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $normalVersion->id,
        ]);

        // MERGEDバージョンは残る
        $this->assertDatabaseHas('document_versions', [
            'id' => $mergedVersion->id,
        ]);

        // entityはMERGEDバージョンがあるので残る
        $this->assertDatabaseHas('document_entities', [
            'id' => $documentEntity->id,
        ]);
    }

    #[Test]
    public function execute_deletes_entity_with_only_soft_deleted_versions(): void
    {
        // Arrange - 論理削除されたバージョンのみを持つentityを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $softDeletedVersion = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $softDeletedVersion->delete(); // 論理削除

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // versionが物理削除されたため、entityも削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $softDeletedVersion->id,
        ]);
        $this->assertDatabaseMissing('document_entities', [
            'id' => $documentEntity->id,
        ]);
    }

    #[Test]
    public function execute_complex_scenario_with_multiple_versions_and_entities(): void
    {
        // Arrange - 複雑なシナリオ：複数のentityとversionが混在
        
        // Entity 1: DRAFTのみ（削除される）
        $entity1 = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $entity1DraftVersion = DocumentVersion::factory()->create([
            'entity_id' => $entity1->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        // Entity 2: PUSHEDのみ（削除される）
        $entity2 = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $entity2PushedVersion = DocumentVersion::factory()->create([
            'entity_id' => $entity2->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        // Entity 3: DRAFTとMERGEDの混在（entityは残る）
        $entity3 = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $entity3DraftVersion = DocumentVersion::factory()->create([
            'entity_id' => $entity3->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $entity3MergedVersion = DocumentVersion::factory()->create([
            'entity_id' => $entity3->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // Category Entity 1: DRAFTのみ（削除される）
        $categoryEntity1 = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $categoryEntity1DraftVersion = CategoryVersion::factory()->create([
            'entity_id' => $categoryEntity1->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $dto = new DestroyUserBranchDto(
            user: $this->user,
            userBranchId: $this->activeUserBranch->id
        );

        // Act
        $this->useCase->execute($dto);

        // Assert
        // Entity 1: DRAFTバージョンとentityが削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $entity1DraftVersion->id,
        ]);
        $this->assertDatabaseMissing('document_entities', [
            'id' => $entity1->id,
        ]);

        // Entity 2: PUSHEDバージョンとentityが削除される
        $this->assertDatabaseMissing('document_versions', [
            'id' => $entity2PushedVersion->id,
        ]);
        $this->assertDatabaseMissing('document_entities', [
            'id' => $entity2->id,
        ]);

        // Entity 3: DRAFTバージョンは削除されるが、MERGEDバージョンとentityは残る
        $this->assertDatabaseMissing('document_versions', [
            'id' => $entity3DraftVersion->id,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'id' => $entity3MergedVersion->id,
        ]);
        $this->assertDatabaseHas('document_entities', [
            'id' => $entity3->id,
        ]);

        // Category Entity 1: DRAFTバージョンとentityが削除される
        $this->assertDatabaseMissing('category_versions', [
            'id' => $categoryEntity1DraftVersion->id,
        ]);
        $this->assertDatabaseMissing('category_entities', [
            'id' => $categoryEntity1->id,
        ]);

        // User Branchが削除される
        $this->assertDatabaseMissing('user_branches', [
            'id' => $this->activeUserBranch->id,
        ]);
    }
}

