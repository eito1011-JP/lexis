<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Consts\Flag;
use App\Dto\UseCase\DocumentCategory\DestroyCategoryEntityDto;
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
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use App\Services\DocumentService;
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

    private CategoryEntity $parentCategoryEntity;

    private CategoryVersion $existingParentCategory;

    private EditStartVersion $existingParentCategoryEditStartVersion;

    private $CategoryService;

    private $DocumentService;

    private $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスのモック作成
        $this->CategoryService = Mockery::mock(CategoryService::class);
        $this->DocumentService = Mockery::mock(DocumentService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        $this->useCase = new DestroyDocumentCategoryUseCase(
            $this->userBranchService,
            $this->CategoryService,
            $this->DocumentService
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
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // 親となるCategoryEntityの作成
        $this->parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 親となるCategoryVersionを作成
        $this->existingParentCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'title' => 'Existing Category',
            'description' => 'Existing description',
        ]);

        $this->existingParentCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $this->existingParentCategory->id,
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
        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($this->parentCategoryEntity->id, $this->user)
            ->andReturn($this->existingParentCategory);

        $this->DocumentService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->with($this->parentCategoryEntity->id, $this->user)
            ->andReturn(new Collection());

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
                ->with($this->parentCategoryEntity->id, $this->user)
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('document_versions', $result);
        $this->assertArrayHasKey('category_versions', $result);
        $this->assertCount(0, $result['document_versions']);
        $this->assertCount(1, $result['category_versions']); // カテゴリ自体が削除される


        // MERGEDステータスのカテゴリは削除されていないことを確認
        $this->assertDatabaseHas('category_versions', [
            'id' => $this->existingParentCategory->id,
            'deleted_at' => null,
        ]);

        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $deletedCategory->status);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $deletedCategory->id,
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
            'parent_entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $childCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $childCategory->id,
        ]);

        // ドキュメントを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $document = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $documentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingParentCategory);

        $this->DocumentService
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
        $this->assertEquals($document->entity_id, $deletedDocument->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedDocument->is_deleted);
        $this->assertNotNull($deletedDocument->deleted_at);

        // 子カテゴリが削除されていることを確認
        $deletedChildCategory = $result['category_versions'][0];
        $this->assertEquals($childCategory->entity_id, $deletedChildCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedChildCategory->is_deleted);
        $this->assertNotNull($deletedChildCategory->deleted_at);

        // カテゴリ自体が削除されていることを確認
        $deletedCategory = $result['category_versions'][1];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);

        // EditStartVersionsが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $deletedChildCategory->id,
        ]);

        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_execute_deletes_draft_status_category(): void
    {
        // Arrange
        $draftCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $draftCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($draftCategory);

        $this->DocumentService
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
        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['category_versions']);
        
        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);
        
        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);
    }

        /**
     * @test
     */
    public function test_execute_deletes_pushed_status_category(): void
    {
        // Arrange
        $pushedCategory = CategoryVersion::factory()->create([
            'entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        $pushedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $pushedCategory->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($pushedCategory);

        $this->DocumentService
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
        // 新しい削除バージョンが作成されていることを確認
        $this->assertCount(1, $result['category_versions']);
        
        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);
        
        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);
    }

    /**
     * @test
     */
    public function test_execute_deletes_pushed_status_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        $pushedDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingParentCategory);

        $this->DocumentService
            ->shouldReceive('getDescendantDocumentsByWorkContext')
            ->once()
            ->andReturn(new Collection([$pushedDocument]));

        $this->CategoryService
            ->shouldReceive('getDescendantCategoriesByWorkContext')
            ->once()
            ->andReturn(new Collection());

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        // DRAFTカテゴリが削除されていることを確認
        $this->assertCount(1, $result['category_versions']);
        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $deletedCategory->status);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);

        // 新規作成されたDRAFTドキュメントが削除されていることを確認
        $this->assertCount(1, $result['document_versions']);
        $deletedDocument = $result['document_versions'][0];
        $this->assertEquals($documentEntity->id, $deletedDocument->entity_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $deletedDocument->status);
        $this->assertEquals(Flag::TRUE, $deletedDocument->is_deleted);
        $this->assertNotNull($deletedDocument->deleted_at);
        
        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        // 既存のpushedドキュメントが論理削除されていることを確認
        $this->assertSoftDeleted('document_versions', [
            'id' => $pushedDocument->id,
        ]);
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
            'category_entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        $draftDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingParentCategory);

        $this->DocumentService
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
        // カテゴリが削除されていることを確認
        $this->assertCount(1, $result['category_versions']);
        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $deletedCategory->status);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);

        // ドキュメントが削除されていることを確認
        $this->assertCount(1, $result['document_versions']);
        
        $deletedDocument = $result['document_versions'][0];
        $this->assertEquals($documentEntity->id, $deletedDocument->entity_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $deletedDocument->status);
        $this->assertEquals(Flag::TRUE, $deletedDocument->is_deleted);
        $this->assertNotNull($deletedDocument->deleted_at);
        
        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        // 既存のdraftドキュメントが論理削除されていることを確認
        $this->assertSoftDeleted('document_versions', [
            'id' => $draftDocument->id,
        ]);
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
            'category_entity_id' => $this->parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        $mergedDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->andReturn($this->existingParentCategory);

        $this->DocumentService
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
        // カテゴリが削除されていることを確認
        $deletedCategory = $result['category_versions'][0];
        $this->assertEquals($this->parentCategoryEntity->id, $deletedCategory->entity_id);
        $this->assertEquals(DocumentCategoryStatus::DRAFT->value, $deletedCategory->status);
        $this->assertEquals(Flag::TRUE, $deletedCategory->is_deleted);
        $this->assertNotNull($deletedCategory->deleted_at);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->existingParentCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);

        // ドキュメントが削除されていることを確認
        $deletedDocument = $result['document_versions'][0];
        $this->assertEquals($mergedDocument->entity_id, $deletedDocument->entity_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $deletedDocument->status);
        $this->assertEquals(Flag::TRUE, $deletedDocument->is_deleted);
        $this->assertNotNull($deletedDocument->deleted_at);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);
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

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_execute_throws_not_found_exception_when_category_entity_belongs_to_different_organization(): void
    {
        // Arrange
        // 別の組織を作成
        $anotherOrganization = Organization::factory()->create();
        
        // 別の組織のカテゴリエンティティを作成
        $anotherCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $anotherOrganization->id,
        ]);

        $anotherCategory = CategoryVersion::factory()->create([
            'entity_id' => $anotherCategoryEntity->id,
            'organization_id' => $anotherOrganization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        $anotherCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $anotherCategory->id,
            'current_version_id' => $anotherCategory->id,
        ]);

        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $anotherCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        $this->CategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->once()
            ->with($anotherCategoryEntity->id, $this->user)
            ->andReturn(null); // 別組織のカテゴリなのでnull

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
        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $nonExistentEntityId,
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
        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
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
    public function test_execute_handles_exception_and_roll_back_transaction(): void
    {
        // Arrange
        $dto = new DestroyCategoryEntityDto(
            categoryEntityId: $this->parentCategoryEntity->id,
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
}

