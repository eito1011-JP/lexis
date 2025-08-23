<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateFileOrderTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentService $documentService;

    private DocumentCategoryService $documentCategoryService;

    private DocumentCategory $category;

    private UserBranch $userBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = DocumentCategory::factory()->create();
        $this->userBranch = UserBranch::factory()->create();

        $this->documentCategoryService = $this->createMock(DocumentCategoryService::class);
        $this->documentService = new DocumentService($this->documentCategoryService);
    }

    /**
     * ドキュメントバージョンとEditStartVersionを作成するヘルパーメソッド
     */
    private function createDocumentWithEditStartVersion(
        int $fileOrder,
        DocumentStatus $status = DocumentStatus::MERGED,
        ?int $categoryId = null,
        ?int $userBranchId = null
    ): DocumentVersion {
        $document = DocumentVersion::factory()->create([
            'status' => $status->value,
            'category_id' => $categoryId ?? $this->category->id,
            'file_order' => $fileOrder,
            'user_branch_id' => $userBranchId ?? $this->userBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $userBranchId ?? $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        return $document;
    }

    /**
     * updateFileOrderメソッドを呼び出すヘルパーメソッド
     */
    private function callUpdateFileOrder(
        int $newFileOrder,
        DocumentVersion $targetDocument,
        User $user,
        ?int $editPullRequestId = null
    ): int {
        return $this->documentService->updateFileOrder(
            $newFileOrder,
            $targetDocument->file_order,
            $targetDocument->category_id,
            $this->userBranch->id,
            $editPullRequestId,
            $targetDocument->id,
            $user->id,
            $user->email
        );
    }

    /**
     * ドキュメントの状態が変更されていないことを検証するヘルパーメソッド
     */
    private function assertDocumentUnchanged(DocumentVersion $document): void
    {
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $document->id,
            'category_id' => $document->category_id,
            'file_order' => $document->file_order,
            'user_branch_id' => $document->user_branch_id,
            'status' => $document->status,
        ]);
    }

    /**
     * 新しく作成されたDRAFTバージョンを検証するヘルパーメソッド
     */
    private function assertNewDraftVersionCreated(
        DocumentVersion $originalDocument,
        DocumentVersion $targetDocument,
        User $user,
        int $expectedFileOrder
    ): DocumentVersion {
        $newDraftVersion = DocumentVersion::where('id', '!=', $originalDocument->id)
            ->where('id', '!=', $targetDocument->id)
            ->where('status', DocumentStatus::DRAFT->value)
            ->first();

        $this->assertNotNull($newDraftVersion);
        $this->assertSame(DocumentStatus::DRAFT->value, $newDraftVersion->status);
        $this->assertSame($expectedFileOrder, $newDraftVersion->file_order);
        $this->assertSame($originalDocument->file_path, $newDraftVersion->file_path);
        $this->assertSame($originalDocument->content, $newDraftVersion->content);
        $this->assertSame($originalDocument->slug, $newDraftVersion->slug);
        $this->assertSame($originalDocument->sidebar_label, $newDraftVersion->sidebar_label);
        $this->assertSame($originalDocument->is_public, $newDraftVersion->is_public);
        $this->assertSame($originalDocument->category_id, $newDraftVersion->category_id);
        $this->assertSame($user->id, $newDraftVersion->user_id);
        $this->assertSame($this->userBranch->id, $newDraftVersion->user_branch_id);
        $this->assertSame($user->email, $newDraftVersion->last_edited_by);

        return $newDraftVersion;
    }

    /**
     * 新しく作成されたEditStartVersionを検証するヘルパーメソッド
     */
    private function assertNewEditStartVersionCreated(
        DocumentVersion $newDraftVersion,
        DocumentVersion $originalDocument
    ): void {
        $newEditStartVersion = EditStartVersion::where('current_version_id', $newDraftVersion->id)->first();

        $this->assertNotNull($newEditStartVersion);
        $this->assertSame($this->userBranch->id, $newEditStartVersion->user_branch_id);
        $this->assertSame(EditStartVersionTargetType::DOCUMENT->value, $newEditStartVersion->target_type);
        $this->assertSame($originalDocument->id, $newEditStartVersion->original_version_id);
        $this->assertSame($newDraftVersion->id, $newEditStartVersion->current_version_id);
    }

    #[Test]
    public function one_item_same_order_is_no_op_before_submit_pr(): void
    {
        // Arrange
        $existingDocument = $this->createDocumentWithEditStartVersion(1);
        $user = User::factory()->create();
        $newFileOrder = 1;

        // Act
        $finalFileOrder = $this->callUpdateFileOrder($newFileOrder, $existingDocument, $user);

        // Assert
        $this->assertSame($newFileOrder, $finalFileOrder);
        $this->assertDatabaseCount(DocumentVersion::class, 1);
        $this->assertDatabaseCount(EditStartVersion::class, 1);
        $this->assertDocumentUnchanged($existingDocument);
    }

    #[Test]
    public function two_items_move_up_2_to_1_before_submit_pr(): void
    {
        // Arrange
        $existingDocument = $this->createDocumentWithEditStartVersion(1);
        $targetDocument = $this->createDocumentWithEditStartVersion(2);
        $user = User::factory()->create();
        $newFileOrder = 1;

        // Act
        $finalFileOrder = $this->callUpdateFileOrder($newFileOrder, $targetDocument, $user);

        // Assert
        $this->assertSame($newFileOrder, $finalFileOrder);

        // 既存のドキュメントは変更されない
        $this->assertDocumentUnchanged($existingDocument);
        $this->assertDocumentUnchanged($targetDocument);

        // 新しいDRAFTバージョンが作成される
        $this->assertDatabaseCount(DocumentVersion::class, 3);
        $this->assertDatabaseCount(EditStartVersion::class, 3);

        $newDraftVersion = $this->assertNewDraftVersionCreated(
            $existingDocument,
            $targetDocument,
            $user,
            2
        );

        $this->assertNewEditStartVersionCreated($newDraftVersion, $existingDocument);
    }

    #[Test]
    public function two_items_move_down_1_to_2_before_submit_pr(): void
    {
        // Arrange
        $existingDocument = $this->createDocumentWithEditStartVersion(2);
        $targetDocument = $this->createDocumentWithEditStartVersion(1);
        $user = User::factory()->create();
        $newFileOrder = 2;

        // Act
        $finalFileOrder = $this->callUpdateFileOrder($newFileOrder, $targetDocument, $user);

        // Assert
        $this->assertSame($newFileOrder, $finalFileOrder);

        // 既存のドキュメントは変更されない
        $this->assertDocumentUnchanged($existingDocument);
        $this->assertDocumentUnchanged($targetDocument);

        // 新しいDRAFTバージョンが作成される
        $this->assertDatabaseCount(DocumentVersion::class, 3);
        $this->assertDatabaseCount(EditStartVersion::class, 3);

        $newDraftVersion = $this->assertNewDraftVersionCreated(
            $existingDocument,
            $targetDocument,
            $user,
            1
        );

        $this->assertNewEditStartVersionCreated($newDraftVersion, $existingDocument);
    }

    // =========================
    // 3件以上: 中間へ上移動（3→2）
    // =========================
    #[Test]
    public function three_items_move_up_3_to_2_before_submit_pr(): void
    {
        // Arrange
        $existingDocumentA = $this->createDocumentWithEditStartVersion(1);
        $existingDocumentB = $this->createDocumentWithEditStartVersion(2);
        $targetDocument = $this->createDocumentWithEditStartVersion(3);
        $user = User::factory()->create();
        $newFileOrder = 2;

        // Act
        $finalFileOrder = $this->callUpdateFileOrder($newFileOrder, $targetDocument, $user);

        // Assert
        $this->assertSame($newFileOrder, $finalFileOrder);

        // 既存のドキュメントは変更されない
        $this->assertDocumentUnchanged($existingDocumentA);
        $this->assertDocumentUnchanged($existingDocumentB);
        $this->assertDocumentUnchanged($targetDocument);

        /**
         * 新しいDRAFTバージョンが作成される
         * 影響があるのはfile_order = 2 & 3のみなので、1件のみDraftが追加される
         */
        $this->assertDatabaseCount(DocumentVersion::class, 4);
        $this->assertDatabaseCount(EditStartVersion::class, 4);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $existingDocumentA->id,
            'category_id' => $existingDocumentA->category_id,
            'file_order' => $existingDocumentA->file_order,
            'user_branch_id' => $existingDocumentA->user_branch_id,
            'status' => $existingDocumentA->status,
        ]);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $existingDocumentB->id,
            'category_id' => $existingDocumentB->category_id,
            'file_order' => $existingDocumentB->file_order,
            'user_branch_id' => $existingDocumentB->user_branch_id,
            'status' => $existingDocumentB->status,
        ]);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $targetDocument->id,
            'category_id' => $targetDocument->category_id,
            'file_order' => $targetDocument->file_order,
            'user_branch_id' => $targetDocument->user_branch_id,
            'status' => $targetDocument->status,
        ]);

        $newDraftVersion = $this->assertNewDraftVersionCreated(
            $existingDocumentB,
            $targetDocument,
            $user,
            3
        );

        $this->assertNewEditStartVersionCreated($newDraftVersion, $existingDocumentB);
    }

    // =========================
    // 3件以上: 中間へ下移動（2→3）
    // =========================
    #[Test]
    public function three_items_move_down_2_to_3_before_submit_pr(): void
    {
        // Arrange
        $existingDocumentA = $this->createDocumentWithEditStartVersion(1);
        $existingDocumentB = $this->createDocumentWithEditStartVersion(3);
        $targetDocument = $this->createDocumentWithEditStartVersion(2);
        $user = User::factory()->create();
        $newFileOrder = 3;

        // Act
        $finalFileOrder = $this->callUpdateFileOrder($newFileOrder, $targetDocument, $user);

        // Assert
        $this->assertSame($newFileOrder, $finalFileOrder);

        // 既存のドキュメントは変更されない
        $this->assertDocumentUnchanged($existingDocumentA);
        $this->assertDocumentUnchanged($existingDocumentB);
        $this->assertDocumentUnchanged($targetDocument);

        /**
         * 新しいDRAFTバージョンが作成される
         * 影響があるのはfile_order = 2 & 3のみなので、1件のみDraftが追加される
         */
        $this->assertDatabaseCount(DocumentVersion::class, 4);
        $this->assertDatabaseCount(EditStartVersion::class, 4);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $existingDocumentA->id,
            'category_id' => $existingDocumentA->category_id,
            'file_order' => $existingDocumentA->file_order,
            'user_branch_id' => $existingDocumentA->user_branch_id,
            'status' => $existingDocumentA->status,
        ]);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $existingDocumentB->id,
            'category_id' => $existingDocumentB->category_id,
            'file_order' => $existingDocumentB->file_order,
            'user_branch_id' => $existingDocumentB->user_branch_id,
            'status' => $existingDocumentB->status,
        ]);
        $this->assertDatabaseHas(DocumentVersion::class, [
            'id' => $targetDocument->id,
            'category_id' => $targetDocument->category_id,
            'file_order' => $targetDocument->file_order,
            'user_branch_id' => $targetDocument->user_branch_id,
            'status' => $targetDocument->status,
        ]);

        $newDraftVersion = $this->assertNewDraftVersionCreated(
            $existingDocumentB,
            $targetDocument,
            $user,
            2
        );

        $this->assertNewEditStartVersionCreated($newDraftVersion, $existingDocumentB);
    }
}
