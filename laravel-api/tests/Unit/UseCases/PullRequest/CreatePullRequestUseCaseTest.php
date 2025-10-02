<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\PullRequestStatus;
use App\Models\CategoryVersion;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\OrganizationService;
use App\Services\UserBranchService;
use App\UseCases\PullRequest\CreatePullRequestUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreatePullRequestUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private CreatePullRequestUseCase $useCase;

    private MockInterface $organizationService;

    private MockInterface $userBranchService;

    private User $user;

    private User $reviewerUser;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $userBranch;

    private DocumentVersion $documentVersion;

    private CategoryVersion $documentCategory;

    protected function setUp(): void
    {
        parent::setUp();

        // モックの作成
        $this->organizationService = Mockery::mock(OrganizationService::class);
        $this->userBranchService = Mockery::mock(UserBranchService::class);

        // サービスコンテナにモックをバインド
        $this->app->instance(OrganizationService::class, $this->organizationService);
        $this->app->instance(UserBranchService::class, $this->userBranchService);

        // UseCaseの初期化
        $this->useCase = $this->app->make(CreatePullRequestUseCase::class);

        // テストデータの作成
        $this->setupTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * テストデータのセットアップ
     */
    private function setupTestData(): void
    {
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();
        $this->reviewerUser = User::factory()->create(['email' => 'reviewer_'.time().'@example.com']);

        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'branch_name' => 'test-branch',
        ]);

        $this->documentCategory = CategoryVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::DRAFT->value,
        ]);

        $this->documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->user->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
    }

    // /**
    //  * 正常にプルリクエストが作成される場合のテスト（レビュアーあり）
    //  */
    // public function test_execute_success_with_reviewers(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         description: 'Test Description',
    //         reviewers: [$this->reviewerUser->email]
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act
    //     $result = $this->useCase->execute($dto, $this->user);

    //     // Assert
    //     $this->assertArrayHasKey('pull_request_id', $result);

    //     // プルリクエストが作成されているか確認
    //     $pullRequest = PullRequest::find($result['pull_request_id']);
    //     $this->assertNotNull($pullRequest);
    //     $this->assertEquals($this->userBranch->id, $pullRequest->user_branch_id);
    //     $this->assertEquals($this->organization->id, $pullRequest->organization_id);
    //     $this->assertEquals('Test Pull Request', $pullRequest->title);
    //     $this->assertEquals('Test Description', $pullRequest->description);
    //     $this->assertEquals(PullRequestStatus::OPENED->value, $pullRequest->status);

    //     // レビュアーが作成されているか確認
    //     $reviewer = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->first();
    //     $this->assertNotNull($reviewer);
    //     $this->assertEquals($this->reviewerUser->id, $reviewer->user_id);

    //     // DocumentVersionのステータスが更新されているか確認
    //     $this->documentVersion->refresh();
    //     $this->assertEquals(DocumentStatus::PUSHED->value, $this->documentVersion->status);

    //     // DocumentCategoryのステータスが更新されているか確認
    //     $this->documentCategory->refresh();
    //     $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $this->documentCategory->status);
    // }

    // /**
    //  * 正常にプルリクエストが作成される場合のテスト（レビュアーなし）
    //  */
    // public function test_execute_success_without_reviewers(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         description: 'Test Description',
    //         reviewers: null
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act
    //     $result = $this->useCase->execute($dto, $this->user);

    //     // Assert
    //     $this->assertArrayHasKey('pull_request_id', $result);

    //     // プルリクエストが作成されているか確認
    //     $pullRequest = PullRequest::find($result['pull_request_id']);
    //     $this->assertNotNull($pullRequest);

    //     // レビュアーが作成されていないことを確認
    //     $reviewerCount = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->count();
    //     $this->assertEquals(0, $reviewerCount);
    // }

    // /**
    //  * 正常にプルリクエストが作成される場合のテスト（レビュアー空配列）
    //  */
    // public function test_execute_success_with_empty_reviewers(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         description: 'Test Description',
    //         reviewers: []
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act
    //     $result = $this->useCase->execute($dto, $this->user);

    //     // Assert
    //     $this->assertArrayHasKey('pull_request_id', $result);

    //     // レビュアーが作成されていないことを確認
    //     $pullRequest = PullRequest::find($result['pull_request_id']);
    //     $reviewerCount = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->count();
    //     $this->assertEquals(0, $reviewerCount);
    // }

    // /**
    //  * ユーザーが組織に所属していない場合のテスト
    //  */
    // public function test_execute_fails_when_user_not_belongs_to_organization(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request'
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id)
    //         ->andThrow(new NotFoundException);

    //     // Act & Assert
    //     $this->expectException(NotFoundException::class);
    //     $this->useCase->execute($dto, $this->user);

    //     // トランザクションがロールバックされていることを確認
    //     $pullRequestCount = PullRequest::count();
    //     $this->assertEquals(0, $pullRequestCount);
    // }

    // /**
    //  * ユーザーブランチが見つからない場合のテスト
    //  */
    // public function test_execute_fails_when_user_branch_not_found(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request'
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andThrow(new NotFoundException);

    //     // Act & Assert
    //     $this->expectException(NotFoundException::class);
    //     $this->useCase->execute($dto, $this->user);

    //     // トランザクションがロールバックされていることを確認
    //     $pullRequestCount = PullRequest::count();
    //     $this->assertEquals(0, $pullRequestCount);
    // }

    // /**
    //  * 一部のレビュアーが見つからない場合のテスト
    //  */
    // public function test_execute_fails_when_some_reviewers_not_found(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         reviewers: [$this->reviewerUser->email, 'notfound@example.com']
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     // レビュアー検証は deactivateUserBranch の後に実行されるため、例外が発生してもメソッドは呼ばれる
    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act & Assert
    //     $this->expectException(NotFoundException::class);
    //     $this->useCase->execute($dto, $this->user);

    //     // トランザクションがロールバックされていることを確認
    //     $pullRequestCount = PullRequest::count();
    //     $this->assertEquals(0, $pullRequestCount);
    // }

    // /**
    //  * ユーザーブランチの無効化に失敗した場合のテスト
    //  */
    // public function test_execute_fails_when_deactivate_user_branch_fails(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request'
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andThrow(new NotFoundException);

    //     // Act & Assert
    //     $this->expectException(NotFoundException::class);
    //     $this->useCase->execute($dto, $this->user);

    //     // トランザクションがロールバックされていることを確認
    //     $pullRequestCount = PullRequest::count();
    //     $this->assertEquals(0, $pullRequestCount);
    // }

    // /**
    //  * 複数のレビュアーが正しく処理される場合のテスト
    //  */
    // public function test_execute_success_with_multiple_reviewers(): void
    // {
    //     // Arrange
    //     $reviewer2 = User::factory()->create(['email' => 'reviewer2_'.time().'@example.com']);
    //     $reviewer3 = User::factory()->create(['email' => 'reviewer3_'.time().'@example.com']);

    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         reviewers: [$this->reviewerUser->email, $reviewer2->email, $reviewer3->email]
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act
    //     $result = $this->useCase->execute($dto, $this->user);

    //     // Assert
    //     $pullRequest = PullRequest::find($result['pull_request_id']);
    //     $reviewerCount = PullRequestReviewer::where('pull_request_id', $pullRequest->id)->count();
    //     $this->assertEquals(3, $reviewerCount);

    //     // 各レビュアーが正しく登録されているか確認
    //     $reviewerIds = PullRequestReviewer::where('pull_request_id', $pullRequest->id)
    //         ->pluck('user_id')
    //         ->sort()
    //         ->values()
    //         ->toArray();

    //     $expectedIds = collect([$this->reviewerUser->id, $reviewer2->id, $reviewer3->id])
    //         ->sort()
    //         ->values()
    //         ->toArray();

    //     $this->assertEquals($expectedIds, $reviewerIds);
    // }

    // /**
    //  * descriptionがnullの場合のテスト
    //  */
    // public function test_execute_success_with_null_description(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request',
    //         description: null
    //     );

    //     // モックの設定
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id);

    //     // Act
    //     $result = $this->useCase->execute($dto, $this->user);

    //     // Assert
    //     $pullRequest = PullRequest::find($result['pull_request_id']);
    //     $this->assertNull($pullRequest->description);
    // }

    // /**
    //  * トランザクションロールバックの詳細テスト
    //  */
    // public function test_transaction_rollback_on_exception(): void
    // {
    //     // Arrange
    //     $dto = new CreatePullRequestDto(
    //         userBranchId: $this->userBranch->id,
    //         organizationId: $this->organization->id,
    //         title: 'Test Pull Request'
    //     );

    //     // 実行前の状態を記録
    //     $initialDocumentVersionStatus = $this->documentVersion->status;
    //     $initialDocumentCategoryStatus = $this->documentCategory->status;
    //     $initialPullRequestCount = PullRequest::count();

    //     // モックの設定（途中で例外を発生させる）
    //     $this->organizationService
    //         ->shouldReceive('validateUserBelongsToOrganization')
    //         ->once()
    //         ->with($this->user->id, $this->organization->id);

    //     $this->userBranchService
    //         ->shouldReceive('findActiveUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andReturn($this->userBranch);

    //     $this->userBranchService
    //         ->shouldReceive('deactivateUserBranch')
    //         ->once()
    //         ->with($this->userBranch->id)
    //         ->andThrow(new \Exception('テスト例外'));

    //     // Act & Assert
    //     $this->expectException(\Exception::class);
    //     $this->expectExceptionMessage('テスト例外');
    //     $this->useCase->execute($dto, $this->user);

    //     // 状態が元に戻っていることを確認
    //     $this->documentVersion->refresh();
    //     $this->documentCategory->refresh();

    //     $this->assertEquals($initialDocumentVersionStatus, $this->documentVersion->status);
    //     $this->assertEquals($initialDocumentCategoryStatus, $this->documentCategory->status);
    //     $this->assertEquals($initialPullRequestCount, PullRequest::count());
    // }
}
