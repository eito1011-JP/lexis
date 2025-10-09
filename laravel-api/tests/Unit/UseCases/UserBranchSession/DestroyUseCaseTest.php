<?php

namespace Tests\Unit\UseCases\UserBranchSession;

use App\Dto\UseCase\UserBranchSession\DestroyDto;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\UserBranchService;
use App\UseCases\UserBranchSession\DestroyUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DestroyUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private DestroyUseCase $useCase;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    private UserBranchService $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userBranchService = new UserBranchService();
        $this->useCase = new DestroyUseCase($this->userBranchService);

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
    }

    #[Test]
    public function execute_successfully_destroys_user_branch_session(): void
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $this->user
        );

        // Act
        $this->useCase->execute($dto);

        // Assert - セッションが削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_has_no_organization(): void
    {
        // Arrange - 組織に属していないユーザーを作成
        $userWithoutOrg = User::factory()->create();

        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $userWithoutOrg
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_not_found(): void
    {
        // Arrange
        $dto = new DestroyDto(
            userBranchId: 99999,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_organization_id_does_not_match(): void
    {
        // Arrange - 別の組織を作成
        $anotherOrganization = Organization::factory()->create();
        $anotherUser = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $anotherOrganization->id,
        ]);

        // セッションを作成
        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $anotherUser
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 元のユーザーブランチが変更されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
        ]);
        // セッションが削除されていないことを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_does_not_affect_other_user_branches(): void
    {
        // Arrange - 同じユーザーの別のブランチを作成してセッションも作成
        $anotherUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $anotherUserBranch->id,
        ]);

        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $this->user
        );

        // Act
        $this->useCase->execute($dto);

        // Assert - 対象ブランチのセッションのみ削除されていることを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_branch_id' => $this->userBranch->id,
        ]);
        // 別のブランチのセッションは残っていることを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_branch_id' => $anotherUserBranch->id,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function execute_does_nothing_when_session_does_not_exist(): void
    {
        // Arrange - セッションが存在しない状態
        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $this->user
        );

        // Act - 例外がスローされないことを確認
        $this->useCase->execute($dto);

        // Assert - セッションが存在しないことを確認
        $this->assertDatabaseMissing('user_branch_sessions', [
            'user_branch_id' => $this->userBranch->id,
        ]);
    }

    #[Test]
    public function execute_cannot_delete_other_users_session_even_as_branch_creator(): void
    {
        // Arrange - 別のユーザーを作成
        $anotherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        // 別のユーザーのセッションを作成（ブランチ作成者は$this->user）
        UserBranchSession::create([
            'user_id' => $anotherUser->id,
            'user_branch_id' => $this->userBranch->id,
        ]);

        $dto = new DestroyDto(
            userBranchId: $this->userBranch->id,
            user: $this->user // ブランチ作成者が別のユーザーのセッションを削除しようとする
        );

        // Act - 例外がスローされないことを確認（何も削除されない）
        $this->useCase->execute($dto);

        // Assert - 別のユーザーのセッションは残っていることを確認
        $this->assertDatabaseHas('user_branch_sessions', [
            'user_id' => $anotherUser->id,
            'user_branch_id' => $this->userBranch->id,
        ]);
    }
}

