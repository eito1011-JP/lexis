<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Models\DocumentCategory;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\UseCases\DocumentCategory\GetCategoryUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GetCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private GetCategoryUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new GetCategoryUseCase;

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);
    }

    /**
     * @test
     */
    public function test_get_category_successfully(): void
    {
        // Arrange
        $category = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Test Category',
            'description' => 'Test description',
        ]);

        $dto = new GetCategoryDto([
            'id' => $category->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($category->id, $result['id']);
        $this->assertEquals('Test Category', $result['title']);
        $this->assertEquals('Test description', $result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_throws_not_found_exception_when_category_not_exists(): void
    {
        // Arrange
        $nonExistentCategoryId = 99999;

        $dto = new GetCategoryDto([
            'id' => $nonExistentCategoryId,
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
    public function test_get_category_throws_not_found_exception_when_category_belongs_to_different_organization(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();
        $category = DocumentCategory::factory()->create([
            'organization_id' => $otherOrganization->id,
            'title' => 'Other Organization Category',
            'description' => 'Category from different organization',
        ]);

        $dto = new GetCategoryDto([
            'id' => $category->id,
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
    public function test_get_category_with_null_description(): void
    {
        // Arrange
        $category = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Category Without Description',
            'description' => null,
        ]);

        $dto = new GetCategoryDto([
            'id' => $category->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($category->id, $result['id']);
        $this->assertEquals('Category Without Description', $result['title']);
        $this->assertNull($result['description']);
    }

    /**
     * @test
     */
    public function test_get_category_with_empty_description(): void
    {
        // Arrange
        $category = DocumentCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Category With Empty Description',
            'description' => '',
        ]);

        $dto = new GetCategoryDto([
            'id' => $category->id,
            'user' => $this->user,
        ]);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($category->id, $result['id']);
        $this->assertEquals('Category With Empty Description', $result['title']);
        $this->assertEquals('', $result['description']);
    }
}
