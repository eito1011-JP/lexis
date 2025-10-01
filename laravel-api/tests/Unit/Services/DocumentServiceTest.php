<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentService $service;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $activeUserBranch;

    private DocumentEntity $documentEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $CategoryService = $this->createMock(CategoryService::class);
        $this->service = new DocumentService($CategoryService);

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $this->activeUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $this->documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /** @test */
    public function アクティブなユーザーブランチがない場合は_merge_dステータスのドキュメントを取得する(): void
    {
        // Arrange
        // アクティブなユーザーブランチを削除
        $this->activeUserBranch->delete();

        $previousUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function edit_start_versionに登録されている場合は現在のバージョンを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($currentDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 再編集の場合は_pushe_dと_draf_tと_merge_dステータスのドキュメントを取得する(): void
    {
        // Arrange
        $pullRequestEditSessionToken = 'test-token';

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(3),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now()->subDays(1),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user,
            $pullRequestEditSessionToken
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($draftDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 初回編集の場合は_draf_tと_merge_dステータスのドキュメントを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(2),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // PUSHEDステータスのドキュメントも作成（これは取得されないはず）
        DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now()->subDays(1),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($draftDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 初回編集で_draf_tがない場合は_merge_dステータスのドキュメントを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function 他のユーザーブランチの_draf_tは取得されない(): void
    {
        // Arrange
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => User::factory()->create()->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(2),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // 他のユーザーのDRAFTドキュメント
        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function 組織が異なる場合はドキュメントが取得されない(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $otherOrganization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function get_descendant_documents_by_work_context_without_active_user_branch(): void
    {
        // Arrange
        $this->activeUserBranch->delete();

        $categoryEntityId = 1;

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntityId,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->first()->status);
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_initial_edit(): void
    {
        // Arrange
        $categoryEntityId = 1;

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntityId,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::DRAFT->value));
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::MERGED->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_pr_edit_session(): void
    {
        // Arrange
        $categoryEntityId = 1;
        $pullRequestEditSessionToken = 'test-token';

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntityId,
            $this->user,
            $pullRequestEditSessionToken
        );

        // Assert
        $this->assertCount(3, $result);
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::PUSHED->value));
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::DRAFT->value));
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::MERGED->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_child_categories(): void
    {
        // Arrange
        $parentCategoryEntityId = 1;
        $childCategoryEntityId = 2;

        // 親カテゴリの直下のドキュメント
        DocumentVersion::factory()->create([
            'category_entity_id' => $parentCategoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // 子カテゴリのドキュメント
        DocumentVersion::factory()->create([
            'category_entity_id' => $childCategoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // 子カテゴリのモックオブジェクト
        $childCategory = (object) ['entity_id' => $childCategoryEntityId];

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        
        // 親カテゴリに対する呼び出し
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturnCallback(function($entityId) use ($parentCategoryEntityId, $childCategory) {
                if ($entityId === $parentCategoryEntityId) {
                    // 親カテゴリには子カテゴリが1つ存在
                    return new \Illuminate\Database\Eloquent\Collection([$childCategory]);
                } else {
                    // 子カテゴリには子カテゴリが存在しない
                    return new \Illuminate\Database\Eloquent\Collection();
                }
            });

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $parentCategoryEntityId,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    #[Test]
    public function get_descendant_documents_by_work_context_without_child_categories(): void
    {
        // Arrange
        $categoryEntityId = 1;

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntityId,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // CategoryServiceのモックを設定（子カテゴリなし）
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntityId,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(1, $result);
    }
}
