<?php

namespace App\Services;

use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\FixRequestStatus;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestEditSessionDiffTargetType;
use App\Enums\PullRequestStatus;
use App\Enums\UserRole;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Constants\DocumentCategoryConstants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PullRequestMergeService
{
    protected GitService $gitService;
    protected MdFileService $mdFileService;
    protected CategoryFolderService $categoryFolderService;

    public function __construct(
        GitService $gitService,
        MdFileService $mdFileService,
        CategoryFolderService $categoryFolderService
    ) {
        $this->gitService = $gitService;
        $this->mdFileService = $mdFileService;
        $this->categoryFolderService = $categoryFolderService;
    }

    /**
     * プルリクエストのマージを実行
     * 
     * @param int $pullRequestId
     * @param int $userId
     * @return array
     */
    public function mergePullRequest(int $pullRequestId, int $userId): array
    {
        DB::beginTransaction();

        try {
            // プルリクエストを取得
            $pullRequest = PullRequest::with(['userBranch', 'pullRequestEditSessions.editSessionDiffs', 'fixRequests'])
                ->where('id', $pullRequestId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->firstOrFail();

            // PR提出時から差分が変わっているかをチェック
            $pullRequestEditSessions = $pullRequest->pullRequestEditSessions()
                ->whereNotNull('finished_at')
                ->get();

            $appliedFixRequests = $pullRequest->fixRequests()
                ->where('status', FixRequestStatus::APPLIED->value)
                ->get();

            // PR提出時と差分が変わっていない場合
            if ($pullRequestEditSessions->isEmpty() && $appliedFixRequests->isEmpty()) {
                // GitHub APIでプルリクエストをマージ（squashマージを使用）
                $this->gitService->mergePullRequest($pullRequest->pr_number, 'squash');
            } else {
                // PR提出時から差分が変更されている場合
                $this->processMergeWithChanges($pullRequest, $pullRequestEditSessions, $appliedFixRequests);
            }

            // プルリクエストに紐づくuser_branchesテーブルを取得し、
            // それに紐づくdocument_versionsとdocument_categoriesのstatusをmergedにupdate
            $userBranch = $pullRequest->userBranch;

            // DocumentVersionsのstatusを更新
            $userBranch->documentVersions()->update([
                'status' => DocumentStatus::MERGED->value,
            ]);

            // DocumentCategoriesのstatusを更新
            $userBranch->documentCategories()->update([
                'status' => DocumentCategoryStatus::MERGED->value,
            ]);

            // pull_requestsテーブルのstatusをmergedにupdate
            $pullRequest->update([
                'status' => PullRequestStatus::MERGED->value,
            ]);

            // ActivityLogを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $userId,
                'pull_request_id' => $pullRequest->id,
                'action' => PullRequestActivityAction::PULL_REQUEST_MERGED->value,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'プルリクエストが正常にマージされました',
                'pull_request_id' => $pullRequest->id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエストマージエラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $pullRequestId,
            ]);

            throw $e;
        }
    }

    /**
     * 差分が変更されている場合のマージ処理
     */
    private function processMergeWithChanges($pullRequest, $pullRequestEditSessions, $appliedFixRequests): void
    {
        // プルリクエストに紐づくdocument_versionsとdocument_categoriesを取得
        $userBranch = $pullRequest->userBranch;
        $documentVersions = $userBranch->documentVersions()->get();
        $documentCategories = $userBranch->documentCategories()->get();

        Log::info('userBranchId: '.$userBranch->id);
        Log::info('pullRequestId: '.$pullRequest->id);

        // applied fix requestのversion_idを取得
        $appliedDocumentVersionIds = [];
        $appliedCategoryVersionIds = [];
        if ($appliedFixRequests->isNotEmpty()) {
            $appliedDocumentVersionIds = $appliedFixRequests->pluck('document_version_id')->filter()->toArray();
            $appliedCategoryVersionIds = $appliedFixRequests->pluck('document_category_id')->filter()->toArray();
        }

        // 再編集されたdocumentとcategoryのidを取得
        $reEditDocumentVersionsIds = [];
        $reEditCategoryVersionsIds = [];
        if ($pullRequestEditSessions->isNotEmpty()) {
            $pullRequestEditSessionDiffs = $pullRequestEditSessions->flatMap(function ($session) {
                return $session->editSessionDiffs;
            });

            Log::info('pullRequestEditSessionDiffIds: '.json_encode($pullRequestEditSessionDiffs->pluck('id')));
            $reEditDocumentVersionsIds = $pullRequestEditSessionDiffs
                ->where('target_type', PullRequestEditSessionDiffTargetType::DOCUMENT->value)
                ->pluck('current_version_id')
                ->filter()
                ->toArray();
            
            $reEditCategoryVersionsIds = $pullRequestEditSessionDiffs
                ->where('target_type', PullRequestEditSessionDiffTargetType::CATEGORY->value)
                ->pluck('current_version_id')
                ->filter()
                ->toArray();
        }

        // 新しいバージョンIDをマージ
        $newDocumentVersionIds = array_merge($appliedDocumentVersionIds, $reEditDocumentVersionsIds);
        $newCategoryVersionIds = array_merge($appliedCategoryVersionIds, $reEditCategoryVersionsIds);

        Log::info('newDocumentVersionIds: '.json_encode($newDocumentVersionIds));
        Log::info('newCategoryVersionIds: '.json_encode($newCategoryVersionIds));

        // 対象のdocumentVersionsとcategoryVersionsを取得
        $targetDocumentVersionIds = DocumentVersion::withTrashed()->whereIn('id', $newDocumentVersionIds)
            ->whereHas('currentEditStartVersions', function ($query) use ($userBranch) {
                $query->where('user_branch_id', $userBranch->id);
            })
            ->pluck('id');

        $targetCategoryVersionIds = DocumentCategory::withTrashed()->whereIn('id', $newCategoryVersionIds)
            ->whereHas('currentEditStartVersions', function ($query) use ($userBranch) {
                $query->where('user_branch_id', $userBranch->id);
            })
            ->pluck('id');

        $targetDocumentVersions = DocumentVersion::whereIn('id', $targetDocumentVersionIds)->withTrashed()->get();
        $targetCategoryVersions = DocumentCategory::whereIn('id', $targetCategoryVersionIds)->withTrashed()->get();

        $treeItems = [];

        // documentVersionsを処理
        foreach ($targetDocumentVersions as $documentVersion) {
            $markdownContent = $this->mdFileService->createMdFileContent($documentVersion);
            $filePath = $this->mdFileService->generateFilePath($documentVersion->slug, $documentVersion->category_path);
            
            $treeItems[] = [
                'path' => $filePath,
                'mode' => '100644',
                'type' => 'blob',
                'content' => $markdownContent,
            ];
        }

        // categoryVersionsを処理
        foreach ($targetCategoryVersions as $documentCategory) {
            $categoryJsonData = [
                'label' => $documentCategory->sidebar_label,
                'position' => $documentCategory->position,
                'link' => [
                    'type' => 'generated-index',
                    'description' => $documentCategory->description,
                ],
            ];
            
            $categoryJsonContent = json_encode($categoryJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $categoryFolderPath = $this->categoryFolderService->generateCategoryFilePath(
                $documentCategory->slug, 
                $documentCategory->parent_id && $documentCategory->parent_id !== DocumentCategoryConstants::DEFAULT_CATEGORY_ID 
                    ? $documentCategory->parent_id 
                    : null
            );
            
            // _category_.jsonファイルを作成
            $treeItems[] = [
                'path' => $categoryFolderPath.'/_category_.json',
                'mode' => '100644',
                'type' => 'blob',
                'content' => $categoryJsonContent,
            ];
            
            // .gitkeepファイルを作成（空のフォルダをGitで追跡するため）
            $treeItems[] = [
                'path' => $categoryFolderPath.'/.gitkeep',
                'mode' => '100644',
                'type' => 'blob',
                'content' => '',
            ];
        }

        // プルリクエストブランチを最新状態に更新（mainブランチの変更を取り込む）
        $this->gitService->updatePullRequestBranch($pullRequest->pr_number);

        // 現在のブランチの最新コミットを取得
        $currentBranchRef = $this->gitService->getBranchReference($userBranch->branch_name);
        $currentBranchSha = $currentBranchRef['object']['sha'];

        Log::info('currentBranchRef: '.json_encode($currentBranchRef));
        Log::info('currentBranchSha: '.$currentBranchSha);
        Log::info('treeItems: '.json_encode($treeItems));

        // treeを作成して、リモートブランチのファイルを編集
        $treeResult = $this->gitService->createTree(
            $currentBranchSha,
            $treeItems
        );

        // コミット作成
        $commitResult = $this->gitService->createCommit(
            $pullRequest->title,
            $treeResult['sha'],
            [$currentBranchSha]
        );

        // ブランチの最新コミットを更新
        $this->gitService->updateBranchReference(
            $userBranch->branch_name,
            $commitResult['sha']
        );

        // プルリクエストがマージ可能かを確認
        $prInfo = $this->gitService->getPullRequestInfo($pullRequest->pr_number);

        if ($prInfo['mergeable']) {
            // GitHub APIでプルリクエストをマージ（squashマージを使用）
            $this->gitService->mergePullRequest($pullRequest->pr_number, 'squash');
        }
    }
}