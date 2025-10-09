<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\OrganizationService;
use App\Services\UserBranchService;
use App\UseCases\PullRequest\CreatePullRequestUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CreatePullRequestUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private CreatePullRequestUseCase $useCase;

    /** @var \Mockery\MockInterface&OrganizationService */
    private OrganizationService $organizationService;

    /** @var \Mockery\MockInterface&UserBranchService */
    private UserBranchService $userBranchService;

    private User $user;

    private Organization $organization;

    private UserBranch $activeUserBranch;

    protected function setUp(): void
    {
        parent::setUp();

        // モックの作成
        $this->organizationService = Mockery::mock(OrganizationService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        // UseCaseのインスタンス化
        $this->useCase = new CreatePullRequestUseCase(
            $this->organizationService,
            $this->userBranchService
        );

        // テストデータのセットアップ
        $this->setupTestData();
    }

    /**
     * テストデータのセットアップ
     */
    private function setupTestData(): void
    {
        // 組織の作成
        $this->organization = Organization::factory()->create();

        // ユーザーの作成
        $this->user = User::factory()->create();

        // 組織メンバーの作成
        OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // アクティブなユーザーブランチの作成
        $this->activeUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'branch_name' => 'test-branch',
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);
    }

    /**
     * 正常系：レビュアーなしでプルリクエストを作成できる
     */
    public function test_successfully_create_pull_request_without_reviewers(): void
    {
        // Arrange
        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: []
        );

        // documentVersionsを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'user_id' => $this->user->id,
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentVersion->id,
            'current_version_id' => $documentVersion->id,
        ]);

        // categoryVersionsを作成
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $categoryVersion = CategoryVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $categoryVersion->id,
            'current_version_id' => $categoryVersion->id,
        ]);

        // モックの設定
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id);

        $this->userBranchService
            ->shouldReceive('findActiveUserBranch')
            ->once()
            ->with($this->activeUserBranch->id, $this->organization->id, $this->user->id)
            ->andReturn($this->activeUserBranch);

        $this->userBranchService
            ->shouldReceive('deleteUserBranchSessions')
            ->once()
            ->with($this->activeUserBranch, $this->user->id);

        // Act
        $pullRequest = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(PullRequest::class, $pullRequest);
        $this->assertEquals($this->activeUserBranch->id, $pullRequest->user_branch_id);
        $this->assertEquals($this->organization->id, $pullRequest->organization_id);
        $this->assertEquals('Test Pull Request', $pullRequest->title);
        $this->assertEquals('Test Description', $pullRequest->description);
        $this->assertEquals(PullRequestStatus::OPENED->value, $pullRequest->status);

        // document_versionsのステータスが更新されているか確認
        $documentVersion->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $documentVersion->status);

        // category_versionsのステータスが更新されているか確認
        $categoryVersion->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $categoryVersion->status);

        // レビュアーが作成されていないことを確認
        $reviewers = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->get();
        $this->assertCount(0, $reviewers);
    }

    /**
     * 正常系：レビュアーありでプルリクエストを作成できる
     */
    public function test_successfully_create_pull_request_with_reviewers(): void
    {
        // Arrange
        $reviewer1 = User::factory()->create(['email' => 'reviewer1@example.com']);
        $reviewer2 = User::factory()->create(['email' => 'reviewer2@example.com']);

        OrganizationMember::create([
            'user_id' => $reviewer1->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $reviewer2->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: ['reviewer1@example.com', 'reviewer2@example.com']
        );

        // documentVersionsを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'user_id' => $this->user->id,
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentVersion->id,
            'current_version_id' => $documentVersion->id,
        ]);

        // categoryVersionsを作成
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $categoryVersion = CategoryVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $categoryVersion->id,
            'current_version_id' => $categoryVersion->id,
        ]);

        // モックの設定
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id);

        $this->userBranchService
            ->shouldReceive('findActiveUserBranch')
            ->once()
            ->with($this->activeUserBranch->id, $this->organization->id, $this->user->id)
            ->andReturn($this->activeUserBranch);

        $this->userBranchService
            ->shouldReceive('deleteUserBranchSessions')
            ->once()
            ->with($this->activeUserBranch, $this->user->id);

        // Act
        $pullRequest = $this->useCase->execute($dto, $this->user);

        // Assert
        $this->assertInstanceOf(PullRequest::class, $pullRequest);

        // レビュアーが作成されているか確認
        $reviewers = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->get();
        $this->assertCount(2, $reviewers);

        $reviewerUserIds = $reviewers->pluck('user_id')->toArray();
        $this->assertContains($reviewer1->id, $reviewerUserIds);
        $this->assertContains($reviewer2->id, $reviewerUserIds);
    }

    /**
     * 異常系：ユーザーが組織に所属していない場合、例外がスローされる
     */
    public function test_throws_exception_when_user_does_not_belong_to_organization(): void
    {
        // Arrange
        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: []
        );

        // モックの設定：organizationServiceが例外をスロー
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id)
            ->andThrow(new NotFoundException());

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * 異常系：user_branchがactiveでない場合、例外がスローされる
     */
    public function test_throws_exception_when_user_branch_is_not_active(): void
    {
        // Arrange
        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: []
        );

        // モックの設定
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id);

        $this->userBranchService
            ->shouldReceive('findActiveUserBranch')
            ->once()
            ->with($this->activeUserBranch->id, $this->organization->id, $this->user->id)
            ->andThrow(new NotFoundException());

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * 異常系：レビュアーのメールアドレスが存在しない場合、例外がスローされる
     */
    public function test_throws_exception_when_reviewer_email_does_not_exist(): void
    {
        // Arrange
        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: ['nonexistent@example.com']
        );

        // documentVersionsを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'user_id' => $this->user->id,
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentVersion->id,
            'current_version_id' => $documentVersion->id,
        ]);

        // categoryVersionsを作成
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $categoryVersion = CategoryVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $categoryVersion->id,
            'current_version_id' => $categoryVersion->id,
        ]);

        // モックの設定
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id);

        $this->userBranchService
            ->shouldReceive('findActiveUserBranch')
            ->once()
            ->with($this->activeUserBranch->id, $this->organization->id, $this->user->id)
            ->andReturn($this->activeUserBranch);

        $this->userBranchService
            ->shouldReceive('deleteUserBranchSessions')
            ->once()
            ->with($this->activeUserBranch, $this->user->id);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * 異常系：レビュアーの一部が存在しない場合、例外がスローされる
     */
    public function test_throws_exception_when_some_reviewer_emails_do_not_exist(): void
    {
        // Arrange
        $reviewer1 = User::factory()->create(['email' => 'reviewer1@example.com']);

        OrganizationMember::create([
            'user_id' => $reviewer1->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $dto = new CreatePullRequestDto(
            userBranchId: $this->activeUserBranch->id,
            organizationId: $this->organization->id,
            title: 'Test Pull Request',
            description: 'Test Description',
            reviewers: ['reviewer1@example.com', 'nonexistent@example.com']
        );

        // documentVersionsを作成
        $documentEntity = DocumentEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'user_id' => $this->user->id,
            'entity_id' => $documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $documentVersion->id,
            'current_version_id' => $documentVersion->id,
        ]);

        // categoryVersionsを作成
        $categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $categoryVersion = CategoryVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'entity_id' => $categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);
        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $categoryVersion->id,
            'current_version_id' => $categoryVersion->id,
        ]);

        // モックの設定
        $this->organizationService
            ->shouldReceive('validateUserBelongsToOrganization')
            ->once()
            ->with($this->user->id, $this->organization->id);

        $this->userBranchService
            ->shouldReceive('findActiveUserBranch')
            ->once()
            ->with($this->activeUserBranch->id, $this->organization->id, $this->user->id)
            ->andReturn($this->activeUserBranch);

        $this->userBranchService
            ->shouldReceive('deleteUserBranchSessions')
            ->once()
            ->with($this->activeUserBranch, $this->user->id);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
