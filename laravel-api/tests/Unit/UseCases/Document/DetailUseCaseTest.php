<?php

namespace Tests\Unit\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\UseCases\Document\DetailUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DetailUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private DetailUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private DocumentVersion $document;

    private CategoryVersion $category;

    private UserBranch $userBranch;

    private CategoryEntity $categoryEntity;

    private DocumentEntity $documentEntity;

    private $CategoryService;

    private $documentVersionRepository;

    private $documentService;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスとリポジトリのモック作成
        $this->CategoryService = Mockery::mock(CategoryService::class);
        $this->documentVersionRepository = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $this->documentService = Mockery::mock(DocumentService::class);

        $this->useCase = new DetailUseCase(
            $this->CategoryService,
            $this->documentVersionRepository,
            $this->documentService  
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

        // categoryEntityの作成
        $this->categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentCategoryの作成
        $this->category = CategoryVersion::factory()->create([
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'title' => 'テストカテゴリ',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->category->id,
            'current_version_id' => $this->category->id,
        ]);

        $this->documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // DocumentVersionの作成
        $this->document = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'category_entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'category_entity_id' => $this->categoryEntity->id,
            'title' => 'テストドキュメント',
            'description' => 'テストドキュメントの説明',
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->document->id,
            'current_version_id' => $this->document->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_returns_document_with_category(): void
    {
        // Arrange
        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // ドキュメントをリレーションと共に読み込み
        $this->document->load('category');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->document);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($this->document->id, $result['id']);
        $this->assertEquals('テストドキュメント', $result['title']);
        $this->assertEquals('テストドキュメントの説明', $result['description']);
        $this->assertIsArray($result['breadcrumbs']);
        $this->assertCount(2, $result['breadcrumbs']); // カテゴリ + ドキュメント

        // パンくずリストの内容確認
        $this->assertEquals($this->category->id, $result['breadcrumbs'][0]['id']);
        $this->assertEquals('テストカテゴリ', $result['breadcrumbs'][0]['title']);
        $this->assertEquals($this->document->id, $result['breadcrumbs'][1]['id']);
        $this->assertEquals('テストドキュメント', $result['breadcrumbs'][1]['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_successfully_returns_document_with_category_having_no_breadcrumbs(): void
    {
        // Arrange
        // カテゴリがgetBreadcrumbsで空配列を返すようなケース（例：論理削除されたカテゴリ）
        $categoryWithoutBreadcrumbs = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'シンプルカテゴリ',
            'parent_entity_id' => null,
        ]);

        $documentWithSimpleCategory = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_entity_id' => $categoryWithoutBreadcrumbs->entity_id,
            'title' => 'シンプルドキュメント',
            'description' => 'シンプルドキュメントの説明',
        ]);

        $dto = new DetailDto(entityId: $documentWithSimpleCategory->entity_id);

        // ドキュメントをリレーションと共に読み込み
        $documentWithSimpleCategory->load('category');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($documentWithSimpleCategory->entity_id, $this->user)
            ->andReturn($documentWithSimpleCategory);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($documentWithSimpleCategory->id, $result['id']);
        $this->assertEquals('シンプルドキュメント', $result['title']);
        $this->assertEquals('シンプルドキュメントの説明', $result['description']);
        $this->assertIsArray($result['breadcrumbs']);
        $this->assertCount(2, $result['breadcrumbs']); // カテゴリ + ドキュメント
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_multilevel_category_hierarchy(): void
    {
        // Arrange
        // 3階層のカテゴリ構造を作成
        $rootCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $rootCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'ルートカテゴリ',
            'parent_entity_id' => null,
            'entity_id' => $rootCategoryEntity->id,
        ]);

        $rootCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $rootCategory->id,
            'current_version_id' => $rootCategory->id,
        ]);

        $childCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $childCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => '子カテゴリ',
            'parent_entity_id' => $rootCategoryEntity->id,
            'entity_id' => $childCategoryEntity->id,
        ]);

        $childCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $childCategory->id,
            'current_version_id' => $childCategory->id,
        ]);

        $grandchildCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $grandchildCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => '孫カテゴリ',
            'parent_entity_id' => $childCategoryEntity->id,
            'entity_id' => $grandchildCategoryEntity->id,
        ]);

        $grandchildCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $grandchildCategory->id,
            'current_version_id' => $grandchildCategory->id,
        ]);

        $documentWithHierarchyEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $documentWithHierarchy = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_entity_id' => $grandchildCategoryEntity->id,
            'entity_id' => $documentWithHierarchyEntity->id,
            'title' => '階層ドキュメント',
            'description' => '階層カテゴリのドキュメント',
        ]);

        $documentWithHierarchyEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentWithHierarchy->id,
            'current_version_id' => $documentWithHierarchy->id,
        ]);

        $dto = new DetailDto(entityId: $documentWithHierarchyEntity->id);

        // ドキュメントをリレーションと共に再読み込み
        // CategoryVersion::parentは parent_entity_id で CategoryVersion を参照している
        // しかし、親のentity_idと一致する最新のCategoryVersionを取得する必要がある
        $documentWithHierarchy = DocumentVersion::with([
            'category' => function ($query) {
                $query->with(['parent' => function ($q) {
                    $q->with(['parent']);
                }]);
            }
        ])->find($documentWithHierarchy->id);

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($documentWithHierarchyEntity->id, $this->user)
            ->andReturn($documentWithHierarchy);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($documentWithHierarchy->id, $result['id']);
        $this->assertEquals('階層ドキュメント', $result['title']);
        $this->assertEquals('階層カテゴリのドキュメント', $result['description']);
        $this->assertIsArray($result['breadcrumbs']);
        $this->assertCount(4, $result['breadcrumbs']); // ルート + 子 + 孫 + ドキュメント

        // パンくずリストの順序確認
        $this->assertEquals($rootCategory->id, $result['breadcrumbs'][0]['id']);
        $this->assertEquals('ルートカテゴリ', $result['breadcrumbs'][0]['title']);
        $this->assertEquals($childCategory->id, $result['breadcrumbs'][1]['id']);
        $this->assertEquals('子カテゴリ', $result['breadcrumbs'][1]['title']);
        $this->assertEquals($grandchildCategory->id, $result['breadcrumbs'][2]['id']);
        $this->assertEquals('孫カテゴリ', $result['breadcrumbs'][2]['title']);
        $this->assertEquals($documentWithHierarchy->id, $result['breadcrumbs'][3]['id']);
        $this->assertEquals('階層ドキュメント', $result['breadcrumbs'][3]['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_document_does_not_exist(): void
    {
        // Arrange
        $nonExistentId = 999999;
        $dto = new DetailDto(entityId: $nonExistentId);

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($nonExistentId, $this->user)
            ->andReturn(null);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_document_belongs_to_different_organization(): void
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        $anotherCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $anotherOrganization->id,
        ]);
        $anotherCategory = CategoryVersion::factory()->create([
            'organization_id' => $anotherOrganization->id,
            'title' => '他組織のカテゴリ',
            'entity_id' => $anotherCategoryEntity->id,
        ]);
        $anotherCategoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $anotherCategory->id,
            'current_version_id' => $anotherCategory->id,
        ]);
        $documentFromAnotherOrgEntity = DocumentEntity::factory()->create([
            'organization_id' => $anotherOrganization->id,
        ]);
        $documentFromAnotherOrg = DocumentVersion::factory()->create([
            'organization_id' => $anotherOrganization->id,
            'category_entity_id' => $anotherCategoryEntity->id,
            'entity_id' => $documentFromAnotherOrgEntity->id,
            'title' => '他組織のドキュメント',
            'description' => '他組織のドキュメント説明',
        ]);
        $documentFromAnotherOrgEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentFromAnotherOrg->id,
            'current_version_id' => $documentFromAnotherOrg->id,
        ]);

        $dto = new DetailDto(entityId: $documentFromAnotherOrgEntity->id);

        // documentServiceのモック設定（異なる組織なのでnullを返す）
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($documentFromAnotherOrgEntity->id, $this->user)
            ->andReturn(null);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        $userWithoutOrganization = User::factory()->create();
        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->useCase->execute($dto, $userWithoutOrganization);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_correctly_loads_category_with_parent_relationships(): void
    {
        // Arrange
        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // ドキュメントをリレーションと共に読み込み
        $this->document->load('category.parent');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->document);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);

        // データベースから再取得してrelationshipが正しく読み込まれていることを確認
        $loadedDocument = DocumentVersion::with(['category.parent'])->find($this->document->id);
        $this->assertNotNull($loadedDocument->category);
        $this->assertEquals($this->category->id, $loadedDocument->category->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_deleted_category(): void
    {
        // Arrange
        // カテゴリを論理削除
        $this->category->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        // ドキュメントも再読み込みして最新の関連データを取得
        $this->document->refresh();
        $this->document->load('category');

        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->document);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($this->document->id, $result['id']);
        // 削除されたカテゴリの場合、categoryはnullになり、パンくずリストにはドキュメントのみが含まれる
        $this->assertCount(1, $result['breadcrumbs']); // ドキュメントのみ
        $this->assertEquals($this->document->id, $result['breadcrumbs'][0]['id']);
        $this->assertEquals('テストドキュメント', $result['breadcrumbs'][0]['title']);
        $this->assertNull($result['category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_handles_seven_levels_category_hierarchy(): void
    {
        // Arrange
        // 7階層の深いカテゴリ構造を作成（UseCaseでの読み込み上限テスト）
        $categories = [];
        $parentEntityId = null;

        for ($i = 0; $i < 7; $i++) {
            $categoryEntity = CategoryEntity::factory()->create([
                'organization_id' => $this->organization->id,
            ]);
            $category = CategoryVersion::factory()->create([
                'entity_id' => $categoryEntity->id,
                'organization_id' => $this->organization->id,
                'title' => "レベル{$i}カテゴリ",
                'parent_entity_id' => $parentEntityId,
            ]);

            EditStartVersion::factory()->create([
                'user_branch_id' => $this->userBranch->id,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $category->id,
                'current_version_id' => $category->id,
            ]);

            $categories[] = $category;
            $parentEntityId = $categoryEntity->id;
        }

        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $deepDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_entity_id' => $categories[6]->entity_id,
            'entity_id' => $documentEntity->id,
            'title' => '深階層ドキュメント',
            'description' => '深い階層のドキュメント',
        ]);

        $deepDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $deepDocument->id,
            'current_version_id' => $deepDocument->id,
        ]);

        $dto = new DetailDto(entityId: $documentEntity->id);

        // ドキュメントをリレーションと共に再読み込み（7階層分の親をロード）
        $deepDocument = DocumentVersion::with([
            'category' => function ($query) {
                $query->with(['parent' => function ($q1) {
                    $q1->with(['parent' => function ($q2) {
                        $q2->with(['parent' => function ($q3) {
                            $q3->with(['parent' => function ($q4) {
                                $q4->with(['parent' => function ($q5) {
                                    $q5->with(['parent' => function ($q6) {
                                        $q6->with('parent');
                                    }]);
                                }]);
                            }]);
                        }]);
                    }]);
                }]);
            }
        ])->find($deepDocument->id);

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($documentEntity->id, $this->user)
            ->andReturn($deepDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($deepDocument->id, $result['id']);
        $this->assertEquals('深階層ドキュメント', $result['title']);
        $this->assertIsArray($result['breadcrumbs']);
        $this->assertCount(8, $result['breadcrumbs']); // 7階層 + ドキュメント

        // すべての階層が正しい順序で含まれていることを確認
        for ($i = 0; $i < 7; $i++) {
            $this->assertEquals($categories[$i]->id, $result['breadcrumbs'][$i]['id']);
            $this->assertEquals("レベル{$i}カテゴリ", $result['breadcrumbs'][$i]['title']);
        }
        $this->assertEquals($deepDocument->id, $result['breadcrumbs'][7]['id']);
        $this->assertEquals('深階層ドキュメント', $result['breadcrumbs'][7]['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_logs_error_and_rethrows_exception_on_database_error(): void
    {
        // Arrange
        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // DocumentServiceのモックを作成してデータベースエラーをシミュレート
        $documentServiceMock = Mockery::mock(DocumentService::class);
        $documentServiceMock->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->andThrow(new \Exception('Database connection error'));

        // UseCaseのインスタンスをモック化されたサービスで再作成
        $useCase = new DetailUseCase(
            $this->app->make(CategoryService::class),
            $this->app->make(DocumentVersionRepositoryInterface::class),
            $documentServiceMock
        );

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // データベースエラーが発生することを期待
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection error');

        // Act
        $useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_special_characters_in_title_and_description(): void
    {
        // Arrange
        $specialCharDocumentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $specialCharDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_entity_id' => $this->categoryEntity->id,
            'entity_id' => $specialCharDocumentEntity->id,
            'title' => '特殊文字<>&"\'テスト',
            'description' => 'HTML<tag>や&記号、"クォート"のテスト',
        ]);

        $specialCharDocumentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $specialCharDocument->id,
            'current_version_id' => $specialCharDocument->id,
        ]);

        $dto = new DetailDto(entityId: $specialCharDocumentEntity->id);

        // ドキュメントをリレーションと共に読み込み
        $specialCharDocument->load('category');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($specialCharDocumentEntity->id, $this->user)
            ->andReturn($specialCharDocument);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($specialCharDocument->id, $result['id']);
        $this->assertEquals('特殊文字<>&"\'テスト', $result['title']);
        $this->assertEquals('HTML<tag>や&記号、"クォート"のテスト', $result['description']);
    }


    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_empty_string_description(): void
    {
        // Arrange
        $documentWithEmptyDescriptionEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $documentWithEmptyDescription = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_entity_id' => $this->categoryEntity->id,
            'entity_id' => $documentWithEmptyDescriptionEntity->id,
            'title' => '空の説明ドキュメント',
            'description' => '',
        ]);

        $documentWithEmptyDescriptionEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentWithEmptyDescription->id,
            'current_version_id' => $documentWithEmptyDescription->id,
        ]);

        $dto = new DetailDto(entityId: $documentWithEmptyDescriptionEntity->id);

        // ドキュメントをリレーションと共に読み込み
        $documentWithEmptyDescription->load('category');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($documentWithEmptyDescriptionEntity->id, $this->user)
            ->andReturn($documentWithEmptyDescription);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($documentWithEmptyDescription->id, $result['id']);
        $this->assertEquals('空の説明ドキュメント', $result['title']);
        $this->assertEquals('', $result['description']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_verifies_organization_filtering(): void
    {
        // Arrange
        // 同じIDを持つが異なる組織のドキュメントを作成することはできないので、
        // 代わりに組織メンバーシップを変更してテスト
        $anotherOrganization = Organization::factory()->create();
        $anotherUser = User::factory()->create();

        OrganizationMember::create([
            'user_id' => $anotherUser->id,
            'organization_id' => $anotherOrganization->id,
            'joined_at' => now(),
        ]);

        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // documentServiceのモック設定（異なる組織なのでnullを返す）
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $anotherUser)
            ->andReturn(null);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $anotherUser);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_returns_correct_response_structure(): void
    {
        // Arrange
        $dto = new DetailDto(entityId: $this->documentEntity->id);

        // ドキュメントをリレーションと共に読み込み
        $this->document->load('category');

        // documentServiceのモック設定
        $this->documentService->shouldReceive('getDocumentByWorkContext')
            ->once()
            ->with($this->documentEntity->id, $this->user)
            ->andReturn($this->document);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('breadcrumbs', $result);

        // 型チェック
        $this->assertIsInt($result['id']);
        $this->assertIsString($result['title']);
        $this->assertTrue(is_string($result['description']) || is_null($result['description']));
        $this->assertIsArray($result['breadcrumbs']);

        // パンくずリストの構造チェック
        if (! empty($result['breadcrumbs'])) {
            foreach ($result['breadcrumbs'] as $breadcrumb) {
                $this->assertArrayHasKey('id', $breadcrumb);
                $this->assertArrayHasKey('title', $breadcrumb);
                $this->assertIsInt($breadcrumb['id']);
                $this->assertIsString($breadcrumb['title']);
            }
        }
    }
}
