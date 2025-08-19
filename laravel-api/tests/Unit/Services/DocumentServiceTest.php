<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentService = new DocumentService(new DocumentCategoryService());
    }

    /**
     * @test
     * 参照のみ（user_branch_id なし）の場合
     */
    public function test_getDocumentsByCategoryId_reference_only_scenario()
    {
        // テストデータ作成
        $categoryId = 1;
        
        // MERGED ドキュメント
        $mergedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);
        
        // DRAFT ドキュメント（表示されない）
        DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => 1,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId);

        // 検証
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($mergedDoc->id, $result->first()->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->first()->status);
    }

    /**
     * @test
     * edit_start_versions による除外条件のテスト（参照のみ）
     */
    public function test_getDocumentsByCategoryId_reference_only_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;
        
        // MERGED ドキュメント
        $mergedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);
        
        // edit_start_versions に登録されているドキュメント（除外される）
        $excludedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'status' => DocumentStatus::MERGED->value,
            'user_branch_id' => null,
        ]);
        
        EditStartVersion::factory()->create([
            'original_version_id' => $excludedDoc->id,
            'target_type' => 'document',
            'user_branch_id' => null,
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($mergedDoc->id, $result->first()->id);
    }

    /**
     * @test
     * 初回編集時（user_branch_id のみ存在）- 自ブランチの編集中
     */
    public function test_getDocumentsByCategoryId_first_edit_own_branch_draft()
    {
        $categoryId = 1;
        $userBranchId = 1;
        
        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        
        // 自ブランチの PUSHED（表示されない）
        DocumentVersion::factory()->create([
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

    /**
     * @test
     * 初回編集時（user_branch_id のみ存在）- 他ブランチの安定版
     */
    public function test_getDocumentsByCategoryId_first_edit_other_branch_merged()
    {
        $categoryId = 1;
        $userBranchId = 1;
        
        // 他ブランチの MERGED（user_branch_id が異なる）
        $otherBranchMerged = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'unique-slug',
        ]);
        
        // 本線の MERGED（user_branch_id が null）
        $mainlineMerged = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => null,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'another-unique-slug',
        ]);

        // 実行
        $result = $this->documentService->getDocumentsByCategoryId($categoryId, $userBranchId);

        // 検証
        $this->assertCount(2, $result);
        $slugs = $result->pluck('slug')->toArray();
        $this->assertContains('unique-slug', $slugs);
        $this->assertContains('another-unique-slug', $slugs);
    }

    /**
     * @test
     * 初回編集時（user_branch_id のみ存在）- slug 衝突回避
     */
    public function test_getDocumentsByCategoryId_first_edit_slug_conflict_avoidance()
    {
        $categoryId = 1;
        $userBranchId = 1;
        
        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'slug' => 'conflicting-slug',
        ]);
        
        // 他ブランチの MERGED（同じ slug だが隠される）
        DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'conflicting-slug',
        ]);
        
        // 他ブランチの MERGED（異なる slug なので表示される）
        $visibleMerged = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
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

    /**
     * @test
     * 初回編集時（user_branch_id のみ存在）- edit_start_versions による除外
     */
    public function test_getDocumentsByCategoryId_first_edit_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;
        $userBranchId = 1;
        
        // 自ブランチの DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
        ]);
        
        // edit_start_versions に登録されているドキュメント（除外される）
        $excludedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => null,
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

    /**
     * @test
     * 再編集時（user_branch_id && edit_pull_request_id が存在）- PR紐づき作業中
     */
    public function test_getDocumentsByCategoryId_re_edit_pr_related_working()
    {
        $categoryId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_branch_id' => $userBranchId,
        ]);
        
        // このPRに紐づく DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
        ]);
        
        // このPRに紐づく PUSHED
        $pushedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::PUSHED->value,
            'pull_request_edit_session_id' => $session->id,
        ]);
        
        // このPRに紐づかない DRAFT（表示されない）
        DocumentVersion::factory()->create([
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

    /**
     * @test
     * 再編集時（user_branch_id && edit_pull_request_id が存在）- 閲覧用安定版
     */
    public function test_getDocumentsByCategoryId_re_edit_stable_version_for_viewing()
    {
        $categoryId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // 他ブランチの MERGED（表示される）
        $otherBranchMerged = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'stable-slug',
        ]);
        
        // 自ブランチの MERGED（表示されない）
        DocumentVersion::factory()->create([
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

    /**
     * @test
     * 再編集時（user_branch_id && edit_pull_request_id が存在）- slug 衝突回避
     */
    public function test_getDocumentsByCategoryId_re_edit_slug_conflict_avoidance()
    {
        $categoryId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_branch_id' => $userBranchId,
        ]);
        
        // このPRに紐づく作業中（DRAFT）
        DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
            'slug' => 'conflicting-slug',
        ]);
        
        // 他ブランチの MERGED（同じ slug だが隠される）
        DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
            'status' => DocumentStatus::MERGED->value,
            'slug' => 'conflicting-slug',
        ]);
        
        // 他ブランチの MERGED（異なる slug なので表示される）
        $visibleMerged = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
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

    /**
     * @test
     * 再編集時（user_branch_id && edit_pull_request_id が存在）- edit_start_versions による除外
     */
    public function test_getDocumentsByCategoryId_re_edit_with_edit_start_versions_exclusion()
    {
        $categoryId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // PR編集セッション
        $session = PullRequestEditSession::factory()->create([
            'pull_request_id' => $editPullRequestId,
            'user_branch_id' => $userBranchId,
        ]);
        
        // このPRに紐づく DRAFT
        $draftDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => $userBranchId,
            'status' => DocumentStatus::DRAFT->value,
            'pull_request_edit_session_id' => $session->id,
        ]);
        
        // edit_start_versions に登録されているドキュメント（除外される）
        $excludedDoc = DocumentVersion::factory()->create([
            'category_id' => $categoryId,
            'user_branch_id' => 2,
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
