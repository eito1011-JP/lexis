<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestStatus;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\PullRequest\MergePullRequestUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergePullRequestUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private MergePullRequestUseCase $useCase;
    private User $adminUser;
    private User $ownerUser;
    private User $editorUser;
    private User $nonMemberUser;
    private Organization $organization;
    private UserBranch $userBranch;
    private PullRequest $pullRequest;
    private DocumentVersion $documentVersion;
    private DocumentCategory $documentCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = $this->app->make(MergePullRequestUseCase::class);
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
        $this->adminUser = User::factory()->create();
        $this->ownerUser = User::factory()->create();
        $this->editorUser = User::factory()->create();
        $this->nonMemberUser = User::factory()->create();

        // 組織メンバーの作成
        OrganizationMember::create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $this->ownerUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // 組織ロールバインディングの作成
        OrganizationRoleBinding::create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::ADMIN->value,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $this->ownerUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::OWNER->value,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        // ユーザーブランチの作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'test-branch',
        ]);

        // プルリクエストの作成
        $this->pullRequest = PullRequest::create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'title' => 'Test Pull Request',
            'description' => 'Test Description',
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // ドキュメントカテゴリの作成
        $this->documentCategory = DocumentCategory::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        // ドキュメントバージョンの作成
        $this->documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->adminUser->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);
    }

    /**
     * 管理者権限でマージが成功する場合のテスト
     */
    public function testExecuteSuccessWithAdminUser(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // プルリクエストのステータスが更新されているか確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::MERGED->value, $this->pullRequest->status);

        // ドキュメントバージョンのステータスが更新されているか確認
        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::MERGED->value, $this->documentVersion->status);

        // ドキュメントカテゴリのステータスが更新されているか確認
        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $this->documentCategory->status);

        // アクティビティログが作成されているか確認
        $activityLog = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->first();
        $this->assertNotNull($activityLog);
        $this->assertEquals($this->adminUser->id, $activityLog->user_id);
    }

    /**
     * オーナー権限でマージが成功する場合のテスト
     */
    public function testExecuteSuccessWithOwnerUser(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->ownerUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // プルリクエストのステータスが更新されているか確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::MERGED->value, $this->pullRequest->status);

        // アクティビティログが作成されているか確認
        $activityLog = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->first();
        $this->assertNotNull($activityLog);
        $this->assertEquals($this->ownerUser->id, $activityLog->user_id);
    }

    /**
     * 編集者権限でマージが失敗する場合のテスト（AuthorizationException）
     */
    public function testExecuteFailsWithEditorUser(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->editorUser->id
        );

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->useCase->execute($dto);

        // プルリクエストのステータスが変更されていないことを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);

        // ドキュメントのステータスが変更されていないことを確認
        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $this->documentVersion->status);

        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $this->documentCategory->status);

        // アクティビティログが作成されていないことを確認
        $activityLogCount = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->count();
        $this->assertEquals(0, $activityLogCount);
    }

    /**
     * 組織に所属していないユーザーでマージが失敗する場合のテスト（AuthorizationException）
     */
    public function testExecuteFailsWithNonMemberUser(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->nonMemberUser->id
        );

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->useCase->execute($dto);

        // プルリクエストのステータスが変更されていないことを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);
    }

    /**
     * 存在しないプルリクエストIDでマージが失敗する場合のテスト（NotFoundException）
     */
    public function testExecuteFailsWithNonExistentPullRequest(): void
    {
        // Arrange
        $nonExistentId = 99999;
        $dto = new MergePullRequestDto(
            pullRequestId: $nonExistentId,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * 既にクローズされたプルリクエストでマージが失敗する場合のテスト（NotFoundException）
     */
    public function testExecuteFailsWithClosedPullRequest(): void
    {
        // Arrange
        $this->pullRequest->update(['status' => PullRequestStatus::CLOSED->value]);

        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * 既にマージされたプルリクエストでマージが失敗する場合のテスト（NotFoundException）
     */
    public function testExecuteFailsWithMergedPullRequest(): void
    {
        // Arrange
        $this->pullRequest->update(['status' => PullRequestStatus::MERGED->value]);

        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * トランザクション内で例外が発生した場合のロールバックテスト
     */
    public function testTransactionRollbackOnException(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // UseCaseをモックして例外を発生させる
        $mockUseCase = $this->getMockBuilder(MergePullRequestUseCase::class)
            ->setConstructorArgs([$this->app->make(\App\Policies\PullRequestPolicy::class)])
            ->onlyMethods(['execute'])
            ->getMock();

        $mockUseCase->method('execute')
            ->willReturnCallback(function ($dto) {
                DB::beginTransaction();
                
                try {
                    // プルリクエストを取得
                    $pullRequest = PullRequest::with(['userBranch'])
                        ->where('id', $dto->pullRequestId)
                        ->where('status', PullRequestStatus::OPENED->value)
                        ->lockForUpdate()
                        ->first();

                    if (!$pullRequest) {
                        throw new NotFoundException();
                    }

                    // 権限チェック
                    if (!$this->app->make(\App\Policies\PullRequestPolicy::class)->merge($dto->userId, $pullRequest)) {
                        throw new AuthorizationException();
                    }

                    // ステータス更新を開始
                    $userBranch = $pullRequest->userBranch;
                    $userBranch->documentVersions()->update([
                        'status' => DocumentStatus::MERGED->value,
                    ]);

                    // 意図的に例外を発生させる
                    throw new \RuntimeException('Intentional exception for testing rollback');

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Intentional exception for testing rollback');
        
        $mockUseCase->execute($dto);

        // ロールバックされていることを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);

        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $this->documentVersion->status);

        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $this->documentCategory->status);
    }
}
