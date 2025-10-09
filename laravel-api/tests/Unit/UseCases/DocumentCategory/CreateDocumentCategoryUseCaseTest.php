<?php

namespace Tests\Unit\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\CreateDocumentCategoryDto;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use App\UseCases\DocumentCategory\CreateDocumentCategoryUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CreateDocumentCategoryUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private CreateDocumentCategoryUseCase $useCase;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userBranchService = Mockery::mock(UserBranchService::class);

        $this->useCase = new CreateDocumentCategoryUseCase(
            $this->userBranchService
        );

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        // OrganizationMemberにはidカラムがないため、複合主キーで作成
        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_create_category_successfully(): void
    {
        // Arrange
        // 実際のUserBranchレコードを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($userBranch->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(CategoryVersion::class, $result);
        $this->assertEquals('Test Category', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($userBranch->id, $result->user_branch_id);
        $this->assertEquals(null, $result->parent_entity_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertNotNull($result->entity_id);

        // CategoryEntityが作成されていることを確認
        $this->assertDatabaseHas('category_entities', [
            'id' => $result->entity_id,
            'organization_id' => $this->organization->id,
        ]);

        // EditStartVersionが作成されていることを確認
        $this->assertDatabaseHas('edit_start_versions', [
            'user_branch_id' => $userBranch->id,
            'target_type' => 'category',
            'original_version_id' => $result->id,
            'current_version_id' => $result->id,
        ]);
    }

    /**
     * @test
     */
    public function test_create_category_with_parent_entity_id(): void
    {
        // Arrange
        // 実際のUserBranchレコードを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // 親カテゴリエンティティを作成
        $parentCategoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // 親カテゴリを実際に作成
        $parentCategory = CategoryVersion::factory()->create([
            'entity_id' => $parentCategoryEntity->id,
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: $parentCategoryEntity->id,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($userBranch->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(CategoryVersion::class, $result);
        $this->assertEquals('Test Category', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($userBranch->id, $result->user_branch_id);
        $this->assertEquals($parentCategoryEntity->id, $result->parent_entity_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertNotNull($result->entity_id);

        // CategoryEntityが作成されていることを確認
        $this->assertDatabaseHas('category_entities', [
            'id' => $result->entity_id,
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @test
     */
    public function test_create_category_throws_exception_when_organization_member_not_exists(): void
    {
        // Arrange
        // organizationMemberを削除して組織メンバーが存在しない状況を作る
        // OrganizationMemberはidがないので、条件で削除
        OrganizationMember::where('user_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->delete();

        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: null,
        );

        // Act & Assert
        $this->expectException(\ErrorException::class); // organizationMemberがnullの場合はErrorExceptionが発生
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_create_category_throws_exception_when_organization_not_found(): void
    {
        // Arrange
        // 組織を削除して組織が見つからない状況を作る
        $this->organization->delete();

        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: null,
        );

        // Act & Assert
        $this->expectException(\ErrorException::class); // organizationMemberがnullの場合はErrorExceptionが発生
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * @test
     */
    public function test_create_category_with_pull_request_id_specified(): void
    {
        // Arrange
        // PullRequestとUserBranchを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->with($this->user, $this->organization->id)
            ->andReturn($userBranch->id);

        // Act
        $result = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(CategoryVersion::class, $result);
        $this->assertEquals('Test Category', $result->title);
        $this->assertEquals('Test description', $result->description);
        $this->assertEquals($userBranch->id, $result->user_branch_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertNotNull($result->entity_id);

        // CategoryEntityが作成されていることを確認
        $this->assertDatabaseHas('category_entities', [
            'id' => $result->entity_id,
            'organization_id' => $this->organization->id,
        ]);
    }


    /**
     * @test
     */
    public function test_create_category_handles_exception(): void
    {
        // Arrange
        $dto = new CreateDocumentCategoryDto(
            title: 'Test Category',
            description: 'Test description',
            parentEntityId: null,
        );

        $this->userBranchService
            ->shouldReceive('fetchOrCreateActiveBranch')
            ->once()
            ->andThrow(new \Exception('Service error'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->useCase->execute($dto, $this->user);
    }
}
