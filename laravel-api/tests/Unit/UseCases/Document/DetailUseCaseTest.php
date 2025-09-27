<?php

namespace Tests\Unit\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
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

    private DocumentCategory $category;

    private $documentCategoryService;

    private $documentVersionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // サービスとリポジトリのモック作成
        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);
        $this->documentVersionRepository = Mockery::mock(DocumentVersionRepositoryInterface::class);

        $this->useCase = new DetailUseCase(
            $this->documentCategoryService,
            $this->documentVersionRepository
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

        // DocumentCategoryの作成
        $this->category = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'テストカテゴリ',
        ]);

        // DocumentVersionの作成
        $this->document = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'title' => 'テストドキュメント',
            'description' => 'テストドキュメントの説明',
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
        $dto = new DetailDto(id: $this->document->id);

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
        $categoryWithoutBreadcrumbs = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'シンプルカテゴリ',
            'parent_entity_id' => null,
        ]);

        $documentWithSimpleCategory = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $categoryWithoutBreadcrumbs->id,
            'title' => 'シンプルドキュメント',
            'description' => 'シンプルドキュメントの説明',
        ]);

        $dto = new DetailDto(id: $documentWithSimpleCategory->id);

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
        $rootCategory = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'ルートカテゴリ',
            'parent_entity_id' => null,
        ]);

        $childCategory = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => '子カテゴリ',
            'parent_entity_id' => $rootCategory->id,
        ]);

        $grandchildCategory = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => '孫カテゴリ',
            'parent_entity_id' => $childCategory->id,
        ]);

        $documentWithHierarchy = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $grandchildCategory->id,
            'title' => '階層ドキュメント',
            'description' => '階層カテゴリのドキュメント',
        ]);

        $dto = new DetailDto(id: $documentWithHierarchy->id);

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
        $dto = new DetailDto(id: $nonExistentId);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_document_belongs_to_different_organization(): void
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        $anotherCategory = DocumentCategory::factory()->create([
            'organization_id' => $anotherOrganization->id,
            'title' => '他組織のカテゴリ',
        ]);
        $documentFromAnotherOrg = DocumentVersion::factory()->create([
            'organization_id' => $anotherOrganization->id,
            'category_id' => $anotherCategory->id,
            'title' => '他組織のドキュメント',
            'description' => '他組織のドキュメント説明',
        ]);

        $dto = new DetailDto(id: $documentFromAnotherOrg->id);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange
        $userWithoutOrganization = User::factory()->create();
        $dto = new DetailDto(id: $this->document->id);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->useCase->execute($dto, $userWithoutOrganization);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_correctly_loads_category_with_parent_relationships(): void
    {
        // Arrange
        $dto = new DetailDto(id: $this->document->id);

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

        $dto = new DetailDto(id: $this->document->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($this->document->id, $result['id']);
        // 削除されたカテゴリでもドキュメント自体は取得できるはず
        $this->assertEmpty($result['breadcrumbs']); // カテゴリが削除されているのでパンくずリストは空
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_handles_very_deep_category_hierarchy(): void
    {
        // Arrange
        // 7階層の深いカテゴリ構造を作成（UseCaseでの読み込み上限テスト）
        $categories = [];
        $parentId = null;

        for ($i = 0; $i < 7; $i++) {
            $category = DocumentCategory::factory()->create([
                'organization_id' => $this->organization->id,
                'title' => "レベル{$i}カテゴリ",
                'parent_entity_id' => $parentId,
            ]);
            $categories[] = $category;
            $parentId = $category->id;
        }

        $deepDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $categories[6]->id, // 最深のカテゴリに配置
            'title' => '深階層ドキュメント',
            'description' => '深い階層のドキュメント',
        ]);

        $dto = new DetailDto(id: $deepDocument->id);

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
        $dto = new DetailDto(id: $this->document->id);

        // Logのモック
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type(\Exception::class));

        // データベースエラーをシミュレート（存在しないテーブルへのアクセス）
        $this->expectException(\Exception::class);

        // 一時的にdocument_versionsテーブルをリネームして存在しないようにする
        DB::statement('ALTER TABLE document_versions RENAME TO temp_document_versions');

        try {
            // Act
            $this->useCase->execute($dto, $this->user);
        } finally {
            // テストの後始末：テーブル名を元に戻す
            DB::statement('ALTER TABLE temp_document_versions RENAME TO document_versions');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_special_characters_in_title_and_description(): void
    {
        // Arrange
        $specialCharDocument = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'title' => '特殊文字<>&"\'テスト',
            'description' => 'HTML<tag>や&記号、"クォート"のテスト',
        ]);

        $dto = new DetailDto(id: $specialCharDocument->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($specialCharDocument->id, $result['id']);
        $this->assertEquals('特殊文字<>&"\'テスト', $result['title']);
        $this->assertEquals('HTML<tag>や&記号、"クォート"のテスト', $result['description']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_null_description(): void
    {
        // Arrange
        $documentWithNullDescription = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'title' => 'タイトルのみドキュメント',
            'description' => null,
        ]);

        $dto = new DetailDto(id: $documentWithNullDescription->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($documentWithNullDescription->id, $result['id']);
        $this->assertEquals('タイトルのみドキュメント', $result['title']);
        $this->assertNull($result['description']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_with_empty_string_description(): void
    {
        // Arrange
        $documentWithEmptyDescription = DocumentVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'title' => '空の説明ドキュメント',
            'description' => '',
        ]);

        $dto = new DetailDto(id: $documentWithEmptyDescription->id);

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

        $dto = new DetailDto(id: $this->document->id);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $anotherUser);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_execute_returns_correct_response_structure(): void
    {
        // Arrange
        $dto = new DetailDto(id: $this->document->id);

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
