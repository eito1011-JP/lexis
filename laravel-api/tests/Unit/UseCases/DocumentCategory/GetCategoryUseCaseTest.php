<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\DocumentCategoryService;
use App\UseCases\DocumentCategory\GetCategoryUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class GetCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private GetCategoryUseCase $useCase;

    private $documentCategoryService;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private DocumentCategoryEntity $categoryEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentCategoryService = Mockery::mock(DocumentCategoryService::class);
        $this->useCase = new GetCategoryUseCase($this->documentCategoryService);

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // 基本的なカテゴリエンティティを作成
        $this->categoryEntity = DocumentCategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @test
     */
    public function test_get_category_successfully_returns_category_with_breadcrumbs(): void
    {
        // Arrange
        $mockCategory = Mockery::mock(DocumentCategory::class);
        $mockCategory->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $mockCategory->shouldReceive('getAttribute')->with('entity_id')->andReturn($this->categoryEntity->id);
        $mockCategory->shouldReceive('getAttribute')->with('title')->andReturn('Test Category');
        $mockCategory->shouldReceive('getAttribute')->with('description')->andReturn('Test description');
        $mockCategory->shouldReceive('getBreadcrumbs')->andReturn(['Test Parent', 'Test Category']);

        $this->documentCategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->with($this->categoryEntity->id, $this->user, null)
            ->andReturn($mockCategory);

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals($this->categoryEntity->id, $result['entity_id']);
        $this->assertEquals('Test Category', $result['title']);
        $this->assertEquals('Test description', $result['description']);
        $this->assertEquals(['Test Parent', 'Test Category'], $result['breadcrumbs']);
    }

    /**
     * @test
     */
    public function test_get_category_throws_not_found_exception_when_category_entity_not_exists(): void
    {
        // Arrange
        $nonExistentCategoryEntityId = 99999;

        $dto = new GetCategoryDto([
            'category_entity_id' => $nonExistentCategoryEntityId,
            'user' => $this->user,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('カテゴリエンティティが見つかりません。');
        $this->useCase->execute($dto);
    }

    /**
     * @test
     */
    public function test_get_category_throws_not_found_exception_when_service_returns_null(): void
    {
        // Arrange
        $this->documentCategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->with($this->categoryEntity->id, $this->user, null)
            ->andReturn(null);

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('カテゴリが見つかりません。');
        $this->useCase->execute($dto);
    }

    /**
     * @test
     */
    public function test_get_category_with_pull_request_edit_session_token(): void
    {
        // Arrange
        $mockCategory = Mockery::mock(DocumentCategory::class);
        $mockCategory->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $mockCategory->shouldReceive('getAttribute')->with('entity_id')->andReturn($this->categoryEntity->id);
        $mockCategory->shouldReceive('getAttribute')->with('title')->andReturn('Pushed Category');
        $mockCategory->shouldReceive('getAttribute')->with('description')->andReturn('Pushed description');
        $mockCategory->shouldReceive('getBreadcrumbs')->andReturn(['Pushed Category']);

        $this->documentCategoryService
            ->shouldReceive('getCategoryByWorkContext')
            ->with($this->categoryEntity->id, $this->user, 'test-token')
            ->andReturn($mockCategory);

        $dto = new GetCategoryDto([
            'category_entity_id' => $this->categoryEntity->id,
            'user' => $this->user,
            'pull_request_edit_session_token' => 'test-token',
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['id']);
        $this->assertEquals($this->categoryEntity->id, $result['entity_id']);
        $this->assertEquals('Pushed Category', $result['title']);
        $this->assertEquals('Pushed description', $result['description']);
        $this->assertEquals(['Pushed Category'], $result['breadcrumbs']);
    }
}
