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

class UserBranchServiceDeactivateTest extends TestCase
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
    public function deactivate_user_branch_アクティブなユーザーブランチを正常に非アクティブにできる()
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->deactivateUserBranch($userBranch->id);

        // Assert
        $this->assertTrue($result);

        // データベースの状態を確認
        $userBranch->refresh();
        $this->assertEquals(Flag::FALSE, $userBranch->is_active);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_存在しないユーザーブランチ_i_dの場合は_not_found_exception例外がスローされる()
    {
        // Arrange
        $nonExistentUserBranchId = 99999;

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->deactivateUserBranch($nonExistentUserBranchId);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_既に非アクティブなユーザーブランチの場合は_not_found_exception例外がスローされる()
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::FALSE,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->deactivateUserBranch($userBranch->id);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_削除されたユーザーブランチの場合は_not_found_exception例外がスローされる()
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
        $this->service->deactivateUserBranch($userBranch->id);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_複数のアクティブなユーザーブランチがある場合でも指定されたブランチのみ非アクティブにできる()
    {
        // Arrange
        $userBranch1 = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        $userBranch2 = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->deactivateUserBranch($userBranch1->id);

        // Assert
        $this->assertTrue($result);

        // データベースの状態を確認
        $userBranch1->refresh();
        $userBranch2->refresh();
        $this->assertEquals(Flag::FALSE, $userBranch1->is_active);
        $this->assertEquals(Flag::TRUE, $userBranch2->is_active); // 他のブランチはアクティブのまま
    }

    /**
     * @test
     */
    public function deactivate_user_branch_別の組織のアクティブなユーザーブランチも正常に非アクティブにできる()
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->deactivateUserBranch($userBranch->id);

        // Assert
        $this->assertTrue($result);

        // データベースの状態を確認
        $userBranch->refresh();
        $this->assertEquals(Flag::FALSE, $userBranch->is_active);
        $this->assertEquals($anotherOrganization->id, $userBranch->organization_id);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_別のユーザーのアクティブなユーザーブランチも正常に非アクティブにできる()
    {
        // Arrange
        $anotherUser = User::factory()->create();
        $userBranch = UserBranch::factory()->create([
            'user_id' => $anotherUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        // Act
        $result = $this->service->deactivateUserBranch($userBranch->id);

        // Assert
        $this->assertTrue($result);

        // データベースの状態を確認
        $userBranch->refresh();
        $this->assertEquals(Flag::FALSE, $userBranch->is_active);
        $this->assertEquals($anotherUser->id, $userBranch->user_id);
    }

    /**
     * @test
     */
    public function deactivate_user_branch_updated_atフィールドが更新される()
    {
        // Arrange
        $userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => Flag::TRUE,
        ]);

        $originalUpdatedAt = $userBranch->updated_at;

        // 1秒待機してタイムスタンプの差を確実にする
        sleep(1);

        // Act
        $result = $this->service->deactivateUserBranch($userBranch->id);

        // Assert
        $this->assertTrue($result);

        // データベースの状態を確認
        $userBranch->refresh();
        $this->assertEquals(Flag::FALSE, $userBranch->is_active);
        $this->assertNotEquals($originalUpdatedAt, $userBranch->updated_at);
        $this->assertTrue($userBranch->updated_at->gt($originalUpdatedAt));
    }
}
