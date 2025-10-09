<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\Services\UserBranchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class UserBranchServiceFetchOrCreateActiveTest extends TestCase
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
    public function fetch_or_create_active_branch_アクティブなセッションが存在する場合はそのブランチ_idを返す()
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
        $result = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $this->assertEquals($userBranch->id, $result);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_アクティブなセッションが存在しない場合は新しいブランチを作成する()
    {
        // Arrange
        $initialUserBranchCount = UserBranch::count();
        $initialSessionCount = UserBranchSession::count();

        // Act
        $result = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $this->assertIsInt($result);
        $this->assertEquals($initialUserBranchCount + 1, UserBranch::count());
        $this->assertEquals($initialSessionCount + 1, UserBranchSession::count());

        // 作成されたブランチの検証
        $createdBranch = UserBranch::find($result);
        $this->assertNotNull($createdBranch);
        $this->assertEquals($this->user->id, $createdBranch->creator_id);
        $this->assertEquals($this->organization->id, $createdBranch->organization_id);
        $this->assertStringStartsWith('branch_'.$this->user->id.'_', $createdBranch->branch_name);

        // 作成されたセッションの検証
        $createdSession = UserBranchSession::where('user_branch_id', $result)->first();
        $this->assertNotNull($createdSession);
        $this->assertEquals($this->user->id, $createdSession->user_id);
        $this->assertEquals($result, $createdSession->user_branch_id);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_セッションはあるが組織_idが一致しない場合は新しいブランチを作成する()
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

        $initialUserBranchCount = UserBranch::count();
        $initialSessionCount = UserBranchSession::count();

        // Act
        $result = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $this->assertNotEquals($userBranch->id, $result);
        $this->assertEquals($initialUserBranchCount + 1, UserBranch::count());
        $this->assertEquals($initialSessionCount + 1, UserBranchSession::count());

        // 作成されたブランチの組織IDの検証
        $createdBranch = UserBranch::find($result);
        $this->assertEquals($this->organization->id, $createdBranch->organization_id);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_トランザクション内で新しいブランチとセッションが作成される()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $this->assertIsInt($result);

        $createdBranch = UserBranch::find($result);
        $this->assertNotNull($createdBranch);

        $createdSession = UserBranchSession::where('user_branch_id', $result)->first();
        $this->assertNotNull($createdSession);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_ブランチ名に正しい形式のタイムスタンプが含まれる()
    {
        // Arrange
        $beforeTimestamp = time();

        // Act
        $result = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $afterTimestamp = time();
        $createdBranch = UserBranch::find($result);

        // ブランチ名から数値部分を抽出
        preg_match('/branch_'.$this->user->id.'_(\d+)/', $createdBranch->branch_name, $matches);
        $this->assertNotEmpty($matches);

        $branchTimestamp = (int) $matches[1];
        $this->assertGreaterThanOrEqual($beforeTimestamp, $branchTimestamp);
        $this->assertLessThanOrEqual($afterTimestamp, $branchTimestamp);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_同じユーザーと組織で複数回呼び出しても既存のアクティブなブランチを返す()
    {
        // Arrange & Act - 1回目の呼び出し
        $firstResult = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Act - 2回目の呼び出し
        $secondResult = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);

        // Assert
        $this->assertEquals($firstResult, $secondResult);
        $this->assertEquals(1, UserBranch::where('creator_id', $this->user->id)
            ->where('organization_id', $this->organization->id)
            ->count());
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_複数の組織で異なるブランチを作成する()
    {
        // Arrange
        $organization1 = $this->organization;
        $organization2 = Organization::factory()->create();

        // Act
        $branch1 = $this->service->fetchOrCreateActiveBranch($this->user, $organization1->id);
        $branch2 = $this->service->fetchOrCreateActiveBranch($this->user, $organization2->id);

        // Assert
        $this->assertNotEquals($branch1, $branch2);

        $createdBranch1 = UserBranch::find($branch1);
        $this->assertEquals($organization1->id, $createdBranch1->organization_id);

        $createdBranch2 = UserBranch::find($branch2);
        $this->assertEquals($organization2->id, $createdBranch2->organization_id);
    }

    /**
     * @test
     */
    public function fetch_or_create_active_branch_別のユーザーの場合は独立したブランチを作成する()
    {
        // Arrange
        $user2 = User::factory()->create();

        // Act
        $branch1 = $this->service->fetchOrCreateActiveBranch($this->user, $this->organization->id);
        $branch2 = $this->service->fetchOrCreateActiveBranch($user2, $this->organization->id);

        // Assert
        $this->assertNotEquals($branch1, $branch2);

        $createdBranch1 = UserBranch::find($branch1);
        $this->assertEquals($this->user->id, $createdBranch1->creator_id);

        $createdBranch2 = UserBranch::find($branch2);
        $this->assertEquals($user2->id, $createdBranch2->creator_id);
    }
}

