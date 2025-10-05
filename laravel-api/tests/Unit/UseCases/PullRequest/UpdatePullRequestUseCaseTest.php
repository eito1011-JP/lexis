<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\UpdatePullRequestDto;
use App\Enums\PullRequestStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\PullRequestActivityLogService;
use App\UseCases\PullRequest\UpdatePullRequestUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class UpdatePullRequestUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private UpdatePullRequestUseCase $useCase;

    /** @var \Mockery\MockInterface&PullRequestActivityLogService */
    private PullRequestActivityLogService $activityLogService;

    private User $user;

    private Organization $organization;

    private UserBranch $userBranch;

    private PullRequest $pullRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // モックの作成
        $this->activityLogService = Mockery::mock(PullRequestActivityLogService::class);

        // UseCaseのインスタンス化
        $this->useCase = new UpdatePullRequestUseCase(
            $this->activityLogService
        );

        // テストデータのセットアップ
        $this->setupTestData();
    }

    /**
     * テストデータのセットアップ
     */
    private function setupTestData(): void
    {
        // 組織の作成
        $this->organization = Organization::factory()->create();

        // ユーザーの作成
        $this->user = User::factory()->create();

        // 組織メンバーの作成
        OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // ユーザーブランチの作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'test-branch',
        ]);

        // プルリクエストの作成
        $this->pullRequest = PullRequest::create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => PullRequestStatus::OPENED->value,
        ]);
    }

    /**
     * 正常系：タイトルのみ更新できる
     */
    public function test_successfully_update_title_only(): void
    {
        // Arrange
        $newTitle = 'Updated Title';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: $newTitle,
            description: null,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->once()
            ->with($this->user, Mockery::on(function ($pr) {
                return $pr->id === $this->pullRequest->id;
            }), $newTitle);

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals($newTitle, $this->pullRequest->title);
        $this->assertEquals('Original Description', $this->pullRequest->description);
    }

    /**
     * 正常系：descriptionのみ更新できる
     */
    public function test_successfully_update_description_only(): void
    {
        // Arrange
        $newDescription = 'Updated Description';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: null,
            description: $newDescription,
        );

        // モックの設定：titleがnullの場合はcreateTitleEditLogが呼ばれない
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals('Original Title', $this->pullRequest->title);
        $this->assertEquals($newDescription, $this->pullRequest->description);
    }

    /**
     * 正常系：タイトルとdescriptionの両方を更新できる
     */
    public function test_successfully_update_both_title_and_description(): void
    {
        // Arrange
        $newTitle = 'Updated Title';
        $newDescription = 'Updated Description';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: $newTitle,
            description: $newDescription,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->once()
            ->with($this->user, Mockery::on(function ($pr) {
                return $pr->id === $this->pullRequest->id;
            }), $newTitle);

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals($newTitle, $this->pullRequest->title);
        $this->assertEquals($newDescription, $this->pullRequest->description);
    }

    /**
     * エッジケース：タイトルとdescriptionの両方がnullの場合、何も更新されない
     */
    public function test_no_update_when_both_title_and_description_are_null(): void
    {
        // Arrange
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: null,
            description: null,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals('Original Title', $this->pullRequest->title);
        $this->assertEquals('Original Description', $this->pullRequest->description);
    }


    /**
     * エッジケース：空文字列でdescriptionを更新できる
     */
    public function test_successfully_update_description_with_empty_string(): void
    {
        // Arrange
        $newDescription = '';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: null,
            description: $newDescription,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals('Original Title', $this->pullRequest->title);
        $this->assertEquals('', $this->pullRequest->description);
    }

    /**
     * 異常系：存在しないプルリクエストIDを指定した場合、例外がスローされる
     */
    public function test_throws_exception_when_pull_request_does_not_exist(): void
    {
        // Arrange
        $nonExistentId = 99999;
        $dto = new UpdatePullRequestDto(
            pullRequestId: $nonExistentId,
            title: 'Updated Title',
            description: null,
        );

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    /**
     * 異常系：トランザクション処理でロールバックが正常に動作する
     */
    public function test_rollback_on_exception(): void
    {
        // Arrange
        $newTitle = 'Updated Title';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: $newTitle,
            description: null,
        );

        $originalTitle = $this->pullRequest->title;
        $originalDescription = $this->pullRequest->description;

        // モックの設定：例外をスロー
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->once()
            ->andThrow(new \Exception('Activity log creation failed'));

        // Act & Assert
        try {
            $this->useCase->execute($dto, $this->user);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // 例外がキャッチされることを確認
            $this->assertEquals('Activity log creation failed', $e->getMessage());
        }

        // トランザクションがロールバックされ、データが変更されていないことを確認
        $this->pullRequest->refresh();
        $this->assertEquals($originalTitle, $this->pullRequest->title);
        $this->assertEquals($originalDescription, $this->pullRequest->description);
    }

    /**
     * エッジケース：タイトルとdescriptionの両方に特殊文字を含む更新ができる
     */
    public function test_successfully_update_with_special_characters(): void
    {
        // Arrange
        $specialTitle = 'Test <>&"\'タイトル🎉';
        $specialDescription = 'Test <>&"\'説明🎉';
        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: $specialTitle,
            description: $specialDescription,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->once()
            ->with($this->user, Mockery::on(function ($pr) {
                return $pr->id === $this->pullRequest->id;
            }), $specialTitle);

        // Act
        $this->useCase->execute($dto, $this->user);

        // Assert
        $this->pullRequest->refresh();
        $this->assertEquals($specialTitle, $this->pullRequest->title);
        $this->assertEquals($specialDescription, $this->pullRequest->description);
    }

    /**
     * 異常系：ユーザーが組織に所属していない場合、例外がスローされる
     */
    public function test_throws_exception_when_user_does_not_belong_to_any_organization(): void
    {
        // Arrange
        $userWithoutOrganization = User::factory()->create();

        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            title: 'Updated Title',
            description: null,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto, $userWithoutOrganization);
    }

    /**
     * 異常系：異なる組織のプルリクエストを更新しようとした場合、例外がスローされる
     */
    public function test_throws_exception_when_trying_to_update_pull_request_from_different_organization(): void
    {
        // Arrange
        // 別の組織とユーザーを作成
        $anotherOrganization = Organization::factory()->create();
        $anotherUser = User::factory()->create();

        OrganizationMember::create([
            'user_id' => $anotherUser->id,
            'organization_id' => $anotherOrganization->id,
            'joined_at' => now(),
        ]);

        $dto = new UpdatePullRequestDto(
            pullRequestId: $this->pullRequest->id, // 元の組織のプルリクエスト
            title: 'Updated Title',
            description: null,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->useCase->execute($dto, $anotherUser); // 別の組織のユーザーで実行
    }

    /**
     * 異常系：プルリクエストが存在するが、ユーザーの組織に属していない場合
     */
    public function test_throws_exception_when_pull_request_exists_but_not_in_user_organization(): void
    {
        // Arrange
        // 別の組織のプルリクエストを作成
        $anotherOrganization = Organization::factory()->create();
        $anotherUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
            'is_active' => false,
            'branch_name' => 'another-branch',
        ]);

        $anotherPullRequest = PullRequest::create([
            'user_branch_id' => $anotherUserBranch->id,
            'organization_id' => $anotherOrganization->id,
            'title' => 'Another Pull Request',
            'description' => 'Another Description',
            'status' => PullRequestStatus::OPENED->value,
        ]);

        $dto = new UpdatePullRequestDto(
            pullRequestId: $anotherPullRequest->id,
            title: 'Updated Title',
            description: null,
        );

        // モックの設定
        $this->activityLogService
            ->shouldReceive('createTitleEditLog')
            ->never();

        // Act & Assert
        // ユーザーは$this->organizationに所属しているが、プルリクエストは$anotherOrganizationのもの
        $this->expectException(ModelNotFoundException::class);
        $this->useCase->execute($dto, $this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
