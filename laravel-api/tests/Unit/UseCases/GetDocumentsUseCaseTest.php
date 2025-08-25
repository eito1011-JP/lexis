<?php

namespace Tests\Unit\UseCases;

use App\Dto\UseCase\Document\GetDocumentsDto;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\UseCases\Document\GetDocumentsUseCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class GetDocumentsUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private GetDocumentsUseCase $useCase;

    /** @var \Mockery\MockInterface&DocumentService */
    private DocumentService $documentService;

    /** @var \Mockery\MockInterface&DocumentCategoryService */
    private DocumentCategoryService $documentCategoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentService = Mockery::mock(DocumentService::class);
        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);

        $this->useCase = new GetDocumentsUseCase(
            $this->documentService,
            $this->documentCategoryService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * テスト用のユーザーとアクティブブランチを作成
     */
    private function createUserWithActiveBranch(): array
    {
        $user = User::factory()->create();
        $branch = UserBranch::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return [$user, $branch];
    }

    /**
     * テスト用のドキュメントオブジェクトを作成
     */
    private function createMockDocument(array $attributes = []): stdClass
    {
        $document = new stdClass();
        $document->sidebar_label = $attributes['sidebar_label'] ?? 'Test Document';
        $document->slug = $attributes['slug'] ?? 'test-document';
        $document->is_public = $attributes['is_public'] ?? true;
        $document->status = $attributes['status'] ?? 'merged';
        $document->last_edited_by = $attributes['last_edited_by'] ?? 'test@example.com';
        $document->file_order = $attributes['file_order'] ?? 1;

        return $document;
    }

    /**
     * テスト用のカテゴリオブジェクトを作成
     */
    private function createMockCategory(array $attributes = []): stdClass
    {
        $category = new stdClass();
        $category->slug = $attributes['slug'] ?? 'test-category';
        $category->sidebar_label = $attributes['sidebar_label'] ?? 'Test Category';
        $category->position = $attributes['position'] ?? 1;

        return $category;
    }

    #[Test]
    public function execute_with_empty_category_path_and_no_pull_request(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([
            $this->createMockDocument(['file_order' => 2, 'slug' => 'doc2']),
            $this->createMockDocument(['file_order' => 1, 'slug' => 'doc1']),
        ]);

        $mockCategories = collect([
            $this->createMockCategory(['position' => 2, 'slug' => 'cat2']),
            $this->createMockCategory(['position' => 1, 'slug' => 'cat1']),
        ]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('categories', $result);

        // ドキュメントがfile_orderでソートされていることを確認
        $documents = $result['documents'];
        $this->assertCount(2, $documents);
        $this->assertSame('doc1', $documents[0]['slug']);
        $this->assertSame('doc2', $documents[1]['slug']);

        // カテゴリがpositionでソートされていることを確認
        $categories = $result['categories'];
        $this->assertCount(2, $categories);
        $this->assertSame('cat1', $categories[0]['slug']);
        $this->assertSame('cat2', $categories[1]['slug']);
    }

    #[Test]
    public function execute_with_category_path(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: 'parent/child'
        );

        $mockDocuments = collect([
            $this->createMockDocument(['file_order' => 1]),
        ]);

        $mockCategories = collect([
            $this->createMockCategory(['position' => 1]),
        ]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn(5);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(5, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(5, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['documents']);
        $this->assertCount(1, $result['categories']);
    }

    #[Test]
    public function execute_with_edit_pull_request_id(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();
        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $branch->id,
        ]);

        $dto = new GetDocumentsDto(
            category_path: '',
            edit_pull_request_id: $pullRequest->id
        );

        $mockDocuments = collect([
            $this->createMockDocument(),
        ]);

        $mockCategories = collect([
            $this->createMockCategory(),
        ]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, $pullRequest->id)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, $pullRequest->id)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['documents']);
        $this->assertCount(1, $result['categories']);
    }

    #[Test]
    public function execute_with_no_active_user_branch(): void
    {
        // Arrange
        $user = User::factory()->create();
        // アクティブなブランチを作成しない

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([
            $this->createMockDocument(),
        ]);

        $mockCategories = collect([
            $this->createMockCategory(),
        ]);

        // Mock設定（userBranchId = null）
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, null, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, null, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['documents']);
        $this->assertCount(1, $result['categories']);
    }

    #[Test]
    public function execute_filters_documents_without_file_order(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        // file_orderがnullのドキュメントを含む
        $mockDocuments = collect([
            $this->createMockDocument(['file_order' => 1, 'slug' => 'doc1']),
            $this->createMockDocument(['file_order' => null, 'slug' => 'doc-no-order']),
            $this->createMockDocument(['file_order' => 2, 'slug' => 'doc2']),
        ]);

        $mockCategories = collect([]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        
        // file_orderがnullのドキュメントは除外される
        $documents = $result['documents'];
        $this->assertCount(2, $documents);
        $this->assertSame('doc1', $documents[0]['slug']);
        $this->assertSame('doc2', $documents[1]['slug']);
    }

    #[Test]
    public function execute_filters_categories_without_position(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([]);

        // positionがnullのカテゴリを含む
        $mockCategories = collect([
            $this->createMockCategory(['position' => 1, 'slug' => 'cat1']),
            $this->createMockCategory(['position' => null, 'slug' => 'cat-no-position']),
            $this->createMockCategory(['position' => 2, 'slug' => 'cat2']),
        ]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        
        // positionがnullのカテゴリは除外される
        $categories = $result['categories'];
        $this->assertCount(2, $categories);
        $this->assertSame('cat1', $categories[0]['slug']);
        $this->assertSame('cat2', $categories[1]['slug']);
    }

    #[Test]
    public function execute_returns_correct_document_structure(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([
            $this->createMockDocument([
                'sidebar_label' => 'Test Document',
                'slug' => 'test-document',
                'is_public' => true,
                'status' => 'merged',
                'last_edited_by' => 'test@example.com',
                'file_order' => 1,
            ]),
        ]);

        $mockCategories = collect([]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        
        $document = $result['documents'][0];
        $this->assertSame('Test Document', $document['sidebar_label']);
        $this->assertSame('test-document', $document['slug']);
        $this->assertTrue($document['is_public']);
        $this->assertSame('merged', $document['status']);
        $this->assertSame('test@example.com', $document['last_edited_by']);
        $this->assertSame(1, $document['file_order']);
    }

    #[Test]
    public function execute_returns_correct_category_structure(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([]);

        $mockCategories = collect([
            $this->createMockCategory([
                'slug' => 'test-category',
                'sidebar_label' => 'Test Category',
                'position' => 1,
            ]),
        ]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        
        $category = $result['categories'][0];
        $this->assertSame('test-category', $category['slug']);
        $this->assertSame('Test Category', $category['sidebar_label']);
    }

    #[Test]
    public function execute_logs_user_branch_id(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([]);
        $mockCategories = collect([]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Logファサードをモック
        Log::shouldReceive('info')
            ->once()
            ->with("userBranchId: {$branch->id}");

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function execute_handles_exception_and_returns_error(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        // Mock設定（例外をスロー）
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andThrow(new \Exception('Service error'));

        // Logファサードをモック
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'));

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertSame('ドキュメント一覧の取得に失敗しました', $result['error']);
        $this->assertArrayNotHasKey('documents', $result);
        $this->assertArrayNotHasKey('categories', $result);
    }

    #[Test]
    public function execute_handles_invalid_category_path_with_slashes(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: '//invalid//path//'
        );

        $mockDocuments = collect([]);
        $mockCategories = collect([]);

        // Mock設定（array_filterにより空文字や空要素が除去される）
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('invalid/path')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function execute_handles_pull_request_not_found(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: '',
            edit_pull_request_id: 99999 // 存在しないID
        );

        $mockDocuments = collect([]);
        $mockCategories = collect([]);

        // Mock設定（PullRequest::findはnullを返すため、userBranchIdはnullになる）
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, null, 99999)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, null, 99999)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function execute_with_empty_results(): void
    {
        // Arrange
        [$user, $branch] = $this->createUserWithActiveBranch();

        $dto = new GetDocumentsDto(
            category_path: ''
        );

        $mockDocuments = collect([]);
        $mockCategories = collect([]);

        // Mock設定
        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentCategoryService
            ->shouldReceive('getSubCategories')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockCategories);

        $this->documentService
            ->shouldReceive('fetchDocumentsByCategoryId')
            ->once()
            ->with(1, $branch->id, null)
            ->andReturn($mockDocuments);

        // Act
        $result = $this->useCase->execute($dto, $user);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(0, $result['documents']);
        $this->assertCount(0, $result['categories']);
    }
}
