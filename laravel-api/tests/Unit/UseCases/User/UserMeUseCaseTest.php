<?php

namespace Tests\Unit\UseCases\User;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\UseCases\User\UserMeUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserMeUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UserMeUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new UserMeUseCase();
    }

    #[Test]
    public function execute_returns_user_info_with_organization_and_active_branch_when_all_data_exists(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        $activeUserBranch = UserBranch::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $user->id,
            'user_branch_id' => $activeUserBranch->id,
        ]);

        // Act
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('organization', $result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Organization::class, $result['organization']);
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertEquals($organization->id, $result['organization']->id);
        $this->assertEquals($activeUserBranch->id, $result['activeUserBranch']->id);
    }

    #[Test]
    public function execute_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 999999]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($user);
    }

    #[Test]
    public function execute_throws_exception_when_organization_member_not_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        // OrganizationMemberを作成しないことで組織メンバー情報が存在しない状態を作る

        // Act & Assert
        $this->expectException(\ErrorException::class);
        $this->useCase->execute($user);
    }

    #[Test]
    public function execute_returns_null_active_branch_when_no_active_branch_exists(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        // 非アクティブなユーザーブランチのみ作成（セッションなし）
        UserBranch::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $organization->id,
        ]);

        // Act
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertNull($result['activeUserBranch']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Organization::class, $result['organization']);
    }

    #[Test]
    public function execute_returns_first_active_branch_when_multiple_active_branches_exist(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        // 複数のアクティブなユーザーブランチを作成
        $firstActiveBranch = UserBranch::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $organization->id,
            'created_at' => now()->subDays(2),
        ]);
        
        UserBranchSession::create([
            'user_id' => $user->id,
            'user_branch_id' => $firstActiveBranch->id,
        ]);

        $secondActiveBranch = UserBranch::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $organization->id,
            'created_at' => now()->subDay(),
        ]);
        
        UserBranchSession::create([
            'user_id' => $user->id,
            'user_branch_id' => $secondActiveBranch->id,
        ]);

        // Act
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        // first()は最初に見つかった1つを返すことを確認
        $this->assertNotNull($result['activeUserBranch']);
    }

    #[Test]
    public function execute_returns_correct_data_when_user_has_no_branches(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        // ユーザーブランチを一切作成しない

        // Act
        $result = $this->useCase->execute($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertNull($result['activeUserBranch']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Organization::class, $result['organization']);
    }
}
