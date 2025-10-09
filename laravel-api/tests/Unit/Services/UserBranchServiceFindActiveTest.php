<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\UserBranchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class UserBranchServiceFindActiveTest extends TestCase
{
    use DatabaseTransactions;

    private UserBranchService $service;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserBranchService;

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function find_active_user_branch_アクティブなユーザーブランチが存在する場合は正常に取得できる()
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id, $this->organization->id, $this->user->id);

        // Assert
        $this->assertInstanceOf(UserBranch::class, $result);
        $this->assertEquals($userBranch->id, $result->id);
        $this->assertEquals($this->user->id, $result->creator_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
    }

    /**
     * @test
     */
    public function find_active_user_branch_存在しないユーザーブランチ_i_dの場合は_nullが返される()
    {
        // Arrange
        $nonExistentUserBranchId = 99999;

        // Act & Assert
        $this->assertNull($this->service->findActiveUserBranch($nonExistentUserBranchId, $this->organization->id, $this->user->id));
    }

    /**
     * @test
     */
    public function find_active_user_branch_非アクティブなユーザーブランチの場合は_nullが返される()
    {
        // Arrange - セッションなし（非アクティブ）のブランチを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act & Assert
        $this->assertNull($this->service->findActiveUserBranch($userBranch->id, $this->organization->id, $this->user->id));
    }

    /**
     * @test
     */
    public function find_active_user_branch_複数のユーザーブランチが存在する場合でも正しいアクティブなブランチが取得できる()
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        $activeUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $activeUserBranch->id,
        ]);

        // 非アクティブなブランチ（セッションなし）
        $inactiveUserBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($activeUserBranch->id, $this->organization->id, $this->user->id);

        // Assert
        $this->assertEquals($activeUserBranch->id, $result->id);
    }

    /**
     * @test
     */
    public function find_active_user_branch_別の組織のアクティブなユーザーブランチは取得できない()
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        $anotherOrganization = Organization::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id, $this->organization->id, $this->user->id);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function find_active_user_branch_別のユーザーのアクティブなユーザーブランチは取得できない()
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        $anotherUser = User::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $anotherUser->id,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id, $this->organization->id, $this->user->id);

        // Assert
        $this->assertNull($result);
    }
}
