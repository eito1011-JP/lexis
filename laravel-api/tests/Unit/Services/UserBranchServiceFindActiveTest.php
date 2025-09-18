<?php

namespace Tests\Unit\Services;

use App\Consts\Flag;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
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

        // GitHubクライアントをモック化
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
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id);

        // Assert
        $this->assertInstanceOf(UserBranch::class, $result);
        $this->assertEquals($userBranch->id, $result->id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
        $this->assertEquals(Flag::TRUE, $result->is_active);
    }

    /**
     * @test
     */
    public function find_active_user_branch_存在しないユーザーブランチ_i_dの場合は_not_found_exception例外がスローされる()
    {
        // Arrange
        $nonExistentUserBranchId = 99999;

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->findActiveUserBranch($nonExistentUserBranchId);
    }

    /**
     * @test
     */
    public function find_active_user_branch_非アクティブなユーザーブランチの場合は_not_found_exception例外がスローされる()
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::FALSE,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->findActiveUserBranch($userBranch->id);
    }

    /**
     * @test
     */
    public function find_active_user_branch_削除されたユーザーブランチの場合は_not_found_exception例外がスローされる()
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // ソフトデリート
        $userBranch->delete();

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->findActiveUserBranch($userBranch->id);
    }

    /**
     * @test
     */
    public function find_active_user_branch_複数のユーザーブランチが存在する場合でも正しいアクティブなブランチが取得できる()
    {
        // Arrange
        $activeUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        $inactiveUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::FALSE,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($activeUserBranch->id);

        // Assert
        $this->assertEquals($activeUserBranch->id, $result->id);
        $this->assertEquals(Flag::TRUE, $result->is_active);
    }

    /**
     * @test
     */
    public function find_active_user_branch_別の組織のアクティブなユーザーブランチでも正常に取得できる()
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id);

        // Assert
        $this->assertEquals($userBranch->id, $result->id);
        $this->assertEquals($anotherOrganization->id, $result->organization_id);
        $this->assertEquals(Flag::TRUE, $result->is_active);
    }

    /**
     * @test
     */
    public function find_active_user_branch_別のユーザーのアクティブなユーザーブランチでも正常に取得できる()
    {
        // Arrange
        $anotherUser = User::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->findActiveUserBranch($userBranch->id);

        // Assert
        $this->assertEquals($userBranch->id, $result->id);
        $this->assertEquals($anotherUser->id, $result->user_id);
        $this->assertEquals(Flag::TRUE, $result->is_active);
    }
}
