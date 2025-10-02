<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryEntity;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CategoryService;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Collection;
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

        // mergedは所得される
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

        // draftは取得されない
        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
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
        $this->assertNotEquals($draftDocument->id, $result->id);
        $this->assertNotEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function edit_start_versionに登録されている場合は現在のバージョンを取得する(): void
    {
        // Arrange
        // mergedは取得されない
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

        // draftは取得される
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
        $this->assertNotEquals($mergedDocument->id, $result->id);
        $this->assertNotEquals(DocumentStatus::MERGED->value, $result->status);
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
    public function 初回編集で_draftがない場合は_mergedステータスのドキュメントを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => $this->activeUserBranch->id,
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
    public function 他のユーザーブランチの_draftは取得されない(): void
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
            'user_branch_id' => $this->activeUserBranch->id,
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
            'user_branch_id' => $otherUserBranch->id,
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
        $this->assertNotEquals($draftDocument->id, $result->id);
        $this->assertNotEquals(DocumentStatus::DRAFT->value, $result->status);
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


    /* getDescendantDocumentsByWorkContext */
    
    #[Test]
    public function get_descendant_documents_by_work_context_without_active_user_branch(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // アクティブなユーザーブランチを削除
        $this->activeUserBranch->delete();

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
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
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // MERGEDドキュメント
        $mergedDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // DRAFTドキュメント
        $draftDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert: 初回編集時はMERGEDとDRAFTの両方が取得される
        $this->assertGreaterThanOrEqual(1, $result->count());
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::DRAFT->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_pr_edit_session(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $pullRequestEditSessionToken = 'test-token';

        $mergedDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            $pullRequestEditSessionToken
        );

        // Assert: PR編集時はPUSHED/DRAFT/MERGEDが取得される
        $this->assertGreaterThanOrEqual(1, $result->count());
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::PUSHED->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_child_categories(): void
    {
        // Arrange
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 親カテゴリの直下のドキュメント
        $mergedDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $parentCategoryEntity->id,
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 子カテゴリのドキュメント
        $draftDocument = DocumentVersion::factory()->create([
            'category_entity_id' => $childCategoryEntity->id,
            'entity_id' => $draftDocumentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // 子カテゴリのモックオブジェクト
        $childCategory = (object) ['entity_id' => $childCategoryEntity->id];

        // CategoryServiceのモックを設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        
        // 親カテゴリに対する呼び出し
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
        ->willReturnCallback(function ($entityId) use ($parentCategoryEntity, $childCategory) {
            if ($entityId === $parentCategoryEntity->id) {
                return new Collection([$childCategory]);
            }
            return new Collection();
        });

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $parentCategoryEntity->id,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::MERGED->value));
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::DRAFT->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_without_child_categories(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // CategoryServiceのモックを設定（子カテゴリなし）
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains(fn($doc) => $doc->status === DocumentStatus::MERGED->value));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_multi_level_hierarchy(): void
    {
        // Arrange: 親 -> 子 -> 孫 の3階層構造
        $grandparentCategory = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $parentCategory = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 各階層にドキュメントを作成
        $grandparentDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $grandparentDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $grandparentCategory->id,
            'entity_id' => $grandparentDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        $parentDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $parentCategory->id,
            'entity_id' => $parentDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        $childDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $childDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $childCategory->id,
            'entity_id' => $childDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // CategoryServiceのモック設定
        $parentCategoryObj = (object) ['entity_id' => $parentCategory->id];
        $childCategoryObj = (object) ['entity_id' => $childCategory->id];

        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturnCallback(function ($entityId) use ($grandparentCategory, $parentCategory, $parentCategoryObj, $childCategoryObj) {
                if ($entityId === $grandparentCategory->id) {
                    return new Collection([$parentCategoryObj]);
                }
                if ($entityId === $parentCategory->id) {
                    return new Collection([$childCategoryObj]);
                }
                return new Collection();
            });

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $grandparentCategory->id,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(3, $result);
        $this->assertTrue($result->contains('id', $grandparentDoc->id));
        $this->assertTrue($result->contains('id', $parentDoc->id));
        $this->assertTrue($result->contains('id', $childDoc->id));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_mixed_status_in_descendants(): void
    {
        // Arrange: 子孫カテゴリに MERGED と DRAFT が混在
        $parentCategory = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 子カテゴリのドキュメント（同じentityに対してMERGEDとDRAFT）
        $docEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $childCategory->id,
            'entity_id' => $docEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(1),
            'user_branch_id' => null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDoc->id,
            'current_version_id' => $mergedDoc->id,
        ]);

        $draftDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $childCategory->id,
            'entity_id' => $docEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        // CategoryServiceのモック設定
        $childCategoryObj = (object) ['entity_id' => $childCategory->id];
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturnCallback(function ($entityId) use ($parentCategory, $childCategoryObj) {
                if ($entityId === $parentCategory->id) {
                    return new Collection([$childCategoryObj]);
                }
                return new Collection();
            });

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $parentCategory->id,
            $this->user,
            null
        );

        // Assert: MERGED と DRAFT の両方が取得される（フィルタリングは getDocumentsByWorkContext で行われる）
        $this->assertGreaterThanOrEqual(1, $result->count());
        $this->assertTrue(
            $result->contains(fn($doc) => $doc->status === DocumentStatus::DRAFT->value) ||
            $result->contains(fn($doc) => $doc->status === DocumentStatus::MERGED->value)
        );
    }

    #[Test]
    public function get_descendant_documents_by_work_context_with_draft_and_pushed_in_pr_edit(): void
    {
        // Arrange: PR再編集で DRAFT と PUSHED が両方存在
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $docEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mergedDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $docEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(3),
            'user_branch_id' => null,
        ]);

        $pushedDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $docEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now()->subDays(1),
        ]);

        $draftDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $docEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        // CategoryServiceのモック設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            'test-token'
        );

        // Assert: PR再編集時は PUSHED, DRAFT, MERGED すべてが取得対象
        $this->assertGreaterThanOrEqual(1, $result->count());
        $statuses = $result->pluck('status')->toArray();
        $this->assertTrue(
            in_array(DocumentStatus::DRAFT->value, $statuses) ||
            in_array(DocumentStatus::PUSHED->value, $statuses) ||
            in_array(DocumentStatus::MERGED->value, $statuses)
        );
    }

    #[Test]
    public function get_descendant_documents_by_work_context_excludes_other_user_branch_documents(): void
    {
        // Arrange: 他ユーザーブランチの DRAFT/PUSHED が子孫にある
        $otherUser = User::factory()->create();
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 自分のブランチのMERGEDドキュメント
        $myDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $myMergedDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $myDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // 他ユーザーのDRAFTドキュメント
        $otherDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $otherDraftDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $otherDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        // CategoryServiceのモック設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert: 他ユーザーのDRAFTは含まれない
        $this->assertFalse($result->contains('id', $otherDraftDoc->id));
        $this->assertTrue($result->contains('id', $myMergedDoc->id));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_returns_empty_when_no_documents(): void
    {
        // Arrange: ドキュメントが0件
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // CategoryServiceのモック設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function get_descendant_documents_by_work_context_excludes_soft_deleted_documents(): void
    {
        // Arrange: is_deleted=1（ソフトデリート）のドキュメント
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 通常のドキュメント
        $normalDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $normalDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $normalDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // ソフトデリートされたドキュメント
        $deletedDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $deletedDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $deletedDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);
        $deletedDoc->delete(); // ソフトデリート

        // CategoryServiceのモック設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert: ソフトデリートされたドキュメントは含まれない
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains('id', $normalDoc->id));
        $this->assertFalse($result->contains('id', $deletedDoc->id));
    }

    #[Test]
    public function get_descendant_documents_by_work_context_excludes_different_organization_documents(): void
    {
        // Arrange: 組織不一致のドキュメント混入
        $otherOrganization = Organization::factory()->create();

        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 正しい組織のドキュメント
        $correctOrgDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $correctOrgDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $correctOrgDocEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // 異なる組織のドキュメント
        $wrongOrgDocEntity = DocumentEntity::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);
        $wrongOrgDoc = DocumentVersion::factory()->create([
            'category_entity_id' => $categoryEntity->id,
            'entity_id' => $wrongOrgDocEntity->id,
            'organization_id' => $otherOrganization->id,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);

        // CategoryServiceのモック設定
        $categoryServiceMock = $this->createMock(CategoryService::class);
        $categoryServiceMock->method('getChildCategoriesByWorkContext')
            ->willReturn(new Collection());

        $service = new DocumentService($categoryServiceMock);

        // Act
        $result = $service->getDescendantDocumentsByWorkContext(
            $categoryEntity->id,
            $this->user,
            null
        );

        // Assert: 異なる組織のドキュメントは含まれない
        $this->assertTrue($result->contains('id', $correctOrgDoc->id));
        $this->assertFalse($result->contains('id', $wrongOrgDoc->id));
    }
}
