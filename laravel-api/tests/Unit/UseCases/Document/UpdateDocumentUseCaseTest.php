<?php

namespace Tests\Unit\UseCases\Document;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\UseCases\Document\UpdateDocumentUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class UpdateDocumentUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateDocumentUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $userBranch;

    private CategoryVersion $category;

    private CategoryEntity $categoryEntity;

    private DocumentVersion $existingDocument;

    private DocumentEntity $documentEntity;

    private EditStartVersion $existingDocumentCategoryEditStartVersion;

    private EditStartVersion $existingDocumentEditStartVersion;

    private $userBranchService;

    private $documentService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスのモック作成
        $this->userBranchService = Mockery::mock(UserBranchService::class);
        $this->documentService = Mockery::mock(DocumentService::class);

        $this->useCase = new UpdateDocumentUseCase(
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

        // DocumentCategoryEntityの作成
        $this->categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryの作成
        $this->category = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        // DocumentEntityの作成
        $this->documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryのediStartVersionを作成
        $this->existingDocumentCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->category->id,
            'current_version_id' => $this->category->id,
        ]);

        // 既存のDocumentVersionを作成
        $this->existingDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'category_entity_id' => $this->categoryEntity->id,
            'status' => DocumentStatus::DRAFT->value,
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        // DocumentVersionのediStartVersionを作成
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
    public function test_execute_successfully_updates_draft_document_without_pull_request(): void
    {
        // Arrange
        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Description', $result->description);
        $this->assertEquals($this->categoryEntity->id, $result->category_entity_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
        ]);

        // 元のDRAFTドキュメントが削除されていることを確認（soft deleteされている）
        $this->assertSoftDeleted('document_versions', [
            'id' => $this->existingDocument->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_updates_merged_document_without_pull_request(): void
    {
        // Arrange
        // 既存ドキュメントをMERGEDに変更
        $this->existingDocument->update(['status' => DocumentStatus::MERGED->value]);

        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Description', $result->description);
        $this->assertEquals($this->categoryEntity->id, $result->category_entity_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->existingDocument->id,
            'current_version_id' => $result->id,
        ]);

        // 元のMERGEDドキュメントは削除されていないことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $this->existingDocument->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        // 組織メンバーシップを持たないユーザーを作成
        $userWithoutOrganization = User::factory()->create();

        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
        );

        // Act & Assert
        // organizationMemberがnullの場合はErrorExceptionが発生する
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Attempt to read property "organization_id" on null');
        $this->useCase->execute($dto, $userWithoutOrganization);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_existing_document_not_found(): void
    {
        // Arrange
        $dto = new UpdateDocumentDto(
            document_entity_id: 999999, // 存在しないドキュメントエンティティID
            title: 'Updated Title',
            description: 'Updated Description',
        );

        // DocumentVersionEntityが見つからない場合は、UserBranchServiceは呼び出されない
        // （UseCaseの実装で、DocumentVersionEntity::findの後にNotFoundException が発生するため）

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_rollback_on_exception_from_user_branch_service(): void
    {
        // Arrange
        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
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
    public function test_execute_rollback_on_exception_during_document_creation(): void
    {
        // Arrange
        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
        );

        // 無効なユーザーブランチIDを返すことでDocumentVersion作成時にエラーを発生させる
        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn(999999); // 存在しないユーザーブランチID

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->existingDocument);

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

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
        $dto = new UpdateDocumentDto(
            document_entity_id: $this->documentEntity->id,
            title: 'Updated Title',
            description: 'Updated Description',
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        $this->documentService
            ->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->existingDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert - サービスが正しいパラメータで呼び出されたことを確認
        $this->assertInstanceOf(DocumentVersion::class, $result);

        // DocumentVersionが正しいデータで作成されたことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $result->id,
            'entity_id' => $this->documentEntity->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'category_entity_id' => $this->categoryEntity->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => DocumentStatus::DRAFT->value,
        ]);
    }
}
