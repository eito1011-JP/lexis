<?php

namespace Tests\Unit\UseCases\UserBranch;

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
use App\Services\DocumentDiffService;
use App\UseCases\UserBranch\FetchDiffUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchDiffUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface&DocumentDiffService */
    private DocumentDiffService $documentDiffService;

    private FetchDiffUseCase $useCase;

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
        $this->documentDiffService = Mockery::mock(DocumentDiffService::class);
        $this->useCase = new FetchDiffUseCase($this->documentDiffService);

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
    public function execute_returns_diff_data_with_created_category_operation(): void
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $category->id,
            'current_version_id' => $category->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [
                            $category,
                        ],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $category->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $category->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->with(Mockery::type(Collection::class))
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals($expectedDiffData['diff'], $result['diff']);
        $this->assertEquals($this->activeUserBranch->id, $result['user_branch_id']);
        $this->assertEquals($this->organization->id, $result['organization_id']);
    }


    #[Test]
    public function execute_returns_diff_data_with_created_document_and_category_operation(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [
                            $parentCategory,
                        ],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $parentCategory->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $parentCategory->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [
                            $document,
                        ],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $document->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $document->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->with(Mockery::type(Collection::class))
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals($expectedDiffData['diff'], $result['diff']);
        $this->assertEquals($this->activeUserBranch->id, $result['user_branch_id']);
        $this->assertEquals($this->organization->id, $result['organization_id']);
    }

    #[Test]
    public function execute_returns_diff_data_with_created_document_operation(): void
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$document],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $document->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $document->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->with(Mockery::type(Collection::class))
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals($expectedDiffData['diff'], $result['diff']);
        $this->assertEquals($this->activeUserBranch->id, $result['user_branch_id']);
    }

    #[Test]
    public function execute_returns_diff_data_with_multiple_created_categories_and_documents(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $document2->id,
            'current_version_id' => $document2->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$category1],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $category1->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $category1->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$category2],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $category2->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $category2->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$document1],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $document1->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $document1->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$document2],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $document2->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $document2->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(4, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('created', $result['diff'][1]['operation']);
        $this->assertEquals('created', $result['diff'][2]['operation']);
        $this->assertEquals('created', $result['diff'][3]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_merged_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $currentCategory->title, 'original' => $originalCategory->title],
                        'description' => ['status' => 'modified', 'current' => $currentCategory->description, 'original' => $originalCategory->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_pushed_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $currentCategory->title, 'original' => $originalCategory->title],
                        'description' => ['status' => 'modified', 'current' => $currentCategory->description, 'original' => $originalCategory->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_created_diff_when_editing_draft_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $currentCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $currentCategory->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $currentCategory->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('created', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_merged_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $currentDocument->title, 'original' => $originalDocument->title],
                        'description' => ['status' => 'modified', 'current' => $currentDocument->description, 'original' => $originalDocument->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_pushed_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $currentDocument->title, 'original' => $originalDocument->title],
                        'description' => ['status' => 'modified', 'current' => $currentDocument->description, 'original' => $originalDocument->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_created_diff_when_editing_draft_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $currentDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $currentDocument->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $currentDocument->description, 'original' => null],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('created', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_merged_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_pushed_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_empty_diff_when_deleting_draft_category(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $currentCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = ['diff' => []];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEmpty($result['diff']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_merged_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->title],
                        'description' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_pushed_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->title],
                        'description' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user,  $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_empty_diff_when_deleting_draft_document(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = ['diff' => []];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEmpty($result['diff']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_creating_and_updating_categories(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalMergedCategory->id,
            'current_version_id' => $updatedCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$draftCategory],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $draftCategory->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $draftCategory->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedCategory],
                        'original' => [$originalMergedCategory],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedCategory->title, 'original' => $originalMergedCategory->title],
                        'description' => ['status' => 'modified', 'current' => $updatedCategory->description, 'original' => $originalMergedCategory->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(2, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('updated', $result['diff'][1]['operation']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_updating_and_deleting_categories(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedCategory],
                        'original' => [$originalUpdateCategory],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedCategory->title, 'original' => $originalUpdateCategory->title],
                        'description' => ['status' => 'modified', 'current' => $updatedCategory->description, 'original' => $originalUpdateCategory->description],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedCategory],
                        'original' => [$originalDeleteCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(2, $result['diff']);
        $this->assertEquals('updated', $result['diff'][0]['operation']);
        $this->assertEquals('deleted', $result['diff'][1]['operation']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_creating_updating_and_deleting_categories(): void
    {
        // Arrange - ドラフトカテゴリを作成
        $draftCategoryEntity = CategoryEntity::factory()->create(['organization_id' => $this->organization->id]);
        $draftCategory = CategoryVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'user_branch_id' => $this->activeUserBranch->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'entity_id' => $draftCategoryEntity->id,
        ]);
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalDeleteCategory->id,
            'current_version_id' => $deletedCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$draftCategory],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $draftCategory->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $draftCategory->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedCategory],
                        'original' => [$originalUpdateCategory],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedCategory->title, 'original' => $originalUpdateCategory->title],
                        'description' => ['status' => 'modified', 'current' => $updatedCategory->description, 'original' => $originalUpdateCategory->description],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedCategory],
                        'original' => [$originalDeleteCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(3, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('updated', $result['diff'][1]['operation']);
        $this->assertEquals('deleted', $result['diff'][2]['operation']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_creating_and_updating_documents(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalMergedDocument->id,
            'current_version_id' => $updatedDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$draftDocument],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $draftDocument->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $draftDocument->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedDocument],
                        'original' => [$originalMergedDocument],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedDocument->title, 'original' => $originalMergedDocument->title],
                        'description' => ['status' => 'modified', 'current' => $updatedDocument->description, 'original' => $originalMergedDocument->description],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(2, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('updated', $result['diff'][1]['operation']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_updating_and_deleting_documents(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedDocument],
                        'original' => [$originalUpdateDocument],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedDocument->title, 'original' => $originalUpdateDocument->title],
                        'description' => ['status' => 'modified', 'current' => $updatedDocument->description, 'original' => $originalUpdateDocument->description],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedDocument],
                        'original' => [$originalDeleteDocument],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(2, $result['diff']);
        $this->assertEquals('updated', $result['diff'][0]['operation']);
        $this->assertEquals('deleted', $result['diff'][1]['operation']);
    }

    #[Test]
    public function execute_returns_mixed_diff_when_creating_updating_and_deleting_documents(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDeleteDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [$draftDocument],
                        'original' => [],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => $draftDocument->title, 'original' => null],
                        'description' => ['status' => 'added', 'current' => $draftDocument->description, 'original' => null],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$updatedDocument],
                        'original' => [$originalUpdateDocument],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => $updatedDocument->title, 'original' => $originalUpdateDocument->title],
                        'description' => ['status' => 'modified', 'current' => $updatedDocument->description, 'original' => $originalUpdateDocument->description],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedDocument],
                        'original' => [$originalDeleteDocument],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(3, $result['diff']);
        $this->assertEquals('created', $result['diff'][0]['operation']);
        $this->assertEquals('updated', $result['diff'][1]['operation']);
        $this->assertEquals('deleted', $result['diff'][2]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_same_category_entity_twice(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
        $this->assertEquals('2回目の編集', $result['diff'][0]['snapshots']['current'][0]->title);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_editing_then_deleting_same_category_entity(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $currentCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$currentCategory],
                        'original' => [$originalCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_updated_diff_when_editing_same_document_entity_twice(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('updated', $result['diff'][0]['operation']);
        $this->assertEquals('2回目の編集', $result['diff'][0]['snapshots']['current'][0]->title);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_editing_then_deleting_same_document_entity(): void
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

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => $currentDocument,
                        'original' => $originalDocument,
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_parent_category_with_child_document(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $deletedDocument->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedParentCategory],
                        'original' => [$originalParentCategory],
                    ],
                ],
                [
                    'type' => 'document',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedDocument],
                        'original' => $originalDocument,
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(2, $result['diff']);
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
        $this->assertEquals('deleted', $result['diff'][1]['operation']);
    }

    #[Test]
    public function execute_returns_deleted_diff_when_deleting_parent_category_with_child_and_grandchild_categories(): void
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
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
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalGrandchildCategory->id,
            'current_version_id' => $deletedGrandchildCategory->id,
        ]);

        $expectedDiffData = [
            'diff' => [
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedParentCategory],
                        'original' => [$originalParentCategory],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedChildCategory],
                        'original' => [$originalChildCategory],
                    ],
                ],
                [
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [$deletedGrandchildCategory],
                        'original' => [$originalGrandchildCategory],
                    ],
                ],
            ],
        ];

        $this->documentDiffService
            ->shouldReceive('generateDiffData')
            ->once()
            ->andReturn($expectedDiffData);

        // Act
        $result = $this->useCase->execute($this->user, $this->activeUserBranch->id);

        // Assert
        $this->assertCount(3, $result['diff']);
        $this->assertEquals('deleted', $result['diff'][0]['operation']);
        $this->assertEquals('deleted', $result['diff'][1]['operation']);
        $this->assertEquals('deleted', $result['diff'][2]['operation']);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_active_user_branch_does_not_exist(): void
    {
        // Arrange - アクティブなブランチを削除
        $this->activeUserBranch->update(['is_active' => false]);

        // Assert
        $this->expectException(NotFoundException::class);

        // Act
        $this->useCase->execute($this->user, $this->activeUserBranch->id);
    }
}
