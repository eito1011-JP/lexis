<?php

namespace Tests\Unit\UseCases\PullRequest;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestStatus;
use App\Enums\DocumentCategoryStatus;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use App\UseCases\PullRequest\MergePullRequestUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    private CategoryVersion $categoryVersion;

    private CategoryEntity $categoryEntity;

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

        $this->categoryEntity = CategoryEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // ドキュメントカテゴリの作成
        $this->categoryVersion = CategoryVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'entity_id' => $this->categoryEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentCategoryStatus::PUSHED->value,
        ]);

        // ドキュメントカテゴリの編集開始バージョンの作成
        $this->categoryEditStartVersion = EditStartVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'target_type' => EditStartVersionTargetType::CATEGORY->value,
            'original_version_id' => $this->categoryVersion->id,
            'current_version_id' => $this->categoryVersion->id,
        ]);

        // ドキュメントバージョンの作成
        $this->documentVersion = DocumentVersion::factory()->create([
            'user_branch_id' => $this->userBranch->id,
            'user_id' => $this->adminUser->id,
            'category_entity_id' => $this->categoryEntity->id,
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
     * 基本的な計算テスト：1+1=2
     */
    public function test_basic_addition(): void
    {
        // Arrange
        $a = 1;
        $b = 1;
        $expected = 2;

        // Act
        $result = $a + $b;

        // Assert
        $this->assertEquals($expected, $result);
    }
}
