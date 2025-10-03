<?php

namespace Tests\Unit\UseCases\UserBranch;

use App\Models\User;
use App\Models\UserBranch;
use App\Models\Organization;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentDiffService = Mockery::mock(DocumentDiffService::class);
        $this->useCase = new FetchDiffUseCase($this->documentDiffService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function execute_returns_diff_data_with_created_operation(): void
    {
        // Arrange
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);
        $userBranch = Mockery::mock(UserBranch::class);

        $user->shouldReceive('userBranches')->andReturnSelf();
        $user->shouldReceive('active')->andReturnSelf();
        $user->shouldReceive('with')->andReturnSelf();
        $user->shouldReceive('first')->andReturn($userBranch);

        $userBranch->shouldReceive('getAttribute')->with('editStartVersions')->andReturn(new Collection());
        $userBranch->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $userBranch->shouldReceive('getAttribute')->with('organization')->andReturn($organization);
        $organization->shouldReceive('getAttribute')->with('id')->andReturn(999);

        $expectedDiffData = [
            'diff_data' => [
                [
                    'id' => 501,
                    'entity_id' => 3001,
                    'type' => 'document',
                    'operation' => 'created',
                    'snapshots' => [
                        'current' => [
                            'id' => 501,
                            'entity_id' => 3001,
                            'title' => '新規ドキュメント',
                            'description' => "## はじめに\nこのドキュメントは新規作成です。",
                            'status' => 'draft',
                            'category_entity_id' => 210,
                            'is_deleted' => 0,
                        ],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'added', 'current' => '新規ドキュメント', 'original' => null, 'meta' => ['display' => 'text']],
                        'description' => ['status' => 'added', 'current' => "## はじめに\nこのドキュメントは新規作成です。", 'original' => null, 'meta' => ['display' => 'markdown']],
                        'status' => ['status' => 'added', 'current' => 'draft', 'original' => null, 'meta' => ['display' => 'text']],
                        'category_entity_id' => ['status' => 'added', 'current' => 210, 'original' => null, 'meta' => ['display' => 'ref']],
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
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertEquals($expectedDiffData['diff_data'], $result['diff_data']);
        $this->assertEquals(123, $result['user_branch_id']);
        $this->assertEquals(999, $result['organization_id']);
    }

    #[Test]
    public function execute_returns_diff_data_with_updated_operation(): void
    {
        // Arrange
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);
        $userBranch = Mockery::mock(UserBranch::class);

        $user->shouldReceive('userBranches')->andReturnSelf();
        $user->shouldReceive('active')->andReturnSelf();
        $user->shouldReceive('with')->andReturnSelf();
        $user->shouldReceive('first')->andReturn($userBranch);

        $userBranch->shouldReceive('getAttribute')->with('editStartVersions')->andReturn(new Collection());
        $userBranch->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $userBranch->shouldReceive('getAttribute')->with('organization')->andReturn($organization);
        $organization->shouldReceive('getAttribute')->with('id')->andReturn(999);

        $expectedDiffData = [
            'diff_data' => [
                [
                    'id' => 202,
                    'entity_id' => 901,
                    'type' => 'document',
                    'operation' => 'updated',
                    'snapshots' => [
                        'current' => [
                            'id' => 202,
                            'entity_id' => 901,
                            'title' => '認証ガイド',
                            'description' => "## 認証\n更新後の本文...",
                            'status' => 'draft',
                            'category_entity_id' => 31,
                            'is_deleted' => 0,
                        ],
                        'original' => [
                            'id' => 198,
                            'entity_id' => 901,
                            'title' => 'APIガイド',
                            'description' => "## API\n更新前の本文...",
                            'status' => 'draft',
                            'category_entity_id' => 31,
                            'is_deleted' => 0,
                        ],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'modified', 'current' => '認証ガイド', 'original' => 'APIガイド', 'meta' => ['display' => 'text']],
                        'description' => ['status' => 'modified', 'current' => "## 認証\n更新後の本文...", 'original' => "## API\n更新前の本文...", 'meta' => ['display' => 'markdown']],
                        'status' => ['status' => 'unchanged', 'current' => 'draft', 'original' => 'draft', 'meta' => ['display' => 'text']],
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
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertEquals($expectedDiffData['diff_data'], $result['diff_data']);
        $this->assertEquals(123, $result['user_branch_id']);
        $this->assertEquals(999, $result['organization_id']);
    }

    #[Test]
    public function execute_returns_diff_data_with_deleted_operation(): void
    {
        // Arrange
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);
        $userBranch = Mockery::mock(UserBranch::class);

        $user->shouldReceive('userBranches')->andReturnSelf();
        $user->shouldReceive('active')->andReturnSelf();
        $user->shouldReceive('with')->andReturnSelf();
        $user->shouldReceive('first')->andReturn($userBranch);

        $userBranch->shouldReceive('getAttribute')->with('editStartVersions')->andReturn(new Collection());
        $userBranch->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $userBranch->shouldReceive('getAttribute')->with('organization')->andReturn($organization);
        $organization->shouldReceive('getAttribute')->with('id')->andReturn(999);

        $expectedDiffData = [
            'diff_data' => [
                [
                    'id' => 312,
                    'entity_id' => 702,
                    'type' => 'category',
                    'operation' => 'deleted',
                    'snapshots' => [
                        'current' => [
                            'id' => 312,
                            'entity_id' => 702,
                            'title' => 'Ops',
                            'description' => '運用',
                            'parent_entity_id' => 10,
                            'is_deleted' => 1,
                        ],
                        'original' => [
                            'id' => 240,
                            'entity_id' => 702,
                            'title' => 'Ops',
                            'description' => '運用',
                            'parent_entity_id' => 10,
                            'is_deleted' => 0,
                        ],
                    ],
                    'changed_fields' => [
                        'title' => ['status' => 'deleted', 'current' => null, 'original' => 'Ops', 'meta' => ['display' => 'text']],
                        'description' => ['status' => 'deleted', 'current' => null, 'original' => '運用', 'meta' => ['display' => 'markdown']],
                        'parent_entity_id' => ['status' => 'deleted', 'current' => null, 'original' => 10, 'meta' => ['display' => 'ref']],
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
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertEquals($expectedDiffData['diff_data'], $result['diff_data']);
        $this->assertEquals(123, $result['user_branch_id']);
        $this->assertEquals(999, $result['organization_id']);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_not_found(): void
    {
        // Arrange
        $user = Mockery::mock(User::class);

        $user->shouldReceive('userBranches')->andReturnSelf();
        $user->shouldReceive('active')->andReturnSelf();
        $user->shouldReceive('with')->andReturnSelf();
        $user->shouldReceive('first')->andReturn(null);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($user);
    }
}
