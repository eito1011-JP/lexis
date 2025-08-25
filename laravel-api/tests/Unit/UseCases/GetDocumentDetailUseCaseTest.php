<?php

namespace Tests\Unit\UseCases;

use App\Dto\UseCase\Document\GetDocumentDetailDto;
use App\Exceptions\DocumentNotFoundException;
use App\Models\DocumentVersion;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
use App\UseCases\Document\GetDocumentDetailUseCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetDocumentDetailUseCaseTest extends TestCase
{
    private GetDocumentDetailUseCase $useCase;

    /** @var \Mockery\MockInterface&DocumentCategoryService */
    private DocumentCategoryService $documentCategoryService;

    /** @var \Mockery\MockInterface&DocumentVersionRepositoryInterface */
    private DocumentVersionRepositoryInterface $documentVersionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);
        $this->documentVersionRepository = Mockery::mock(DocumentVersionRepositoryInterface::class);

        $this->useCase = new GetDocumentDetailUseCase(
            $this->documentCategoryService,
            $this->documentVersionRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function executeWithEmptyCategoryPathReturnsDocumentSuccessfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'test-document'
        );

        $document = new DocumentVersion([
            'slug' => 'test-document',
            'sidebar_label' => 'Test Document',
            'content' => 'Test content',
            'category_id' => 1,
        ]);
        $document->id = 1;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with(1, 'test-document')
            ->andReturn($document);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertSame('test-document', $result->slug);
        $this->assertSame('Test Document', $result->sidebar_label);
        $this->assertSame(1, $result->category_id);
    }

    #[Test]
    public function executeWithCategoryPathReturnsDocumentSuccessfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'parent/child',
            slug: 'test-document'
        );

        $categoryId = 2;
        $document = new DocumentVersion([
            'category_id' => $categoryId,
            'slug' => 'test-document',
            'sidebar_label' => 'Test Document in Category',
            'content' => 'Test content in category',
        ]);
        $document->id = 1;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($categoryId);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with($categoryId, 'test-document')
            ->andReturn($document);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertSame('test-document', $result->slug);
        $this->assertSame($categoryId, $result->category_id);
        $this->assertSame('Test Document in Category', $result->sidebar_label);
    }

    #[Test]
    public function executeWithNullCategoryPathReturnsDocumentSuccessfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: null,
            slug: 'test-document'
        );

        $categoryId = 1;
        $document = new DocumentVersion([
            'category_id' => $categoryId,
            'slug' => 'test-document',
            'sidebar_label' => 'Test Document',
        ]);
        $document->id = 1;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with(null)
            ->andReturn($categoryId);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with($categoryId, 'test-document')
            ->andReturn($document);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertSame('test-document', $result->slug);
        $this->assertSame($categoryId, $result->category_id);
    }

    #[Test]
    public function executeThrowsDocumentNotFoundExceptionWhenDocumentNotFound(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'non-existent-document'
        );

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with(1, 'non-existent-document')
            ->andReturn(null);

        // Act & Assert
        $this->expectException(DocumentNotFoundException::class);
        $this->expectExceptionMessage('ドキュメントが見つかりません');

        $this->useCase->execute($dto);
    }

    #[Test]
    public function executeThrowsDocumentNotFoundExceptionWhenDocumentNotFoundInSpecifiedCategory(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'parent/child',
            slug: 'test-document'
        );

        $categoryId = 2;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('parent/child')
            ->andReturn($categoryId);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with($categoryId, 'test-document')
            ->andReturn(null);

        // Act & Assert
        $this->expectException(DocumentNotFoundException::class);
        $this->expectExceptionMessage('ドキュメントが見つかりません');

        $this->useCase->execute($dto);
    }

    #[Test]
    public function executeReturnsCorrectDocumentWhenMultipleDocumentsWithSameSlugExist(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'target-category',
            slug: 'duplicate-slug'
        );

        $targetCategoryId = 1;
        $targetDocument = new DocumentVersion([
            'category_id' => $targetCategoryId,
            'slug' => 'duplicate-slug',
            'sidebar_label' => 'Target Document',
        ]);
        $targetDocument->id = 2;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('target-category')
            ->andReturn($targetCategoryId);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with($targetCategoryId, 'duplicate-slug')
            ->andReturn($targetDocument);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertSame(2, $result->id);
        $this->assertSame('Target Document', $result->sidebar_label);
        $this->assertSame($targetCategoryId, $result->category_id);
        $this->assertSame('duplicate-slug', $result->slug);
    }

    #[Test]
    public function executeLogsErrorAndThrowsExceptionWhenServiceThrowsException(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: 'some/path',
            slug: 'test-document'
        );

        $serviceException = new \Exception('Service error');

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('some/path')
            ->andThrow($serviceException);

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

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service error');

        $this->useCase->execute($dto);
    }

    #[Test]
    public function executeLogsErrorAndThrowsExceptionWhenRepositoryThrowsException(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'test-document'
        );

        $repositoryException = new \Exception('Repository error');

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn(1);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with(1, 'test-document')
            ->andThrow($repositoryException);

        // Logファサードをモック
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GetDocumentDetailUseCase: エラー',
                Mockery::subset([
                    'error' => 'Repository error',
                    'category_path' => '',
                    'slug' => 'test-document',
                ])
            );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Repository error');

        $this->useCase->execute($dto);
    }

    #[Test]
    public function executeWithSpecialCharactersInSlugReturnsDocumentSuccessfully(): void
    {
        // Arrange
        $dto = new GetDocumentDetailDto(
            category_path: '',
            slug: 'document-with-special-chars_123'
        );

        $categoryId = 1;
        $document = new DocumentVersion([
            'category_id' => $categoryId,
            'slug' => 'document-with-special-chars_123',
            'sidebar_label' => 'Document with Special Characters',
        ]);
        $document->id = 1;

        $this->documentCategoryService
            ->shouldReceive('getIdFromPath')
            ->once()
            ->with('')
            ->andReturn($categoryId);

        $this->documentVersionRepository
            ->shouldReceive('findByCategoryAndSlug')
            ->once()
            ->with($categoryId, 'document-with-special-chars_123')
            ->andReturn($document);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(DocumentVersion::class, $result);
        $this->assertSame('document-with-special-chars_123', $result->slug);
        $this->assertSame('Document with Special Characters', $result->sidebar_label);
    }
}
