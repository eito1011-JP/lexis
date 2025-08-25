<?php

namespace Tests\Unit\UseCases;

use App\Dto\UseCase\Document\GetDocumentDetailDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Services\DocumentCategoryService;
use App\UseCases\Document\GetDocumentDetailUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetDocumentDetailUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private GetDocumentDetailUseCase $useCase;

    /** @var \Mockery\MockInterface&DocumentCategoryService */
    private DocumentCategoryService $documentCategoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);

        $this->useCase = new GetDocumentDetailUseCase(
            $this->documentCategoryService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function execute_with_empty_category_path_returns_document_successfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'test-document'
        );

        $category = DocumentCategory::factory()->create();
        $document = DocumentVersion::factory()->create([
            'category_id' => $category->id,
            'slug' => 'test-document',
            'sidebar_label' => 'Test Document',
            'content' => 'Test content',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn($category->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('document', $result);
        $this->assertInstanceOf(DocumentVersion::class, $result['document']);
        $this->assertSame('test-document', $result['document']->slug);
        $this->assertSame('Test Document', $result['document']->sidebar_label);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function execute_with_category_path_returns_document_successfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'parent/child',
            slug: 'test-document'
        );

        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
        ]);

        $child = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $parent->id,
        ]);

        $document = DocumentVersion::factory()->create([
            'category_id' => $child->id,
            'slug' => 'test-document',
            'sidebar_label' => 'Test Document in Category',
            'content' => 'Test content in category',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($child->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('document', $result);
        $this->assertInstanceOf(DocumentVersion::class, $result['document']);
        $this->assertSame('test-document', $result['document']->slug);
        $this->assertSame($child->id, $result['document']->category_id);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function execute_with_null_category_path_returns_document_successfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: null,
            slug: 'test-document'
        );

        $category = DocumentCategory::factory()->create();
        $document = DocumentVersion::factory()->create([
            'category_id' => $category->id,
            'slug' => 'test-document',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn($category->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('document', $result);
        $this->assertInstanceOf(DocumentVersion::class, $result['document']);
        $this->assertSame('test-document', $result['document']->slug);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function execute_returns_error_when_document_not_found(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'non-existent-document'
        );

        $category = DocumentCategory::factory()->create();

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn($category->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('ドキュメントが見つかりません', $result['error']);
        $this->assertArrayNotHasKey('document', $result);
    }

    #[Test]
    public function execute_returns_error_when_document_not_found_in_specified_category(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'parent/child',
            slug: 'test-document'
        );

        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
        ]);

        $child = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $parent->id,
        ]);

        $otherCategory = DocumentCategory::factory()->create();

        // 別のカテゴリにドキュメントを作成（指定されたカテゴリではない）
        DocumentVersion::factory()->create([
            'category_id' => $otherCategory->id,
            'slug' => 'test-document',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($child->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('ドキュメントが見つかりません', $result['error']);
        $this->assertArrayNotHasKey('document', $result);
    }

    #[Test]
    public function execute_returns_correct_document_when_multiple_documents_with_same_slug_exist(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'target-category',
            slug: 'duplicate-slug'
        );

        $targetCategory = DocumentCategory::factory()->create();
        $otherCategory = DocumentCategory::factory()->create();

        // 異なるカテゴリに同じスラッグのドキュメントを作成
        DocumentVersion::factory()->create([
            'category_id' => $otherCategory->id,
            'slug' => 'duplicate-slug',
            'sidebar_label' => 'Other Document',
        ]);

        $targetDocument = DocumentVersion::factory()->create([
            'category_id' => $targetCategory->id,
            'slug' => 'duplicate-slug',
            'sidebar_label' => 'Target Document',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('target-category')
            ->andReturn($targetCategory->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('document', $result);
        $this->assertSame($targetDocument->id, $result['document']->id);
        $this->assertSame('Target Document', $result['document']->sidebar_label);
        $this->assertSame($targetCategory->id, $result['document']->category_id);
    }

    #[Test]
    public function execute_handles_exception_and_returns_error(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'some/path',
            slug: 'test-document'
        );

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('some/path')
            ->andThrow(new \Exception('Service error'));

        // Logファサードをモック
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GetDocumentDetailUseCase: エラー',
                Mockery::subset([
                    'error' => 'Service error',
                    'category_path' => 'some/path',
                    'slug' => 'test-document',
                ])
            );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('ドキュメントの取得に失敗しました', $result['error']);
        $this->assertArrayNotHasKey('document', $result);
    }

    #[Test]
    public function execute_handles_service_exception_and_returns_error(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'test-document'
        );

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andThrow(new \Exception('Service error'));

        // Logファサードをモック
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GetDocumentDetailUseCase: エラー',
                Mockery::subset([
                    'error' => 'Service error',
                    'category_path' => '',
                    'slug' => 'test-document',
                ])
            );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertSame('ドキュメントの取得に失敗しました', $result['error']);
    }

    #[Test]
    public function execute_logs_error_with_null_category_path(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: null,
            slug: 'test-document'
        );

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andThrow(new \Exception('Service error'));

        // Logファサードをモック（category_pathがnullの場合のログ）
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GetDocumentDetailUseCase: エラー',
                Mockery::subset([
                    'error' => 'Service error',
                    'category_path' => null,
                    'slug' => 'test-document',
                ])
            );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertSame('ドキュメントの取得に失敗しました', $result['error']);
    }

    #[Test]
    public function execute_with_special_characters_in_slug(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'document-with-special-chars_123'
        );

        $category = DocumentCategory::factory()->create();
        $document = DocumentVersion::factory()->create([
            'category_id' => $category->id,
            'slug' => 'document-with-special-chars_123',
            'sidebar_label' => 'Document with Special Characters',
        ]);

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn($category->id);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('document', $result);
        $this->assertSame('document-with-special-chars_123', $result['document']->slug);
        $this->assertSame('Document with Special Characters', $result['document']->sidebar_label);
    }
}
