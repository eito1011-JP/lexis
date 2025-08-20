<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
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

    #[Test]
    public function test_get_documents_by_category_id_reference_only_scenario()
    {
        Log::info('test_getDocumentsByCategoryId_reference_only_scenario');
        // テストデータ作成
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);

        // MERGED ドキュメント
        $mergedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // DRAFT ドキュメント（表示されない）
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId);

        // 検証
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($mergedDoc->id, $result->first()->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->first()->status);
    }

    #[Test]
    public function test_get_documents_by_category_id_reference_only_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);

        // MERGED ドキュメント
        $mergedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => $userBranch->id,
        ]);

        // edit_start_versions に登録されているドキュメント（除外される）
        $excludedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranch->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'original_version_id' => $excludedDoc->id,
            'target_type' => 'document',
            'user_branch_id' => $userBranch->id,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($mergedDoc->id, $result->first()->id);
    }

    #[Test]
    public function test_get_documents_by_category_id_first_edit_own_branch_draft()
    {
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;

        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        // 自ブランチの PUSHED（表示されない）
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::PUSHED->value,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($draftDoc->id, $result->first()->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->first()->status);
    }

    #[Test]
    public function test_get_documents_by_category_id_first_edit_other_branch_merged()
    {
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;

        // 他ブランチの MERGED（user_branch_id が異なる）
        $otherUserBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $otherBranchMerged = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'unique-slug',
        ]);

        // 本線の MERGED（user_branch_id が 1）
        $mainlineMerged = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'another-unique-slug',
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId);

        // 検証
        $this->assertCount(1, $result);
        $slugs = $result->pluck('slug')->toArray();
        $this->assertContains('unique-slug', $slugs);
        // 自ブランチの MERGED は初回編集時には表示されない
    }

    #[Test]
    public function test_get_documents_by_category_id_first_edit_slug_conflict_avoidance()
    {
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;

        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'slug' => 'conflicting-slug',
        ]);

        // 他ブランチの MERGED（同じ slug だが隠される）
        $otherUserBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'conflicting-slug',
        ]);

        // 他ブランチの MERGED（異なる slug なので表示される）
        $visibleMerged = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'visible-slug',
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId);

        // 検証
        $this->assertCount(2, $result);
        $slugs = $result->pluck('slug')->toArray();
        $this->assertContains('conflicting-slug', $slugs); // 自ブランチの DRAFT
        $this->assertContains('visible-slug', $slugs); // 他ブランチの MERGED
    }

    #[Test]
    public function test_get_documents_by_category_id_first_edit_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;

        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        // edit_start_versions に登録されているドキュメント（除外される）
        $excludedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranch->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'original_version_id' => $excludedDoc->id,
            'target_type' => 'document',
            'user_branch_id' => $userBranchId,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($draftDoc->id, $result->first()->id);
    }

    #[Test]
    public function test_get_documents_by_category_id_re_edit_pr_related_working()
    {
        $categoryId = 1;

        // 必要な依存関係を作成
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);
        $editPullRequestId = $pullRequest->id;

        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_id' => $user->id,
            'token' => 'test-token-'.uniqid(),
            'started_at' => now()->subDays(1),
            'finished_at' => null,
        ]);

        // このPRに紐づく DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
        ]);

        // このPRに紐づく PUSHED
        $pushedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::PUSHED->value,
            'pull_request_edit_session_id' => $session->id,
        ]);

        // このPRに紐づかない DRAFT（表示されない）
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => null,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(2, $result);
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($draftDoc->id, $resultIds);
        $this->assertContains($pushedDoc->id, $resultIds);
    }

    #[Test]
    public function test_get_documents_by_category_id_re_edit_stable_version_for_viewing()
    {
        $categoryId = 1;

        // 必要な依存関係を作成
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);
        $editPullRequestId = $pullRequest->id;

        // 他ブランチの MERGED（表示される）
        $otherUserBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $otherBranchMerged = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'stable-slug',
        ]);

        // 自ブランチの MERGED（表示されない）
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'own-branch-slug',
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($otherBranchMerged->id, $result->first()->id);
        $this->assertEquals('stable-slug', $result->first()->slug);
    }

    #[Test]
    public function test_get_documents_by_category_id_re_edit_slug_conflict_avoidance()
    {
        $categoryId = 1;
        $userBranchId = 1;

        // 必要な依存関係を作成
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);
        $editPullRequestId = $pullRequest->id;

        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_id' => $user->id,
            'token' => 'test-token-'.uniqid(),
            'started_at' => now()->subDays(1),
            'finished_at' => null,
        ]);

        // このPRに紐づく作業中（DRAFT）
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
            'slug' => 'conflicting-slug',
        ]);

        // 他ブランチの MERGED（同じ slug だが隠される）
        $otherUserBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'conflicting-slug',
        ]);

        // 他ブランチの MERGED（異なる slug なので表示される）
        $visibleMerged = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'visible-slug',
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(2, $result);
        $slugs = $result->pluck('slug')->toArray();
        $this->assertContains('conflicting-slug', $slugs); // 作業中
        $this->assertContains('visible-slug', $slugs); // 表示される安定版
    }

    #[Test]
    public function test_get_documents_by_category_id_re_edit_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;

        // 必要な依存関係を作成
        $user = User::factory()->create();
        $userBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $userBranchId = $userBranch->id;
        $pullRequest = PullRequest::factory()->create(['user_branch_id' => $userBranch->id]);
        $editPullRequestId = $pullRequest->id;

        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_id' => $user->id,
            'token' => 'test-token-'.uniqid(),
            'started_at' => now()->subDays(1),
            'finished_at' => null,
        ]);

        // このPRに紐づく DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
        ]);

        // edit_start_versions に登録されているドキュメント（除外される）
        $otherUserBranch = UserBranch::factory()->create(['user_id' => $user->id]);
        $excludedDoc = DocumentVersion::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'user_branch_id' => $otherUserBranch->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'original_version_id' => $excludedDoc->id,
            'target_type' => 'document',
            'user_branch_id' => $userBranchId,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($draftDoc->id, $result->first()->id);
    }
}
