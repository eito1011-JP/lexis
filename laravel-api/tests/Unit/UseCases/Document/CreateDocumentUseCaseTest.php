<?php

namespace Tests\Unit\UseCases\Document;

use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
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
use App\UseCases\Document\CreateDocumentUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CreateDocumentUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private CreateDocumentUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $userBranch;

    private CategoryVersion $category;

    private $documentService;

    private $userBranchService;

    private $CategoryService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスのモック作成
        $this->documentService = Mockery::mock(DocumentService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);
        $this->CategoryService = Mockery::mock(CategoryService::class);

        $this->useCase = new CreateDocumentUseCase(
            $this->documentService,
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
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryの作成
        $this->category = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_creates_document_without_pull_request(): void
    {
        // Arrange
        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals('Test Document', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($this->category->entity_id, $result->category_entity_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $result->id,
            'current_version_id' => $result->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_pull_request_id_only_without_token(): void
    {
        // Arrange
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals('Test Document', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($this->category->entity_id, $result->category_entity_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_token_only_without_pull_request_id(): void
    {
        // Arrange
        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertEquals('Test Document', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($this->category->entity_id, $result->category_entity_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        // OrganizationMemberを削除して組織が見つからない状況を作る
        OrganizationMember::where('user_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->delete();

        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_rollback_on_exception_from_user_branch_service(): void
    {
        // Arrange
        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
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

        DB::beginTransaction();
        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto);
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
        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: 999999, // 存在しないカテゴリID
            user: $this->user
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // Act & Assert
        $this->expectException(\Exception::class);

        DB::beginTransaction();
        $initialDocumentCount = DocumentVersion::count();
        $initialEditStartVersionCount = EditStartVersion::count();

        try {
            $this->useCase->execute($dto);
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
        $dto = new CreateDocumentUseCaseDto(
            title: 'Test Document',
            description: 'Test description',
            categoryEntityId: $this->category->entity_id,
            user: $this->user
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($this->userBranch->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert - すべてのサービスが正しいパラメータで呼び出されたことを確認
        $this->assertInstanceOf(DocumentVersion::class, $result);

        // DocumentVersionが正しいデータで作成されたことを確認
        $this->assertDatabaseHas('document_versions', [
            'id' => $result->id,
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'category_entity_id' => $this->category->entity_id,
            'title' => 'Test Document',
            'description' => 'Test description',
            'status' => DocumentStatus::DRAFT->value,
        ]);
    }
}
