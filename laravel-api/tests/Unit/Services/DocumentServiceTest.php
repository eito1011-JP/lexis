<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentService = new DocumentService(new DocumentCategoryService);
    }

    /**
     * 基本的なテスト環境をセットアップする
     */
    private function setupBasicTestEnvironmentWhenPrDoesNotExist(): array
    {
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return [
            'user' => $user,
            'userBranch' => $userBranch,
        ];
    }

    /**
     * 基本的なテスト環境をセットアップする
     */
    private function setupBasicTestEnvironmentWhenPrExists(): array
    {
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);

        return [
            'user' => $user,
            'userBranch' => $userBranch,
            'pullRequest' => $pullRequest,
        ];
    }

    private function setupBasicTestEnvironmentWhenPrExistsBySameUser(User $user): array
    {
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);

        return [
            'user' => $user,
            'userBranch' => $userBranch,
            'pullRequest' => $pullRequest,
        ];
    }

    /**
     * ドキュメントを作成する
     */
    private function createDocument(array $env, int $categoryId = 1, ?PullRequestEditSession $editSession = null, string $slug = 'policy', string $status = DocumentStatus::DRAFT->value): DocumentVersion
    {
        $document = DocumentVersion::factory()->create([
            'user_id' => $env['user']->id,
            'user_branch_id' => $env['userBranch']->id,
            'category_id' => $categoryId,
            'status' => $status,
            'slug' => $slug,
            'pull_request_edit_session_id' => $editSession ? $editSession->id : null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $env['userBranch']->id,
            'target_type' => 'document',
            'original_version_id' => $document->id,
            'current_version_id' => $document->id,
        ]);

        return $document;
    }

    /**
     * プルリクエスト編集セッションを作成する
     */
    private function createPullRequestEditSession(array $env): PullRequestEditSession
    {
        return PullRequestEditSession::factory()->create([
            'pull_request_id' => $env['pullRequest']->id,
            'user_id' => $env['user']->id,
            'token' => 'test_token',
            'started_at' => now(),
        ]);
    }

    /**
     * プルリクエスト編集セッションの差分を作成する
     */
    private function createEditSessionDiff(PullRequestEditSession $editSession, DocumentVersion $original, DocumentVersion $current, string $diffType = 'created'): void
    {
        PullRequestEditSessionDiff::factory()->create([
            'pull_request_edit_session_id' => $editSession->id,
            'target_type' => 'document',
            'original_version_id' => $original->id,
            'current_version_id' => $current->id,
            'diff_type' => $diffType,
        ]);
    }

    /**
     * 他のユーザーのドキュメントを作成する
     */
    private function createOtherUserDocument(array $env, int $categoryId = 1, string $status = DocumentStatus::DRAFT->value, ?int $editSessionId = null, string $slug = 'policy', bool $isReEdit = false): DocumentVersion
    {
        if ($isReEdit) {
            $editSession = $this->createPullRequestEditSession($env);
            $editSessionId = $editSession->id;
        }

        $otherUserDocument = DocumentVersion::factory()->create([
            'user_id' => $env['user']->id,
            'user_branch_id' => $env['userBranch']->id,
            'category_id' => $categoryId,
            'status' => $status,
            'slug' => $slug,
            'pull_request_edit_session_id' => $editSessionId ?? null,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $env['userBranch']->id,
            'target_type' => 'document',
            'original_version_id' => $otherUserDocument->id,
            'current_version_id' => $otherUserDocument->id,
        ]);

        return $otherUserDocument;
    }

    /**
     * 再編集モードのテスト環境をセットアップする
     */
    private function setupEditAgainEnvironment(): array
    {
        $env = $this->setupBasicTestEnvironmentWhenPrExists();
        $original = $this->createDocument($env, 1, null, 'policy', DocumentStatus::PUSHED->value);
        $editSession = $this->createPullRequestEditSession($env);

        return [
            'env' => $env,
            'original' => $original,
            'editSession' => $editSession,
        ];
    }

    /**
     * 再編集モードでドラフトなしのテスト環境をセットアップする
     */
    private function setupEditAgainNoDraftEnvironment(): array
    {
        $env = $this->setupBasicTestEnvironmentWhenPrExists();
        $original = $this->createDocument($env, 1, null, 'policy', DocumentStatus::PUSHED->value);

        return [
            'env' => $env,
            'original' => $original,
        ];
    }

    /**
     * 変更提案提出前でドラフトありのテスト環境をセットアップする
     *
     * draftがない状態でuser_branchが存在することはない
     */
    private function setupBeforeSubmitPrWithDraftEnvironment(): array
    {
        $env = $this->setupBasicTestEnvironmentWhenPrDoesNotExist();
        $draft = $this->createDocument($env, 1, null, 'policy', DocumentStatus::DRAFT->value); // 編集セッションなし

        return [
            'env' => $env,
            'draft' => $draft,
        ];
    }

    /**
     * ドラフトドキュメントを作成する（編集セッションなし）
     */
    private function createDraftDocumentWithoutSession(array $env, int $categoryId = 1, string $slug = 'policy'): DocumentVersion
    {
        return DocumentVersion::factory()->create([
            'user_id' => $env['user']->id,
            'user_branch_id' => $env['userBranch']->id,
            'category_id' => $categoryId,
            'status' => DocumentStatus::DRAFT->value,
            'slug' => $slug,
            'pull_request_edit_session_id' => null,
        ]);
    }

    // ① 再編集モード（userBranchId と editPullRequestId の両方あり）
    #[Test]
    public function case1_edit_again_with_draft()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 再編集セッションで作成されたドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($setup['original']->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case2_edit_again_with_draft_and_other_user_submitted_draft()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        // 他のユーザーが作成したドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 自分のdraftのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case3_edit_again_with_draft_and_other_user_submitted_pushed()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        // 他のユーザーが作成したドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 自分のdraftのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case4_edit_again_with_draft_and_other_user_submitted_merged()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserMerged = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 再編集セッションで作成されたドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserMerged->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case5_edit_again_with_draft_and_same_user_in_other_pr_submitted_draft()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($setup['env']['user']);
        $sameUserDraft = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::DRAFT->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 再編集セッションで作成されたドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($sameUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case6_edit_again_with_draft_and_same_user_in_other_pr_submitted_pushed()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($setup['env']['user']);
        $sameUserPushed = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 再編集セッションで作成されたドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($sameUserPushed->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case7_edit_again_with_draft_and_same_user_in_other_pr_submitted_merged()
    {
        // Arrange
        $setup = $this->setupEditAgainEnvironment();

        $draft = $this->createDocument($setup['env'], 1, $setup['editSession'], 'policy', DocumentStatus::DRAFT->value);

        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($setup['env']['user']);
        $sameUserMerged = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 再編集セッションで作成されたドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($draft->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($sameUserMerged->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case8_edit_again_with_no_draft()
    {
        $setup = $this->setupEditAgainNoDraftEnvironment();

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 変更提案前のpush済みドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['original']->id, $result->first()->id);
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case9_edit_again_with_no_draft_and_other_user_submitted_draft()
    {
        // Arrange
        $setup = $this->setupEditAgainNoDraftEnvironment();

        // 他のユーザーが作成したドラフト
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 変更提案前のpush済みドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['original']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case10_edit_again_with_no_draft_and_other_user_submitted_pushed()
    {
        // Arrange
        $setup = $this->setupEditAgainNoDraftEnvironment();

        // 他のユーザーが作成したpush済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserPushed = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 変更提案前のpush済みドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['original']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserPushed->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case11_edit_again_with_no_draft_and_other_user_submitted_merged()
    {
        // Arrange
        $setup = $this->setupEditAgainNoDraftEnvironment();

        // 他のユーザーが作成したマージ済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserMerged = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            $setup['env']['pullRequest']->id
        );

        // 変更提案前のpush済みドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['original']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserMerged->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    // 変更提案を提出する前(user_branch作成済み)
    #[Test]
    public function case12_before_submit_pr_with_draft()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null // 編集セッションなし
        );

        // ドラフトドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case13_before_submit_pr_with_draft_and_other_user_have_draft_by_reedit()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 他のユーザーが作成したドラフト
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value, null, 'policy', true);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case14_before_submit_pr_with_draft_and_other_user_have_pushed()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 他のユーザーが作成したpush済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserPushed = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserPushed->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case15_before_submit_pr_with_draft_and_other_user_have_draft_on_different_slug()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 異なるスラッグで他のユーザーが作成したドラフト
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value, null, 'handbook', false);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case16_before_submit_pr_with_draft_and_other_user_have_pushed_on_same_slug()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 同じスラッグで他のユーザーが作成したpush済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserPushed = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserPushed->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case17_before_submit_pr_with_draft_and_other_user_have_merged()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 他のユーザーが作成したマージ済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserMerged = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserMerged->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    #[Test]
    public function case18_before_submit_pr_with_draft_and_other_user_have_merged_on_same_slug()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // 他のユーザーが作成したマージ済みドキュメント
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrExists();
        $otherUserMerged = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1, // category_id
            $setup['env']['userBranch']->id,
            null
        );

        // 自分のドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($otherUserMerged->id));
        $this->assertEquals('policy', $result->first()->slug);
    }

    // 変更提案を提出する前(user_branch作成前)
    #[Test]
    public function case20_before_submit_pr_with_no_draft_and_other_user_have_draft()
    {
        // Arrange
        User::factory()->create();

        // 他のユーザーのドキュメントだけ存在する
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrDoesNotExist();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value, null, 'policy', false);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // ドキュメントが表示されない
        $this->assertCount(0, $result);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
    }

    #[Test]
    public function case21_before_submit_pr_with_no_draft_and_other_user_have_pushed()
    {
        // Arrange
        User::factory()->create();

        // 他のユーザーのドキュメントだけ存在する
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrDoesNotExist();
        $otherUserPushed = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::PUSHED->value, null, 'policy', false);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // ドキュメントが表示されない
        $this->assertCount(0, $result);
        $this->assertFalse($result->pluck('id')->contains($otherUserPushed->id));
    }

    #[Test]
    public function case22_before_submit_pr_with_no_draft_and_other_user_have_merged()
    {
        // Arrange
        User::factory()->create();

        // 他のユーザーのドキュメントだけ存在する
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrDoesNotExist();
        $otherUserMerged = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::MERGED->value, null, 'policy', false);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // mergedドキュメントが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($otherUserMerged->id, $result->first()->id);
    }

    #[Test]
    public function case23_before_submit_pr_with_no_draft_and_other_user_have_draft_on_different_slug()
    {
        // Arrange
        User::factory()->create();

        // 他のユーザーのドキュメントだけ存在する
        $setupForOtherUser = $this->setupBasicTestEnvironmentWhenPrDoesNotExist();
        $otherUserDraft = $this->createOtherUserDocument($setupForOtherUser, 1, DocumentStatus::DRAFT->value, null, 'handbook', false);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // ドキュメントが表示されない
        $this->assertCount(0, $result);
        $this->assertFalse($result->pluck('id')->contains($otherUserDraft->id));
    }

    // エッジケース
    #[Test]
    public function case27_before_submit_pr_with_no_draft_and_same_user_have_submitted_pushed()
    {
        // Arrange
        $user = User::factory()->create();
        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($user);

        $sameUserPushed = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // ドキュメントが表示されない
        $this->assertCount(0, $result);
        $this->assertFalse($result->pluck('id')->contains($sameUserPushed->id));
    }

    #[Test]
    public function case28_before_submit_pr_with_no_draft_and_same_user_have_submitted_merged()
    {
        // Arrange
        $user = User::factory()->create();
        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($user);

        $sameUserMerged = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::MERGED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            null,
            null
        );

        // ドキュメントが表示されない
        $this->assertCount(0, $result);
        $this->assertFalse($result->pluck('id')->contains($sameUserMerged->id));
    }

    #[Test]
    public function case29_before_submit_pr_with_duplicated_draft_by_same_user()
    {
        // Arrange
        $setup = $this->setupBeforeSubmitPrWithDraftEnvironment();

        // すでにpush済みのドキュメントが存在する
        $setupForSameUser = $this->setupBasicTestEnvironmentWhenPrExistsBySameUser($setup['env']['user']);
        $pushed = $this->createDocument($setupForSameUser, 1, null, 'policy', DocumentStatus::PUSHED->value);

        $result = $this->documentService->getDocumentsByCategoryId(
            1,
            $setup['env']['userBranch']->id,
            null
        );

        // 現在いるuser_branchで作成されたドラフトのみが表示される
        $this->assertCount(1, $result);
        $this->assertEquals($setup['draft']->id, $result->first()->id);
        $this->assertFalse($result->pluck('id')->contains($pushed->id));
        $this->assertEquals('policy', $result->first()->slug);
    }
}
