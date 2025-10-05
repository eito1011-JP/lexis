<?php

namespace Tests\Unit\Services;

use App\Consts\Flag;
use App\Enums\EditStartVersionTargetType;
use App\Enums\DocumentCategoryStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\CategoryService;
use App\Services\DocumentDiffService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentDiffServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface&CategoryService */
    private CategoryService $categoryService;

    private DocumentDiffService $service;

    private User $user;

    private Organization $organization;

    private UserBranch $activeUserBranch;

    private UserBranch $inactiveUserBranch;

    private CategoryEntity $mergedCategoryEntity;

    private CategoryVersion $mergedCategory;

    private EditStartVersion $mergedCategoryEditStartVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoryService = Mockery::mock(CategoryService::class);
        $this->service = new DocumentDiffService($this->categoryService);

        // 組織とユーザーを作成
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // ユーザーを組織に関連付け
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // 非アクティブなユーザーブランチを作成
        $this->inactiveUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
            'organization_id' => $this->organization->id,
        ]);

        // アクティブなユーザーブランチを作成
        $this->activeUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ]);

        $this->mergedCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->mergedCategory = CategoryVersion::factory()->create([
            'parent_entity_id' => null,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $this->mergedCategoryEntity->id,
            'title' => 'マージ済みカテゴリ',
            'description' => 'マージ済みカテゴリの説明',
        ]);
        $this->mergedCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->mergedCategory->id,
            'current_version_id' => $this->mergedCategory->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function generateDiffData_returns_created_category_operation(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $category = CategoryVersion::factory()->create([
            'parent_entity_id' => null,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'title' => '新規カテゴリ',
            'description' => '新規カテゴリの説明',
        ]);
        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category->id,
            'current_version_id' => $category->id,
        ]);
        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($category->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('created', $diffItem['operation']);
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        // changed_fieldsの検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('added', $diffItem['changed_fields']['title']['status']);
        $this->assertEquals($category->title, $diffItem['changed_fields']['title']['current']);
        $this->assertNull($diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('added', $diffItem['changed_fields']['description']['status']);
        $this->assertEquals($category->description, $diffItem['changed_fields']['description']['current']);
        $this->assertNull($diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_created_document_and_category_operation(): void
    {
        // Arrange
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $parentCategory = CategoryVersion::factory()->create([
            'parent_entity_id' => null,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $parentCategoryEntity->id,
            'title' => '親カテゴリ',
            'description' => '親カテゴリの説明',
        ]);
        $categoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $parentCategory->id,
            'current_version_id' => $parentCategory->id,
        ]);

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $document = DocumentVersion::factory()->create([
            'category_entity_id' => $parentCategoryEntity->id,
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'title' => '新規ドキュメント',
            'description' => '新規ドキュメントの説明',
        ]);
        $documentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$categoryEditStartVersion, $documentEditStartVersion]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // カテゴリのdiff検証
        $categoryDiff = $result['diff'][0];
        $this->assertEquals($parentCategory->id, $categoryDiff['id']);
        $this->assertEquals('category', $categoryDiff['type']);
        $this->assertEquals('created', $categoryDiff['operation']);
        $this->assertArrayHasKey('title', $categoryDiff['changed_fields']);
        $this->assertEquals('added', $categoryDiff['changed_fields']['title']['status']);
        
        // ドキュメントのdiff検証
        $documentDiff = $result['diff'][1];
        $this->assertEquals($document->id, $documentDiff['id']);
        $this->assertEquals('document', $documentDiff['type']);
        $this->assertEquals('created', $documentDiff['operation']);
        $this->assertArrayHasKey('title', $documentDiff['changed_fields']);
        $this->assertEquals('added', $documentDiff['changed_fields']['title']['status']);
        $this->assertArrayHasKey('description', $documentDiff['changed_fields']);
        $this->assertEquals('added', $documentDiff['changed_fields']['description']['status']);
    }

    #[Test]
    public function generateDiffData_returns_created_document_operation(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $document = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '新規ドキュメント',
            'description' => '新規ドキュメントの説明',
        ]);
        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($document->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('created', $diffItem['operation']);
        
        // ドキュメントのchanged_fields検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('added', $diffItem['changed_fields']['title']['status']);
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('added', $diffItem['changed_fields']['description']['status']);
    }

    #[Test]
    public function generateDiffData_returns_multiple_created_categories_and_documents(): void
    {
        // Arrange - 2つのカテゴリを作成
        $category1Entity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $category1 = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category1Entity->id,
            'parent_entity_id' => null,
            'title' => 'カテゴリ1',
            'description' => 'カテゴリ1の説明',
        ]);
        $categoryEditStartVersion1 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category1->id,
            'current_version_id' => $category1->id,
        ]);

        $category2Entity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $category2 = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $category2Entity->id,
            'parent_entity_id' => null,
            'title' => 'カテゴリ2',
            'description' => 'カテゴリ2の説明',
        ]);
        $categoryEditStartVersion2 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category2->id,
            'current_version_id' => $category2->id,
        ]);

        // 2つのドキュメントを作成
        $document1Entity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $document1 = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $document1Entity->id,
            'title' => 'ドキュメント1',
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'description' => 'ドキュメント1の説明',
        ]);
        $documentEditStartVersion1 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document1->id,
            'current_version_id' => $document1->id,
        ]);

        $document2Entity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $document2 = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $document2Entity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => 'ドキュメント2',
            'description' => 'ドキュメント2の説明',
        ]);
        $documentEditStartVersion2 = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document2->id,
            'current_version_id' => $document2->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([
            $categoryEditStartVersion1,
            $categoryEditStartVersion2,
            $documentEditStartVersion1,
            $documentEditStartVersion2
        ]));

        // Assert
        $this->assertCount(4, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('created', $result['diff'][1]['operation']);
        $this->assertEquals('created', $result['diff'][2]['operation']);
        $this->assertEquals('created', $result['diff'][3]['operation']);
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_merged_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        // マージ済みカテゴリ（元バージョン）
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'title' => '元のタイトル',
            'description' => '元の説明',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        // 編集後のドラフトカテゴリ
        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'title' => '編集後のタイトル',
            'description' => '編集後の説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentCategory->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('updated', $diffItem['operation']);
        
        // updated操作のchanged_fields検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['title']['status']);
        $this->assertEquals($currentCategory->title, $diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalCategory->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['description']['status']);
        $this->assertEquals($currentCategory->description, $diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalCategory->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_pushed_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'title' => '元のタイトル',
            'description' => '元の説明',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'title' => '編集後のタイトル',
            'description' => '編集後の説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function generateDiffData_returns_created_diff_when_editing_draft_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'title' => 'ドラフトカテゴリ',
            'description' => 'ドラフトカテゴリの説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $currentCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);
        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEquals('created', $result['diff'][0]['operation']);
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_merged_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '編集後のドキュメント',
            'description' => '編集後のドキュメント説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentDocument->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('updated', $diffItem['operation']);
        
        // updated操作のchanged_fields検証（変更されたフィールドのみ存在）
        $this->assertArrayHasKey('changed_fields', $diffItem);
        // タイトルや説明など、変更されたフィールドがmodifiedになっていることを検証
        foreach ($diffItem['changed_fields'] as $field => $change) {
            $this->assertEquals('modified', $change['status']);
            $this->assertArrayHasKey('current', $change);
            $this->assertArrayHasKey('original', $change);
        }
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_pushed_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '編集後のドキュメント',
            'description' => '編集後のドキュメント説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function generateDiffData_returns_created_diff_when_editing_draft_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => 'ドラフトドキュメント',
            'description' => 'ドラフトドキュメントの説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $currentDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEquals('created', $result['diff'][0]['operation']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_merged_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'is_deleted' => 0,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentCategory->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalCategory->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
        $this->assertNull($diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalCategory->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_pushed_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'is_deleted' => 0,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentCategory->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        // 各フィールドを詳細に検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalCategory->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
        $this->assertNull($diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalCategory->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_empty_diff_when_deleting_draft_category(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'parent_entity_id' => null,
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $currentCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEmpty($result['diff']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_merged_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 0,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        // diffの構造を詳細に検証
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentDocument->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalDocument->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_pushed_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 0,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentDocument->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        // 各フィールドを詳細に検証
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalDocument->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
        $this->assertNull($diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalDocument->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_empty_diff_when_deleting_draft_document(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 0,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertEmpty($result['diff']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_creating_and_updating_categories(): void
    {
        // Arrange - ドラフトカテゴリを作成
        $draftCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $draftCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftCategoryEntity->id,
            'parent_entity_id' => null,
        ]);
        $draftEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // マージ済みカテゴリを更新
        $mergedCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalMergedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $mergedCategoryEntity->id,
            'parent_entity_id' => null,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $originalMergedCategory->id,
        ]);
        $updatedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $mergedCategoryEntity->id,
            'parent_entity_id' => null,
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $updatedCategory->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$draftEditStartVersion, $updatedEditStartVersion]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // 作成操作の検証
        $createdDiff = $result['diff'][0];
        $this->assertEquals($draftCategory->id, $createdDiff['id']);
        $this->assertEquals('category', $createdDiff['type']);
        $this->assertEquals('created', $createdDiff['operation']);
        
        $this->assertArrayHasKey('title', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['title']['status']);
        $this->assertEquals($draftCategory->title, $createdDiff['changed_fields']['title']['current']);
        $this->assertNull($createdDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['description']['status']);
        $this->assertEquals($draftCategory->description, $createdDiff['changed_fields']['description']['current']);
        $this->assertNull($createdDiff['changed_fields']['description']['original']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][1];
        $this->assertEquals($updatedCategory->id, $updatedDiff['id']);
        $this->assertEquals('category', $updatedDiff['type']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedCategory->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalMergedCategory->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedCategory->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalMergedCategory->description, $updatedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_updating_and_deleting_categories(): void
    {
        // Arrange - マージ済みカテゴリを更新
        $updateCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalUpdateCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $updateCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalUpdateCategory->id,
            'current_version_id' => $originalUpdateCategory->id,
        ]);
        $updatedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $updateCategoryEntity->id,
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalUpdateCategory->id,
            'current_version_id' => $updatedCategory->id,
        ]);

        // マージ済みカテゴリを削除
        $deleteCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalDeleteCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $deleteCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $originalDeleteCategory->id,
        ]);
        $deletedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $deleteCategoryEntity->id,
            'is_deleted' => 1,
        ]);
        $deletedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$updatedEditStartVersion, $deletedEditStartVersion]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][0];
        $this->assertEquals($updatedCategory->id, $updatedDiff['id']);
        $this->assertEquals('category', $updatedDiff['type']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedCategory->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalUpdateCategory->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedCategory->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalUpdateCategory->description, $updatedDiff['changed_fields']['description']['original']);
        
        // 削除操作の検証
        $deletedDiff = $result['diff'][1];
        $this->assertEquals($deletedCategory->id, $deletedDiff['id']);
        $this->assertEquals('category', $deletedDiff['type']);
        $this->assertEquals('deleted', $deletedDiff['operation']);
        
        $this->assertArrayHasKey('title', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['title']['status']);
        $this->assertNull($deletedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalDeleteCategory->title, $deletedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['description']['status']);
        $this->assertNull($deletedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalDeleteCategory->description, $deletedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_creating_updating_and_deleting_categories(): void
    {
        // Arrange - ドラフトカテゴリを作成
        $draftCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $draftCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftCategoryEntity->id,
        ]);
        $draftEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $draftCategory->id,
            'current_version_id' => $draftCategory->id,
        ]);

        // マージ済みカテゴリを更新
        $updateCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalUpdateCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $updateCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalUpdateCategory->id,
            'current_version_id' => $originalUpdateCategory->id,
        ]);
        $updatedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $updateCategoryEntity->id,
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalUpdateCategory->id,
            'current_version_id' => $updatedCategory->id,
        ]);

        // マージ済みカテゴリを削除
        $deleteCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalDeleteCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $deleteCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $originalDeleteCategory->id,
        ]);
        $deletedCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $deleteCategoryEntity->id,
            'is_deleted' => 1,
        ]);
        $deletedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([
            $draftEditStartVersion,
            $updatedEditStartVersion,
            $deletedEditStartVersion
        ]));

        // Assert
        $this->assertCount(3, $result['diff']);
        
        // 作成操作の検証
        $createdDiff = $result['diff'][0];
        $this->assertEquals($draftCategory->id, $createdDiff['id']);
        $this->assertEquals('created', $createdDiff['operation']);
        $this->assertEquals('category', $createdDiff['type']);
        
        $this->assertArrayHasKey('title', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['title']['status']);
        $this->assertEquals($draftCategory->title, $createdDiff['changed_fields']['title']['current']);
        $this->assertNull($createdDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['description']['status']);
        $this->assertEquals($draftCategory->description, $createdDiff['changed_fields']['description']['current']);
        $this->assertNull($createdDiff['changed_fields']['description']['original']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][1];
        $this->assertEquals($updatedCategory->id, $updatedDiff['id']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        $this->assertEquals('category', $updatedDiff['type']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedCategory->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalUpdateCategory->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedCategory->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalUpdateCategory->description, $updatedDiff['changed_fields']['description']['original']);
        
        // 削除操作の検証
        $deletedDiff = $result['diff'][2];
        $this->assertEquals($deletedCategory->id, $deletedDiff['id']);
        $this->assertEquals('deleted', $deletedDiff['operation']);
        $this->assertEquals('category', $deletedDiff['type']);
        
        $this->assertArrayHasKey('title', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['title']['status']);
        $this->assertNull($deletedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalDeleteCategory->title, $deletedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['description']['status']);
        $this->assertNull($deletedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalDeleteCategory->description, $deletedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_creating_and_updating_documents(): void
    {
        // Arrange - ドラフトドキュメントを作成
        $draftDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $draftDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '新規ドキュメント',
            'description' => '新規ドキュメント説明',
        ]);
        $draftEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // マージ済みドキュメントを更新
        $mergedDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalMergedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $mergedDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalMergedDocument->id,
            'current_version_id' => $originalMergedDocument->id,
        ]);
        $updatedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $mergedDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '更新したドキュメント',
            'description' => '更新したドキュメント説明',
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalMergedDocument->id,
            'current_version_id' => $updatedDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$draftEditStartVersion, $updatedEditStartVersion]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // 作成操作の検証
        $createdDiff = $result['diff'][0];
        $this->assertEquals($draftDocument->id, $createdDiff['id']);
        $this->assertEquals('created', $createdDiff['operation']);
        $this->assertEquals('document', $createdDiff['type']);
        
        $this->assertArrayHasKey('title', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['title']['status']);
        $this->assertEquals($draftDocument->title, $createdDiff['changed_fields']['title']['current']);
        $this->assertNull($createdDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['description']['status']);
        $this->assertEquals($draftDocument->description, $createdDiff['changed_fields']['description']['current']);
        $this->assertNull($createdDiff['changed_fields']['description']['original']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][1];
        $this->assertEquals($updatedDocument->id, $updatedDiff['id']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        $this->assertEquals('document', $updatedDiff['type']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedDocument->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalMergedDocument->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedDocument->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalMergedDocument->description, $updatedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_updating_and_deleting_documents(): void
    {
        // Arrange - マージ済みドキュメントを更新
        $updateDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalUpdateDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $updateDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalUpdateDocument->id,
            'current_version_id' => $originalUpdateDocument->id,
        ]);
        $updatedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $updateDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '更新したドキュメント',
            'description' => '更新したドキュメント説明',
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalUpdateDocument->id,
            'current_version_id' => $updatedDocument->id,
        ]);

        // マージ済みドキュメントを削除
        $deleteDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalDeleteDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $deleteDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $originalDeleteDocument->id,
        ]);
        $deletedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $deleteDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 1,
        ]);
        $deletedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$updatedEditStartVersion, $deletedEditStartVersion]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][0];
        $this->assertEquals($updatedDocument->id, $updatedDiff['id']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        $this->assertEquals('document', $updatedDiff['type']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedDocument->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalUpdateDocument->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedDocument->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalUpdateDocument->description, $updatedDiff['changed_fields']['description']['original']);
        
        // 削除操作の検証
        $deletedDiff = $result['diff'][1];
        $this->assertEquals($deletedDocument->id, $deletedDiff['id']);
        $this->assertEquals('deleted', $deletedDiff['operation']);
        $this->assertEquals('document', $deletedDiff['type']);
        
        $this->assertArrayHasKey('title', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['title']['status']);
        $this->assertNull($deletedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalDeleteDocument->title, $deletedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['description']['status']);
        $this->assertNull($deletedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalDeleteDocument->description, $deletedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_mixed_diff_when_creating_updating_and_deleting_documents(): void
    {
        // Arrange - ドラフトドキュメントを作成
        $draftDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $draftDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '新規ドキュメント',
            'description' => '新規ドキュメント説明',
        ]);
        $draftEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $draftDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // マージ済みドキュメントを更新
        $updateDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalUpdateDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $updateDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalUpdateDocument->id,
            'current_version_id' => $originalUpdateDocument->id,
        ]);
        $updatedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $updateDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '更新したドキュメント',
            'description' => '更新したドキュメント説明',
        ]);
        $updatedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalUpdateDocument->id,
            'current_version_id' => $updatedDocument->id,
        ]);

        // マージ済みドキュメントを削除
        $deleteDocumentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalDeleteDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $deleteDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $originalDeleteDocument->id,
        ]);
        $deletedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $deleteDocumentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '削除するドキュメント',
            'description' => '削除するドキュメント説明',
            'is_deleted' => 1,
        ]);
        $deletedEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([
            $draftEditStartVersion,
            $updatedEditStartVersion,
            $deletedEditStartVersion
        ]));

        // Assert
        $this->assertCount(3, $result['diff']);
        
        // 作成操作の検証
        $createdDiff = $result['diff'][0];
        $this->assertEquals($draftDocument->id, $createdDiff['id']);
        $this->assertEquals('created', $createdDiff['operation']);
        $this->assertEquals('document', $createdDiff['type']);
        
        $this->assertArrayHasKey('title', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['title']['status']);
        $this->assertEquals($draftDocument->title, $createdDiff['changed_fields']['title']['current']);
        $this->assertNull($createdDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $createdDiff['changed_fields']);
        $this->assertEquals('added', $createdDiff['changed_fields']['description']['status']);
        $this->assertEquals($draftDocument->description, $createdDiff['changed_fields']['description']['current']);
        $this->assertNull($createdDiff['changed_fields']['description']['original']);
        
        // 更新操作の検証
        $updatedDiff = $result['diff'][1];
        $this->assertEquals($updatedDocument->id, $updatedDiff['id']);
        $this->assertEquals('updated', $updatedDiff['operation']);
        $this->assertEquals('document', $updatedDiff['type']);
        
        $this->assertArrayHasKey('title', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['title']['status']);
        $this->assertEquals($updatedDocument->title, $updatedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalUpdateDocument->title, $updatedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $updatedDiff['changed_fields']);
        $this->assertEquals('modified', $updatedDiff['changed_fields']['description']['status']);
        $this->assertEquals($updatedDocument->description, $updatedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalUpdateDocument->description, $updatedDiff['changed_fields']['description']['original']);
        
        // 削除操作の検証
        $deletedDiff = $result['diff'][2];
        $this->assertEquals($deletedDocument->id, $deletedDiff['id']);
        $this->assertEquals('deleted', $deletedDiff['operation']);
        $this->assertEquals('document', $deletedDiff['type']);
        
        $this->assertArrayHasKey('title', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['title']['status']);
        $this->assertNull($deletedDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalDeleteDocument->title, $deletedDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $deletedDiff['changed_fields']);
        $this->assertEquals('deleted', $deletedDiff['changed_fields']['description']['status']);
        $this->assertNull($deletedDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalDeleteDocument->description, $deletedDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_same_category_entity_twice(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $categoryEntity->id,
            'title' => '元のタイトル',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        // 2回目に編集したドラフトカテゴリ
        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'title' => '2回目の編集',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentCategory->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('updated', $diffItem['operation']);
        $this->assertEquals('2回目の編集', $diffItem['changed_fields']['title']['current']);
        
        // changed_fieldsの検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['title']['status']);
        $this->assertEquals($currentCategory->title, $diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalCategory->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['description']['status']);
        $this->assertEquals($currentCategory->description, $diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalCategory->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_editing_then_deleting_same_category_entity(): void
    {
        // Arrange
        $categoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $categoryEntity->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $originalCategory->id,
        ]);

        // 削除されたドラフトカテゴリ
        $currentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $categoryEntity->id,
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentCategory->id, $diffItem['id']);
        $this->assertEquals('category', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalCategory->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
        $this->assertNull($diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalCategory->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_updated_diff_when_editing_same_document_entity_twice(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        // 2回目に編集したドラフトドキュメント
        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '2回目の編集',
            'description' => '2回目の編集説明',
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentDocument->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('updated', $diffItem['operation']);
        $this->assertEquals('2回目の編集', $diffItem['changed_fields']['title']['current']);
        
        // updated操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['title']['status']);
        $this->assertEquals($currentDocument->title, $diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalDocument->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('modified', $diffItem['changed_fields']['description']['status']);
        $this->assertEquals($currentDocument->description, $diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalDocument->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_editing_then_deleting_same_document_entity(): void
    {
        // Arrange
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);

        // 削除されたドラフトドキュメント
        $currentDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $this->mergedCategoryEntity->id,
            'title' => '元のドキュメント',
            'description' => '元のドキュメント説明',
            'is_deleted' => 1,
        ]);

        $editStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->generateDiffData(collect([$editStartVersion]));

        // Assert
        $this->assertCount(1, $result['diff']);
        
        $diffItem = $result['diff'][0];
        $this->assertEquals($currentDocument->id, $diffItem['id']);
        $this->assertEquals('document', $diffItem['type']);
        $this->assertEquals('deleted', $diffItem['operation']);
        
        // deleted操作のchanged_fields検証
        $this->assertArrayHasKey('changed_fields', $diffItem);
        
        $this->assertArrayHasKey('title', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['title']['status']);
        $this->assertNull($diffItem['changed_fields']['title']['current']);
        $this->assertEquals($originalDocument->title, $diffItem['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $diffItem['changed_fields']);
        $this->assertEquals('deleted', $diffItem['changed_fields']['description']['status']);
        $this->assertNull($diffItem['changed_fields']['description']['current']);
        $this->assertEquals($originalDocument->description, $diffItem['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_parent_category_with_child_document(): void
    {
        // Arrange - 親カテゴリ
        $parentCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalParentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $parentCategoryEntity->id,
            'title' => '親カテゴリ',
            'description' => '親カテゴリ説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalParentCategory->id,
            'current_version_id' => $originalParentCategory->id,
        ]);
        $deletedParentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $parentCategoryEntity->id,
            'title' => '親カテゴリ',
            'description' => '親カテゴリ説明',
            'is_deleted' => 1,
        ]);
        $categoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalParentCategory->id,
            'current_version_id' => $deletedParentCategory->id,
        ]);

        // 従属するドキュメント
        $documentEntity = DocumentEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $parentCategoryEntity->id,
            'title' => '子ドキュメント',
            'description' => '子ドキュメント説明',
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $originalDocument->id,
        ]);
        $deletedDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $documentEntity->id,
            'category_entity_id' => $parentCategoryEntity->id,
            'title' => '子ドキュメント',
            'description' => '子ドキュメント説明',
            'is_deleted' => 1,
        ]);
        $documentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([
            $categoryEditStartVersion,
            $documentEditStartVersion
        ]));

        // Assert
        $this->assertCount(2, $result['diff']);
        
        // 親カテゴリの削除検証
        $categoryDiff = $result['diff'][0];
        $this->assertEquals($deletedParentCategory->id, $categoryDiff['id']);
        $this->assertEquals('category', $categoryDiff['type']);
        $this->assertEquals('deleted', $categoryDiff['operation']);
        
        $this->assertArrayHasKey('title', $categoryDiff['changed_fields']);
        $this->assertEquals('deleted', $categoryDiff['changed_fields']['title']['status']);
        $this->assertNull($categoryDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalParentCategory->title, $categoryDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $categoryDiff['changed_fields']);
        $this->assertEquals('deleted', $categoryDiff['changed_fields']['description']['status']);
        $this->assertNull($categoryDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalParentCategory->description, $categoryDiff['changed_fields']['description']['original']);
        
        // 子ドキュメントの削除検証
        $documentDiff = $result['diff'][1];
        $this->assertEquals($deletedDocument->id, $documentDiff['id']);
        $this->assertEquals('document', $documentDiff['type']);
        $this->assertEquals('deleted', $documentDiff['operation']);
        
        $this->assertArrayHasKey('title', $documentDiff['changed_fields']);
        $this->assertEquals('deleted', $documentDiff['changed_fields']['title']['status']);
        $this->assertNull($documentDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalDocument->title, $documentDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $documentDiff['changed_fields']);
        $this->assertEquals('deleted', $documentDiff['changed_fields']['description']['status']);
        $this->assertNull($documentDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalDocument->description, $documentDiff['changed_fields']['description']['original']);
    }

    #[Test]
    public function generateDiffData_returns_deleted_diff_when_deleting_parent_category_with_child_and_grandchild_categories(): void
    {
        // Arrange - 親カテゴリ
        $parentCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalParentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $parentCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalParentCategory->id,
            'current_version_id' => $originalParentCategory->id,
        ]);
        $deletedParentCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $parentCategoryEntity->id,
            'is_deleted' => 1,
        ]);
        $parentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalParentCategory->id,
            'current_version_id' => $deletedParentCategory->id,
        ]);

        // 子カテゴリ
        $childCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalChildCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $childCategoryEntity->id,
            'parent_entity_id' => $parentCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalChildCategory->id,
            'current_version_id' => $originalChildCategory->id,
        ]);
        $deletedChildCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $childCategoryEntity->id,
            'parent_entity_id' => $parentCategoryEntity->id,
            'is_deleted' => 1,
        ]);
        $childEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalChildCategory->id,
            'current_version_id' => $deletedChildCategory->id,
        ]);

        // 孫カテゴリ
        $grandchildCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $originalGrandchildCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->inactiveUserBranch->id,
            'status' => DocumentCategoryStatus::MERGED->value,
            'entity_id' => $grandchildCategoryEntity->id,
            'parent_entity_id' => $childCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->inactiveUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalGrandchildCategory->id,
            'current_version_id' => $originalGrandchildCategory->id,
        ]);
        $deletedGrandchildCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $grandchildCategoryEntity->id,
            'parent_entity_id' => $childCategoryEntity->id,
            'is_deleted' => 1,
        ]);
        $grandchildEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalGrandchildCategory->id,
            'current_version_id' => $deletedGrandchildCategory->id,
        ]);


        // Act
        $result = $this->service->generateDiffData(collect([
            $parentEditStartVersion,
            $childEditStartVersion,
            $grandchildEditStartVersion
        ]));

        // Assert
        $this->assertCount(3, $result['diff']);
        
        // 親カテゴリの削除検証
        $parentDiff = $result['diff'][0];
        $this->assertEquals($deletedParentCategory->id, $parentDiff['id']);
        $this->assertEquals('category', $parentDiff['type']);
        $this->assertEquals('deleted', $parentDiff['operation']);
        
        $this->assertArrayHasKey('title', $parentDiff['changed_fields']);
        $this->assertEquals('deleted', $parentDiff['changed_fields']['title']['status']);
        $this->assertNull($parentDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalParentCategory->title, $parentDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $parentDiff['changed_fields']);
        $this->assertEquals('deleted', $parentDiff['changed_fields']['description']['status']);
        $this->assertNull($parentDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalParentCategory->description, $parentDiff['changed_fields']['description']['original']);
        
        // 子カテゴリの削除検証
        $childDiff = $result['diff'][1];
        $this->assertEquals($deletedChildCategory->id, $childDiff['id']);
        $this->assertEquals('category', $childDiff['type']);
        $this->assertEquals('deleted', $childDiff['operation']);
        
        $this->assertArrayHasKey('title', $childDiff['changed_fields']);
        $this->assertEquals('deleted', $childDiff['changed_fields']['title']['status']);
        $this->assertNull($childDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalChildCategory->title, $childDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $childDiff['changed_fields']);
        $this->assertEquals('deleted', $childDiff['changed_fields']['description']['status']);
        $this->assertNull($childDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalChildCategory->description, $childDiff['changed_fields']['description']['original']);
        
        // 孫カテゴリの削除検証
        $grandchildDiff = $result['diff'][2];
        $this->assertEquals($deletedGrandchildCategory->id, $grandchildDiff['id']);
        $this->assertEquals('category', $grandchildDiff['type']);
        $this->assertEquals('deleted', $grandchildDiff['operation']);
        
        $this->assertArrayHasKey('title', $grandchildDiff['changed_fields']);
        $this->assertEquals('deleted', $grandchildDiff['changed_fields']['title']['status']);
        $this->assertNull($grandchildDiff['changed_fields']['title']['current']);
        $this->assertEquals($originalGrandchildCategory->title, $grandchildDiff['changed_fields']['title']['original']);
        
        $this->assertArrayHasKey('description', $grandchildDiff['changed_fields']);
        $this->assertEquals('deleted', $grandchildDiff['changed_fields']['description']['status']);
        $this->assertNull($grandchildDiff['changed_fields']['description']['current']);
        $this->assertEquals($originalGrandchildCategory->description, $grandchildDiff['changed_fields']['description']['original']);
    }
}

