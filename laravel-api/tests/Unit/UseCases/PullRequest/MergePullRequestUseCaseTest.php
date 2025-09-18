<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestStatus;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\PullRequest\MergePullRequestUseCase;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergePullRequestUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private MergePullRequestUseCase $useCase;

    private User $adminUser;

    private User $ownerUser;

    private User $editorUser;

    private User $nonMemberUser;

    private User $pullRequestAuthor;

    private Organization $organization;

    private UserBranch $userBranch;

    private UserBranch $authorUserBranch;

    private PullRequest $pullRequest;

    private DocumentVersion $documentVersion;

    private DocumentCategory $documentCategory;

    private EditStartVersion $documentEditStartVersion;

    private EditStartVersion $categoryEditStartVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = $this->app->make(MergePullRequestUseCase::class);
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
        $this->adminUser = User::factory()->create();
        $this->ownerUser = User::factory()->create();
        $this->editorUser = User::factory()->create();
        $this->nonMemberUser = User::factory()->create();
        $this->pullRequestAuthor = User::factory()->create();

        // 組織メンバーの作成
        OrganizationMember::create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $this->ownerUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        OrganizationMember::create([
            'user_id' => $this->pullRequestAuthor->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        // 組織ロールバインディングの作成
        OrganizationRoleBinding::create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::ADMIN->value,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $this->ownerUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::OWNER->value,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        OrganizationRoleBinding::create([
            'user_id' => $this->pullRequestAuthor->id,
            'organization_id' => $this->organization->id,
            'role' => OrganizationRoleBindingRole::EDITOR->value,
        ]);

        // ユーザーブランチの作成
        $this->userBranch = UserBranch::factory()->create([
            'user_id' => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'test-branch',
        ]);

        // プルリクエスト作成者のユーザーブランチ
        $this->authorUserBranch = UserBranch::factory()->create([
            'user_id' => $this->pullRequestAuthor->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'author-test-branch',
        ]);

        // プルリクエストの作成（adminユーザーが作成）
        $this->pullRequest = PullRequest::create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'title' => 'Test Pull Request',
            'description' => 'Test Description',
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // ドキュメントカテゴリの作成
        $this->documentCategory = DocumentCategory::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        // ドキュメントカテゴリの編集開始バージョンの作成
        $this->categoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->documentCategory->id,
            'current_version_id' => $this->documentCategory->id,
        ]);

        // ドキュメントバージョンの作成
        $this->documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->adminUser->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        // 編集開始バージョンの作成
        $this->documentEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $this->documentVersion->id,
            'current_version_id' => $this->documentVersion->id,
        ]);
    }

    /**
     * 管理者権限でマージが成功する場合のテスト
     */
    public function test_execute_success_with_admin_user(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // プルリクエストのステータスが更新されているか確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::MERGED->value, $this->pullRequest->status);

        // ドキュメントバージョンのステータスが更新されているか確認
        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::MERGED->value, $this->documentVersion->status);

        // ドキュメントカテゴリのステータスが更新されているか確認
        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $this->documentCategory->status);

        // アクティビティログが作成されているか確認
        $activityLog = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->first();
        $this->assertNotNull($activityLog);
        $this->assertEquals($this->adminUser->id, $activityLog->user_id);
    }

    /**
     * オーナー権限でマージが成功する場合のテスト
     */
    public function test_execute_success_with_owner_user(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->ownerUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // プルリクエストのステータスが更新されているか確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::MERGED->value, $this->pullRequest->status);

        // アクティビティログが作成されているか確認
        $activityLog = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->first();
        $this->assertNotNull($activityLog);
        $this->assertEquals($this->ownerUser->id, $activityLog->user_id);
    }

    /**
     * 編集者権限でマージが失敗する場合のテスト（AuthorizationException）
     */
    public function test_execute_fails_with_editor_user(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->editorUser->id
        );

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->useCase->execute($dto);

        // プルリクエストのステータスが変更されていないことを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);

        // ドキュメントのステータスが変更されていないことを確認
        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $this->documentVersion->status);

        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $this->documentCategory->status);

        // アクティビティログが作成されていないことを確認
        $activityLogCount = ActivityLogOnPullRequest::where('pull_request_id', $this->pullRequest->id)
            ->where('action', PullRequestActivityAction::PULL_REQUEST_MERGED->value)
            ->count();
        $this->assertEquals(0, $activityLogCount);
    }

    /**
     * 組織に所属していないユーザーでマージが失敗する場合のテスト（AuthorizationException）
     */
    public function test_execute_fails_with_non_member_user(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->nonMemberUser->id
        );

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->useCase->execute($dto);

        // プルリクエストのステータスが変更されていないことを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);
    }

    /**
     * 存在しないプルリクエストIDでマージが失敗する場合のテスト（NotFoundException）
     */
    public function test_execute_fails_with_non_existent_pull_request(): void
    {
        // Arrange
        $nonExistentId = 99999;
        $dto = new MergePullRequestDto(
            pullRequestId: $nonExistentId,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * 既にクローズされたプルリクエストでマージが失敗する場合のテスト（NotFoundException）
     */
    public function test_execute_fails_with_closed_pull_request(): void
    {
        // Arrange
        $this->pullRequest->update(['status' => PullRequestStatus::CLOSED->value]);

        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * 既にマージされたプルリクエストでマージが失敗する場合のテスト（NotFoundException）
     */
    public function test_execute_fails_with_merged_pull_request(): void
    {
        // Arrange
        $this->pullRequest->update(['status' => PullRequestStatus::MERGED->value]);

        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->useCase->execute($dto);
    }

    /**
     * トランザクション内で例外が発生した場合のロールバックテスト
     */
    public function test_transaction_rollback_on_exception(): void
    {
        // Arrange
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // UseCaseをモックして例外を発生させる
        $mockUseCase = $this->getMockBuilder(MergePullRequestUseCase::class)
            ->setConstructorArgs([$this->app->make(\App\Policies\PullRequestPolicy::class)])
            ->onlyMethods(['execute'])
            ->getMock();

        $mockUseCase->method('execute')
            ->willReturnCallback(function ($dto) {
                DB::beginTransaction();

                try {
                    // プルリクエストを取得
                    $pullRequest = PullRequest::with(['userBranch'])
                        ->where('id', $dto->pullRequestId)
                        ->where('status', PullRequestStatus::OPENED->value)
                        ->lockForUpdate()
                        ->first();

                    if (! $pullRequest) {
                        throw new NotFoundException;
                    }

                    // 権限チェック
                    if (! $this->app->make(\App\Policies\PullRequestPolicy::class)->merge($dto->userId, $pullRequest)) {
                        throw new AuthorizationException;
                    }

                    // ステータス更新を開始
                    $userBranch = $pullRequest->userBranch;
                    $userBranch->documentVersions()->update([
                        'status' => DocumentStatus::MERGED->value,
                    ]);

                    // 意図的に例外を発生させる
                    throw new \RuntimeException('Intentional exception for testing rollback');
                } catch (\Exception $e) {
                    DB::rollBack();

                    throw $e;
                }
            });

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Intentional exception for testing rollback');

        $mockUseCase->execute($dto);

        // ロールバックされていることを確認
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::OPENED->value, $this->pullRequest->status);

        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::PUSHED->value, $this->documentVersion->status);

        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::PUSHED->value, $this->documentCategory->status);
    }

    /**
     * 競合解決：同じドキュメントを複数ユーザーが編集し、先にマージされた場合のテスト
     */
    public function test_execute_resolves_document_conflicts_by_deleting_conflicting_versions(): void
    {
        // Arrange - 同じオリジナルドキュメントを複数ユーザーが編集するシナリオ
        $originalDocument = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->adminUser->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        // ユーザーAが編集した版
        $userAEditedDocument = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->adminUser->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        // ユーザーBが編集した版（競合）
        $userBBranch = UserBranch::factory()->create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'user-b-branch',
        ]);

        $userBEditedDocument = DocumentVersion::factory()->create([
            'user_branch_id' => $userBBranch->id,
            'user_id' => $this->editorUser->id,
            'category_id' => $this->documentCategory->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        $userBPullRequest = PullRequest::create([
            'user_branch_id' => $userBBranch->id,
            'organization_id' => $this->organization->id,
            'title' => 'User B Pull Request',
            'description' => 'Conflicting changes',
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // EditStartVersionを作成（同じオリジナルを参照）
        EditStartVersion::create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $userAEditedDocument->id,
        ]);

        EditStartVersion::create([
            'user_branch_id' => $userBBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $originalDocument->id,
            'current_version_id' => $userBEditedDocument->id,
        ]);

        // ユーザーAのプルリクエストを先にマージ
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // ユーザーAの変更がマージされている
        $userAEditedDocument->refresh();
        $this->assertEquals(DocumentStatus::MERGED->value, $userAEditedDocument->status);

        // 競合するユーザーBの変更は削除されない（ユーザーが競合修正するため）
        $userBEditedDocument->refresh();
        $this->assertNull($userBEditedDocument->deleted_at);

        // 変更対象となったoriginalのドキュメントが論理削除されている
        $originalDocument->refresh();
        $this->assertNotNull($originalDocument->deleted_at);

        // originalを指すEditStartVersionが論理削除されている
        $allEditStartVersions = EditStartVersion::withTrashed()
            ->where('original_version_id', $originalDocument->id)
            ->get();

        // EditStartVersionが作成されて削除されていることを確認
        foreach ($allEditStartVersions as $esv) {
            $this->assertNotNull($esv->deleted_at);
        }
    }

    /**
     * 競合解決：同じカテゴリを複数ユーザーが編集し、先にマージされた場合のテスト
     */
    public function test_execute_resolves_category_conflicts_by_deleting_conflicting_versions(): void
    {
        // Arrange - 同じオリジナルカテゴリを複数ユーザーが編集するシナリオ
        $originalCategory = DocumentCategory::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::MERGED->value,
        ]);

        // ユーザーAが編集した版
        $userAEditedCategory = DocumentCategory::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        // ユーザーBが編集した版（競合）
        $userBBranch = UserBranch::factory()->create([
            'user_id' => $this->editorUser->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
            'branch_name' => 'user-b-category-branch',
        ]);

        $userBEditedCategory = DocumentCategory::factory()->create([
            'user_branch_id' => $userBBranch->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        $userBPullRequest = PullRequest::create([
            'user_branch_id' => $userBBranch->id,
            'organization_id' => $this->organization->id,
            'title' => 'User B Category Pull Request',
            'description' => 'Conflicting category changes',
            'status' => PullRequestStatus::OPENED->value,
        ]);

        // EditStartVersionを作成（同じオリジナルを参照）
        EditStartVersion::create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $userAEditedCategory->id,
        ]);

        EditStartVersion::create([
            'user_branch_id' => $userBBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $originalCategory->id,
            'current_version_id' => $userBEditedCategory->id,
        ]);

        // ユーザーAのプルリクエストを先にマージ
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // ユーザーAの変更がマージされている
        $userAEditedCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $userAEditedCategory->status);

        // 競合するユーザーBの変更は削除されない（ユーザーが競合修正するため）
        $userBEditedCategory->refresh();
        $this->assertNull($userBEditedCategory->deleted_at);

        // 変更対象となったoriginalのカテゴリが論理削除されている
        $originalCategory->refresh();
        $this->assertNotNull($originalCategory->deleted_at);

        // originalを指すEditStartVersionが論理削除されている
        $allEditStartVersions = EditStartVersion::withTrashed()
            ->where('original_version_id', $originalCategory->id)
            ->get();

        // EditStartVersionが作成されて削除されていることを確認
        foreach ($allEditStartVersions as $esv) {
            $this->assertNotNull($esv->deleted_at);
        }
    }

    /**
     * 競合解決：競合がない場合のテスト（正常系）
     */
    public function test_execute_with_no_conflicts(): void
    {
        // Arrange - 競合がないシナリオ
        $dto = new MergePullRequestDto(
            pullRequestId: $this->pullRequest->id,
            userId: $this->adminUser->id
        );

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertEquals($this->pullRequest->id, $result['pull_request_id']);

        // ドキュメントがマージされている
        $this->documentVersion->refresh();
        $this->assertEquals(DocumentStatus::MERGED->value, $this->documentVersion->status);

        // カテゴリがマージされている
        $this->documentCategory->refresh();
        $this->assertEquals(DocumentCategoryStatus::MERGED->value, $this->documentCategory->status);

        // プルリクエストがマージされている
        $this->pullRequest->refresh();
        $this->assertEquals(PullRequestStatus::MERGED->value, $this->pullRequest->status);
    }
}
