<?php

namespace Tests\Unit\Services;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Models\DocumentVersion;
use App\Services\DocumentDiffService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentDiffServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentDiffService $documentDiffService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentDiffService = new DocumentDiffService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUpdateDocumentDto(array $data): UpdateDocumentDto
    {
        return new UpdateDocumentDto(
            category_path: $data['category_path'] ?? null,
            current_document_id: $data['current_document_id'],
            sidebar_label: $data['sidebar_label'],
            content: $data['content'],
            is_public: $data['is_public'],
            slug: $data['slug'],
            file_order: $data['file_order'] ?? null,
            edit_pull_request_id: $data['edit_pull_request_id'] ?? null,
            pull_request_edit_token: $data['pull_request_edit_token'] ?? null,
        );
    }

    #[Test]
    public function has_document_changes_returns_true_when_content_is_different(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'old content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'new content', // 変更あり
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_slug_is_different(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'old-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'new-slug', // 変更あり
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_sidebar_label_is_different(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Old Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'New Label', // 変更あり
            'is_public' => false,
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_is_public_is_different(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => true, // 変更あり
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_file_order_is_different(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 2, // 変更あり
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_false_when_no_changes(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_file_order_is_null_and_existing_has_value(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => null, // nullの場合は変更ありとみなす
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_file_order_changes_from_null_to_value(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => null,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => 1, // nullから値への変更
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function has_document_changes_returns_false_when_both_file_order_are_null(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => null,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'same content',
            'slug' => 'same-slug',
            'sidebar_label' => 'Same Label',
            'is_public' => false,
            'file_order' => null, // 両方nullの場合は変更なし
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function has_document_changes_returns_true_when_multiple_fields_change(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'content' => 'old content',
            'slug' => 'old-slug',
            'sidebar_label' => 'Old Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        $dto = $this->createUpdateDocumentDto([
            'current_document_id' => 1,
            'content' => 'new content', // 変更
            'slug' => 'new-slug', // 変更
            'sidebar_label' => 'Old Label',
            'is_public' => false,
            'file_order' => 1,
        ]);

        // Act
        $result = $this->documentDiffService->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }
}
