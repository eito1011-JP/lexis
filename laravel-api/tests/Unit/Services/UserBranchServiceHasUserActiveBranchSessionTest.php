<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\UserBranchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserBranchServiceHasUserActiveBranchSessionTest extends TestCase
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

    /**
     * @test
     */
    public function has_user_active_branch_session_アクティブなセッションが存在し組織_idが一致する場合はユーザーブランチを返す()
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
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(UserBranch::class, $result);
        $this->assertEquals($userBranch->id, $result->id);
        $this->assertEquals($this->organization->id, $result->organization_id);
    }

    /**
     * @test
     */
    public function has_user_active_branch_session_アクティブなセッションが存在しない場合はnullを返す()
    {
        // Arrange - セッションなし

        // Act
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function has_user_active_branch_session_アクティブなセッションは存在するが組織_idが一致しない場合はnullを返す()
    {
        // Arrange - 別の組織のブランチとセッションを作成
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
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function has_user_active_branch_session_複数のブランチセッションが存在する場合は最初のアクティブなセッションを返す()
    {
        // Arrange - 複数のブランチとセッションを作成（最初のものだけがアクティブ）
        $userBranch1 = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        $userBranch2 = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // アクティブなセッションを作成（最初に作成されたもの）
        $activeSession = UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $userBranch1->id,
        ]);

        // Act
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($userBranch1->id, $result->id);
    }

    /**
     * @test
     */
    public function has_user_active_branch_session_別のユーザーのセッションには影響されない()
    {
        // Arrange - 別のユーザーのセッションを作成
        $anotherUser = User::factory()->create();
        $anotherUserBranch = UserBranch::factory()->create([
            'creator_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
        ]);

        UserBranchSession::create([
            'user_id' => $anotherUser->id,
            'user_branch_id' => $anotherUserBranch->id,
        ]);

        // Act
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function has_user_active_branch_session_正しいユーザーブランチの属性が取得できる()
    {
        // Arrange - アクティブなセッションを持つブランチを作成
        $userBranch = UserBranch::factory()->create([
            'creator_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'branch_name' => 'test_branch',
        ]);

        UserBranchSession::create([
            'user_id' => $this->user->id,
            'user_branch_id' => $userBranch->id,
        ]);

        // Act
        $result = $this->service->hasUserActiveBranchSession($this->user, $this->organization->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(UserBranch::class, $result);
        $this->assertEquals('test_branch', $result->branch_name);
        $this->assertEquals($this->user->id, $result->creator_id);
        $this->assertEquals($this->organization->id, $result->organization_id);
    }
}

