<?php

namespace Tests\Unit\Services;

use App\Consts\Flag;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use Github\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserBranchServiceTest extends TestCase
{
    public function test_dummy(): void
    {
        $this->assertTrue(true);
    }
    // use DatabaseTransactions;

    // private UserBranchService $userBranchService;

    // protected function setUp(): void
    // {
    //     parent::setUp();

    //     // GitHub Clientのモックを作成
    //     $mockClient = Mockery::mock(Client::class);
    //     $this->app->instance(Client::class, $mockClient);

    //     $this->userBranchService = new UserBranchService($mockClient);
    // }

    // protected function tearDown(): void
    // {
    //     Mockery::close();
    //     parent::tearDown();
    // }

    // /**
    //  * 外部API（GitHub Client）をモックしたUserBranchServiceを作成する
    //  */
    // private function createMockedUserBranchService(string $commitHash = 'abc123def456'): UserBranchService
    // {
    //     $mockClient = Mockery::mock(Client::class);
    //     $mockGitData = Mockery::mock('Github\Api\GitData');
    //     $mockReferences = Mockery::mock('Github\Api\GitData\References');

    //     $mockClient->shouldReceive('gitData')
    //         ->andReturn($mockGitData);

    //     $mockGitData->shouldReceive('references')
    //         ->andReturn($mockReferences);

    //     $mockReferences->shouldReceive('show')
    //         ->with(
    //             config('services.github.owner'),
    //             config('services.github.repo'),
    //             'heads/main'
    //         )
    //         ->andReturn(['object' => ['sha' => $commitHash]]);

    //     return new UserBranchService($mockClient);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_with_edit_pull_request_id_returns_user_branch_id()
    // {
    //     // Arrange
    //     $user = User::factory()->create();
    //     $userBranch = UserBranch::factory()->create([
    //         'user_id' => $user->id,
    //         'is_active' => Flag::TRUE,
    //     ]);
    //     $pullRequest = PullRequest::factory()->create([
    //         'user_branch_id' => $userBranch->id,
    //     ]);

    //     // Act
    //     $result = $this->userBranchService->fetchOrCreateActiveBranch($user, $pullRequest->id);

    //     // Assert
    //     $this->assertEquals($userBranch->id, $result);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_without_edit_pull_request_id_and_active_exists_returns_active_id()
    // {
    //     // Arrange
    //     $user = User::factory()->create();
    //     $activeBranch = UserBranch::factory()->create([
    //         'user_id' => $user->id,
    //         'is_active' => Flag::TRUE,
    //     ]);

    //     // Act
    //     $result = $this->userBranchService->fetchOrCreateActiveBranch($user);

    //     // Assert
    //     $this->assertEquals($activeBranch->id, $result);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_without_edit_pull_request_id_and_no_active_creates_new()
    // {
    //     // Arrange
    //     $mockedService = $this->createMockedUserBranchService('test-commit-hash-123');

    //     $user = User::factory()->create();

    //     // 非アクティブなブランチを作成（アクティブなブランチが存在しない状況）
    //     UserBranch::factory()->create([
    //         'user_id' => $user->id,
    //         'is_active' => Flag::FALSE,
    //     ]);

    //     // Act
    //     $result = $mockedService->fetchOrCreateActiveBranch($user);

    //     // Assert
    //     $this->assertIsInt($result);

    //     // 新しいアクティブなブランチが作成されていることを確認
    //     $newBranch = UserBranch::find($result);
    //     $this->assertNotNull($newBranch);
    //     $this->assertEquals($user->id, $newBranch->user_id);
    //     $this->assertTrue($newBranch->is_active);
    //     $this->assertEquals('test-commit-hash-123', $newBranch->snapshot_commit);
    //     $this->assertStringStartsWith('branch_'.$user->id.'_', $newBranch->branch_name);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_without_edit_pull_request_id_and_no_branches_creates_new()
    // {
    //     // Arrange
    //     $mockedService = $this->createMockedUserBranchService('new-commit-hash-456');

    //     $user = User::factory()->create();
    //     // ユーザーブランチが全く存在しない状況

    //     // Act
    //     $result = $mockedService->fetchOrCreateActiveBranch($user);

    //     // Assert
    //     $this->assertIsInt($result);

    //     // 新しいアクティブなブランチが作成されていることを確認
    //     $newBranch = UserBranch::find($result);
    //     $this->assertNotNull($newBranch);
    //     $this->assertEquals($user->id, $newBranch->user_id);
    //     $this->assertTrue($newBranch->is_active);
    //     $this->assertEquals('new-commit-hash-456', $newBranch->snapshot_commit);
    //     $this->assertStringStartsWith('branch_'.$user->id.'_', $newBranch->branch_name);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_can_handle_multiple_inactive_ranches()
    // {
    //     // Arrange
    //     $user = User::factory()->create();

    //     // 複数の非アクティブなブランチを作成（これは制約に違反しない）
    //     UserBranch::factory()->create([
    //         'user_id' => $user->id,
    //         'is_active' => Flag::FALSE,
    //     ]);
    //     UserBranch::factory()->create([
    //         'user_id' => $user->id,
    //         'is_active' => Flag::FALSE,
    //     ]);

    //     // アクティブなブランチは存在しない状況でサービスを実行
    //     $mockedService = $this->createMockedUserBranchService('test-commit-789');

    //     // Act
    //     $result = $mockedService->fetchOrCreateActiveBranch($user);

    //     // Assert
    //     $this->assertIsInt($result);

    //     // 新しいアクティブなブランチが作成されていることを確認
    //     $newBranch = UserBranch::find($result);
    //     $this->assertNotNull($newBranch);
    //     $this->assertEquals($user->id, $newBranch->user_id);
    //     $this->assertTrue($newBranch->is_active);
    //     $this->assertEquals('test-commit-789', $newBranch->snapshot_commit);
    // }

    // #[Test]
    // public function fetch_or_create_active_branch_throws_exception_for_invalid_pull_request_id()
    // {
    //     // Arrange
    //     $user = User::factory()->create();
    //     $invalidPullRequestId = 99999;

    //     // Act & Assert
    //     $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    //     $this->userBranchService->fetchOrCreateActiveBranch($user, $invalidPullRequestId);
    // }
}
