<?php

namespace Tests\Unit\UseCases\UserBranch;

use App\Consts\Flag;
use App\Dto\UseCase\UserBranch\UpdateUserBranchDto;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\UserBranch\UpdateUserBranchUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateUserBranchUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateUserBranchUseCase $useCase;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new UpdateUserBranchUseCase();

        // テストデータの準備
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // ユーザーブランチを作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::FALSE,
        ]);
    }

    #[Test]
    public function execute_successfully_updates_user_branch_is_active_to_true(): void
    {
        // Arrange
        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result->is_active);
        $this->assertEquals($this->userBranch->id, $result->id);
        $this->assertDatabaseHas('user_branches', [
            'id' => $this->userBranch->id,
            'is_active' => Flag::TRUE,
        ]);
    }

    #[Test]
    public function execute_successfully_updates_user_branch_is_active_to_false(): void
    {
        // Arrange
        $activeUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        $dto = new UpdateUserBranchDto(
            userBranchId: $activeUserBranch->id,
            isActive: false,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertFalse($result->is_active);
        $this->assertEquals($activeUserBranch->id, $result->id);
        $this->assertDatabaseHas('user_branches', [
            'id' => $activeUserBranch->id,
            'is_active' => Flag::FALSE,
        ]);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_branch_not_found(): void
    {
        // Arrange
        $dto = new UpdateUserBranchDto(
            userBranchId: 99999,
            isActive: true,
            user: $this->user
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    #[Test]
    public function execute_throws_not_found_exception_when_user_id_does_not_match(): void
    {
        // Arrange - 別のユーザーを作成
        $anotherUser = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $anotherUser
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 元のユーザーブランチが変更されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $this->userBranch->id,
            'user_id' => $this->user->id,
            'is_active' => Flag::FALSE,
        ]);
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

        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $anotherUser
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);

        // 元のユーザーブランチが変更されていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::FALSE,
        ]);
    }

    #[Test]
    public function execute_returns_refreshed_user_branch_with_updated_values(): void
    {
        // Arrange
        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert - refreshされた最新の値が返されることを確認
        $this->assertInstanceOf(UserBranch::class, $result);
        $this->assertTrue($result->is_active);
        $this->assertEquals($this->userBranch->id, $result->id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
    }

    #[Test]
    public function execute_does_not_affect_other_user_branches(): void
    {
        // Arrange - 同じユーザーの別のブランチを作成
        $anotherUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $this->user
        );

        // Act
        $this->useCase->execute($dto);

        // Assert - 別のブランチは影響を受けていないことを確認
        $this->assertDatabaseHas('user_branches', [
            'id' => $anotherUserBranch->id,
            'is_active' => Flag::TRUE,
        ]);
    }

    #[Test]
    public function execute_verifies_all_conditions_before_updating(): void
    {
        // Arrange - すべての条件が満たされている正常なケース
        $dto = new UpdateUserBranchDto(
            userBranchId: $this->userBranch->id,
            isActive: true,
            user: $this->user
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert - すべての条件がチェックされた上で更新されていることを確認
        $this->assertEquals($this->userBranch->id, $result->id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertTrue($result->is_active);
    }
}

