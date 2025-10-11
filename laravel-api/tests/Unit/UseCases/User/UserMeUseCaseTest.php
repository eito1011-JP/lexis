<?php

namespace Tests\Unit\UseCases\User;

use App\Enums\PullRequestStatus;
use App\Models\Commit;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use App\UseCases\User\UserMeUseCase;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserMeUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UserMeUseCase $useCase;

    private UserBranchService $userBranchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userBranchService = new UserBranchService();
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
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('organization', $result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Organization::class, $result['organization']);
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertEquals($organization->id, $result['organization']->id);
        $this->assertEquals($activeUserBranch->id, $result['activeUserBranch']->id);
        $this->assertNull($result['nextAction']);
        // pullRequestsリレーションがロードされていることを確認
        $this->assertTrue($result['activeUserBranch']->relationLoaded('pullRequests'));
    }

    #[Test]
    public function execute_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 999999]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($user, $this->userBranchService);
    }

    #[Test]
    public function execute_throws_exception_when_organization_member_not_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        // OrganizationMemberを作成しないことで組織メンバー情報が存在しない状態を作る

        // Act & Assert
        $this->expectException(\ErrorException::class);
        $this->useCase->execute($user, $this->userBranchService);
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
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertNull($result['activeUserBranch']);
        $this->assertNull($result['nextAction']);
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
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        // first()は最初に見つかった1つを返すことを確認
        $this->assertNotNull($result['activeUserBranch']);
        $this->assertNull($result['nextAction']);
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
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeUserBranch', $result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertNull($result['activeUserBranch']);
        $this->assertNull($result['nextAction']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Organization::class, $result['organization']);
    }

    #[Test]
    public function execute_returns_null_next_action_when_no_uncommitted_changes_exist(): void
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

        // コミットを作成
        $commit = Commit::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'user_id' => $user->id,
        ]);

        // commit_idがnullではないedit_start_versionを作成
        EditStartVersion::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'commit_id' => $commit->id, // コミット済み
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertNull($result['nextAction']);
    }

    #[Test]
    public function execute_returns_create_initial_commit_when_uncommitted_changes_exist_and_no_opened_pr(): void
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

        // commit_idがnullのedit_start_versionを作成（未コミットの変更）
        EditStartVersion::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'commit_id' => null,
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertEquals('create_initial_commit', $result['nextAction']);
    }

    #[Test]
    public function execute_returns_create_subsequent_commit_when_uncommitted_changes_and_opened_pr_exist(): void
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

        // commit_idがnullのedit_start_versionを作成（未コミットの変更）
        EditStartVersion::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'commit_id' => null,
        ]);

        // OPENEDステータスのプルリクエストを作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertEquals('create_subsequent_commit', $result['nextAction']);
    }

    #[Test]
    public function execute_returns_create_initial_commit_when_uncommitted_changes_exist_and_pr_is_closed(): void
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

        // commit_idがnullのedit_start_versionを作成（未コミットの変更）
        EditStartVersion::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'commit_id' => null,
        ]);

        // CLOSEDステータスのプルリクエストを作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::CLOSED->value,
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('nextAction', $result);
        $this->assertEquals('create_initial_commit', $result['nextAction']);
    }

    #[Test]
    public function execute_loads_only_opened_pull_requests_when_active_branch_exists(): void
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

        // OPENEDステータスのプルリクエストを作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // CLOSEDステータスのプルリクエストを作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::CLOSED->value,
        ]);

        // MERGEDステータスのプルリクエストを作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::MERGED->value,
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        // pullRequestsリレーションがロードされていることを確認
        $this->assertTrue($result['activeUserBranch']->relationLoaded('pullRequests'));
        // OPENED状態のプルリクエストのみがロードされていることを確認
        $this->assertCount(1, $result['activeUserBranch']->pullRequests);
        $this->assertEquals(PullRequestStatus::OPENED->value, $result['activeUserBranch']->pullRequests->first()->status);
    }

    #[Test]
    public function execute_loads_empty_pull_requests_when_no_opened_pr_exists(): void
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

        // CLOSEDステータスのプルリクエストのみ作成
        PullRequest::factory()->create([
            'user_branch_id' => $activeUserBranch->id,
            'organization_id' => $organization->id,
            'status' => PullRequestStatus::CLOSED->value,
        ]);

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertInstanceOf(UserBranch::class, $result['activeUserBranch']);
        // pullRequestsリレーションがロードされていることを確認
        $this->assertTrue($result['activeUserBranch']->relationLoaded('pullRequests'));
        // OPENED状態のプルリクエストがないため、空のコレクションであることを確認
        $this->assertCount(0, $result['activeUserBranch']->pullRequests);
    }

    #[Test]
    public function execute_does_not_load_pull_requests_when_no_active_branch_exists(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        // アクティブなブランチを作成しない

        // Act
        $result = $this->useCase->execute($user, $this->userBranchService);

        // Assert
        $this->assertNull($result['activeUserBranch']);
        $this->assertNull($result['nextAction']);
    }
}
