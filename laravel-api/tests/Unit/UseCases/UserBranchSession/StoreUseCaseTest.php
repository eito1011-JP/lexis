<?php

namespace Tests\Unit\UseCases\UserBranchSession;

use App\Dto\UseCase\UserBranchSession\StoreDto;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestStatus;
use App\Exceptions\DuplicateExecutionException;
use App\Exceptions\NotAuthorizedException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\UseCases\UserBranchSession\StoreUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private StoreUseCase $useCase;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    private PullRequest $pullRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new StoreUseCase();

        // テストデータの準備
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // ユーザーブランチを作成
        $this->userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // プルリクエストを作成
        $this->pullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => PullRequestStatus::OPENED->value,
        ]);
    }

    #[Test]
    public function execute_successfully_creates_user_branch_session_with_editor_role(): void
    {
        // Arrange - editorロールを付与
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(UserBranchSession::class, $result);
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_successfully_creates_user_branch_session_with_admin_role(): void
    {
        // Arrange - adminロールを付与
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::ADMIN->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(UserBranchSession::class, $result);
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_successfully_creates_user_branch_session_with_owner_role(): void
    {
        // Arrange - ownerロールを付与
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::OWNER->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(UserBranchSession::class, $result);
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_has_no_organization_member(): void
    {
        // Arrange - 組織に属していないユーザーを作成
        $userWithoutOrg = User::factory()->create();

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $userWithoutOrg
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_role_binding_not_found(): void
    {
        // Arrange - OrganizationRoleBindingを作成しない
        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_authorized_exception_when_user_is_viewer(): void
    {
        // Arrange - viewerロールを付与
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::VIEWER->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotAuthorizedException::class);
        $this->useCase->execute($dto);

        // セッションが作成されていないことを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_pull_request_not_found(): void
    {
        // Arrange
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: 99999, // 存在しないID
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_pull_request_is_closed(): void
    {
        // Arrange - closedステータスのプルリクエストを作成
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $closedPullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => PullRequestStatus::CLOSED->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $closedPullRequest->id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_pull_request_is_merged(): void
    {
        // Arrange - mergedステータスのプルリクエストを作成
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $mergedPullRequest = PullRequest::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => PullRequestStatus::MERGED->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $mergedPullRequest->id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_organization_id_does_not_match(): void
    {
        // Arrange - 別の組織とユーザーを作成
        $anotherOrganization = Organization::factory()->create();
        $anotherUser = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $anotherOrganization->id,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $anotherUser->id,
            'organization_id' => $anotherOrganization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $anotherUser
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 元のプルリクエストが変更されていないことを確認
        $this->assertDatabaseHas('pull_requests', [
            'id' => $this->pullRequest->id,
            'organization_id' => $this->organization->id,
        ]);

        // セッションが作成されていないことを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_duplicate_execution_exception_when_session_already_exists(): void
    {
        // Arrange - 既存のセッションを作成
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $anotherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $anotherUser->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(DuplicateExecutionException::class);
        $this->useCase->execute($dto);

        // 既存のセッションが保持されていることを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $anotherUser->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        // 新しいセッションが作成されていないことを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_returns_user_branch_session_with_correct_attributes(): void
    {
        // Arrange
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->userBranch->id, $result->user_branch_id);
        $this->assertNotNull($result->id);
    }

    #[Test]
    public function execute_uses_transaction_and_rolls_back_on_error(): void
    {
        // Arrange - 無効なデータで例外が発生する状況を作る
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::VIEWER->value, // viewerなので例外が発生
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act & Assert
        try {
            $this->useCase->execute($dto);
        } catch (NotAuthorizedException $e) {
            // セッションが作成されていないことを確認（ロールバックされている）
            $this->assertDatabaseMissing('user_branch_sessions', [
                'user_id' => $this->user->id,
                'user_branch_id' => $this->userBranch->id,
            ]);

            return;
        }

        $this->fail('NotAuthorizedException was not thrown');
    }

    #[Test]
    public function execute_does_not_affect_other_user_branches(): void
    {
        // Arrange - 別のユーザーブランチと既存のセッションを作成
        OrganizationRoleBinding::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        $anotherUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $anotherUserBranch->id,
        ]);

        $dto = new StoreDto(
            pullRequestId: $this->pullRequest->id,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert - 新しいセッションが作成されていることを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        // 既存の別のブランチのセッションが保持されていることを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $anotherUserBranch->id,
        ]);

        // 2つのセッションが存在することを確認
        $this->assertEquals(2, UserBranchSession::where('user_id', $this->user->id)->count());
    }
}

