<?php

namespace Tests\Unit\Services;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentVersion;
use App\Services\DocumentDiffService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentDiffServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentDiffService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentDiffService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUpdateDocumentDto(array $data): UpdateDocumentDto
    {
        // モックリクエストを作成
        $request = Mockery::mock(UpdateDocumentRequest::class);
        $request->shouldReceive('all')->andReturn($data);

        // DTOを作成
        $payload = [
            'category_path' => $data['category_path'] ?? null,
            'current_document_id' => $data['current_document_id'],
            'sidebar_label' => $data['sidebar_label'],
            'content' => $data['content'],
            'is_public' => $data['is_public'],
            'slug' => $data['slug'],
            'file_order' => $data['file_order'] ?? null,
        ];

        return UpdateDocumentDto::fromArray($payload);
    }

    #[Test]
    public function hasDocumentChanges_returns_true_when_content_is_different(): void
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
        $result = $this->service->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function hasDocumentChanges_returns_false_when_no_changes(): void
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
        $result = $this->service->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function hasDocumentChanges_returns_false_when_file_order_is_null(): void
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
            'file_order' => null, // nullの場合は変更なしとみなす
        ]);

        // Act
        $result = $this->service->hasDocumentChanges($dto, $existingDocument);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function canEditDocument_returns_true_for_same_user_branch(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'user_branch_id' => 1,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $userBranchId = 1;

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function canEditDocument_returns_true_for_merged_document_by_different_user(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'user_branch_id' => 1,
            'status' => DocumentStatus::MERGED->value,
        ]);
        $userBranchId = 2; // 異なるユーザーブランチ

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function canEditDocument_returns_false_for_draft_document_by_different_user(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'user_branch_id' => 1,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $userBranchId = 2; // 異なるユーザーブランチ

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function canEditDocument_returns_false_for_pushed_document_by_different_user(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'user_branch_id' => 1,
            'status' => DocumentStatus::PUSHED->value,
        ]);
        $userBranchId = 2; // 異なるユーザーブランチ

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function canEditDocument_returns_true_for_edit_session(): void
    {
        // Arrange
        $existingDocument = DocumentVersion::factory()->make([
            'user_branch_id' => 1,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        $userBranchId = 2; // 異なるユーザーブランチ
        $pullRequestEditSessionId = 1; // 編集セッション中

        // Act
        $result = $this->service->canEditDocument($existingDocument, $userBranchId, $pullRequestEditSessionId);

        // Assert
        $this->assertTrue($result);
    }
}
