<?php

namespace App\UseCases\PullRequest;

use App\Constants\DocumentCategoryConstants;
use App\Consts\Flag;
use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\PullRequestStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Services\CategoryFolderService;
use App\Services\GitService;
use App\Services\MdFileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエスト作成UseCase
 */
class CreatePullRequestUseCase
{
    public function __construct(
        private GitService $gitService,
        private MdFileService $mdFileService,
        private CategoryFolderService $categoryFolderService,
    ) {}

    /**
     * プルリクエストを作成
     *
     * @param CreatePullRequestDto $dto プルリクエスト作成DTO
     * @param User $user 認証済みユーザー
     * @return array プルリクエスト作成結果
     * @throws \Exception
     */
    public function execute(CreatePullRequestDto $dto, User $user): array
    {
        Log::info('CreatePullRequestUseCase start', ['dto' => $dto->toArray(), 'user_id' => $user->id]);

        DB::beginTransaction();

        try {
            // 1. アクティブなユーザーブランチを取得
            $userBranch = $user->userBranches()
                ->active()
                ->orderBy('id', 'desc')
                ->first();

            if (!$userBranch) {
                throw new \Exception('ユーザーブランチが見つかりません');
            }

            // 2. 差分アイテムを処理
            $result = $this->processDiffItems($dto->diffItems, $userBranch->id);
            $documentVersions = $result['documentVersions'];
            $documentCategories = $result['documentCategories'];

            // 3. GitHubツリー用のアイテムを作成
            $treeItems = $this->createTreeItems($documentVersions, $documentCategories);

            // 4. GitHubでリモートブランチ作成
            $this->gitService->createRemoteBranch(
                $userBranch->branch_name,
                $userBranch->snapshot_commit
            );

            // 5. ツリー作成
            $treeResult = $this->gitService->createTree(
                $userBranch->snapshot_commit,
                $treeItems
            );

            // 6. コミット作成
            $commitResult = $this->gitService->createCommit(
                $dto->title,
                $treeResult['sha'],
                [$userBranch->snapshot_commit]
            );

            // 7. ブランチ参照更新
            $this->gitService->updateBranchReference(
                $userBranch->branch_name,
                $commitResult['sha']
            );

            // 8. プルリクエスト作成
            $prResult = $this->gitService->createPullRequest(
                $userBranch->branch_name,
                $dto->title,
                $dto->description ?? ''
            );

            // 9. レビュアー設定
            $reviewerUserIds = $this->processReviewers($dto->reviewers, $prResult['pr_number']);

            // 10. プルリクエストをDBに保存
            $pullRequest = $this->savePullRequest($userBranch, $dto, $prResult);

            // 11. レビュアーをDBに保存
            if (!empty($reviewerUserIds)) {
                $this->savePullRequestReviewers($pullRequest->id, $reviewerUserIds);
            }

            // 12. ユーザーブランチを非アクティブ化
            $userBranch->update(['is_active' => Flag::FALSE]);

            DB::commit();

            Log::info('CreatePullRequestUseCase completed successfully', [
                'pull_request_id' => $pullRequest->id,
                'pr_number' => $prResult['pr_number']
            ]);

            return [
                'success' => true,
                'message' => 'プルリクエストを作成しました',
                'pr_url' => $prResult['pr_url'],
                'pr_number' => $prResult['pr_number'],
                'pull_request_id' => $pullRequest->id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }

    /**
     * 差分アイテムを処理し、ステータスを更新
     */
    private function processDiffItems(array $diffItems, int $userBranchId): array
    {
        Log::info('Processing diff items', ['diffItems' => $diffItems]);

        $documentIds = [];
        $categoryIds = [];

        // IDを分別
        foreach ($diffItems as $item) {
            if ($item['type'] === 'document') {
                $documentIds[] = $item['id'];
            } elseif ($item['type'] === 'category') {
                $categoryIds[] = $item['id'];
            }
        }

        $documentVersions = collect();
        $documentCategories = collect();

        // ドキュメントバージョンを処理
        if (!empty($documentIds)) {
            $documentVersions = DocumentVersion::where('user_branch_id', $userBranchId)
                ->withTrashed()
                ->whereIn('id', $documentIds)
                ->get();

            DocumentVersion::where('user_branch_id', $userBranchId)
                ->whereIn('id', $documentIds)
                ->withTrashed()
                ->update(['status' => DocumentStatus::PUSHED->value]);
        }

        // ドキュメントカテゴリを処理
        if (!empty($categoryIds)) {
            $documentCategories = DocumentCategory::where('user_branch_id', $userBranchId)
                ->whereIn('id', $categoryIds)
                ->withTrashed()
                ->get();

            DocumentCategory::where('user_branch_id', $userBranchId)
                ->whereIn('id', $categoryIds)
                ->withTrashed()
                ->update(['status' => DocumentCategoryStatus::PUSHED->value]);
        }

        return [
            'documentVersions' => $documentVersions,
            'documentCategories' => $documentCategories,
        ];
    }

    /**
     * GitHubツリー用のアイテムを作成
     */
    private function createTreeItems($documentVersions, $documentCategories): array
    {
        $treeItems = [];

        // ドキュメント用のツリーアイテム
        foreach ($documentVersions as $documentVersion) {
            $filePath = $this->mdFileService->generateFilePath(
                $documentVersion->slug,
                $documentVersion->category_path
            );

            Log::info('Processing document version', [
                'id' => $documentVersion->id,
                'is_deleted' => $documentVersion->is_deleted
            ]);

            if (!$documentVersion->is_deleted) {
                $markdownContent = $this->mdFileService->createMdFileContent($documentVersion);
                $treeItems[] = [
                    'path' => $filePath,
                    'mode' => '100644',
                    'type' => 'blob',
                    'content' => $markdownContent,
                ];
            } else {
                $treeItems[] = [
                    'path' => $filePath,
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => null,
                ];
            }
        }

        // カテゴリ用のツリーアイテム
        foreach ($documentCategories as $documentCategory) {
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

            Log::info('Processing category', ['categoryFolderPath' => $categoryFolderPath]);

            // _category_.jsonファイル
            $treeItems[] = [
                'path' => $categoryFolderPath . '/_category_.json',
                'mode' => '100644',
                'type' => 'blob',
                'content' => $categoryJsonContent,
            ];

            // .gitkeepファイル
            $treeItems[] = [
                'path' => $categoryFolderPath . '/.gitkeep',
                'mode' => '100644',
                'type' => 'blob',
                'content' => '',
            ];
        }

        return $treeItems;
    }

    /**
     * レビュアーを処理
     */
    private function processReviewers(?array $reviewers, int $prNumber): array
    {
        if (empty($reviewers)) {
            return [];
        }

        $reviewerUsers = User::whereIn('email', $reviewers)->get();
        $reviewerUserIds = [];
        $reviewerUsernames = [];

        foreach ($reviewerUsers as $reviewerUser) {
            $reviewerUserIds[] = $reviewerUser->id;
            // GitHubユーザー名（仮でemailのローカル部分を使用）
            $reviewerUsernames[] = explode('@', $reviewerUser->email)[0];
        }

        // GitHubでレビュアーを設定
        $this->gitService->addReviewersToPullRequest($prNumber, $reviewerUsernames);

        return $reviewerUserIds;
    }

    /**
     * プルリクエストをDBに保存
     */
    private function savePullRequest($userBranch, CreatePullRequestDto $dto, array $prResult): PullRequest
    {
        return PullRequest::create([
            'user_branch_id' => $userBranch->id,
            'title' => $dto->title,
            'description' => $dto->description,
            'github_url' => $prResult['pr_url'],
            'pr_number' => $prResult['pr_number'],
            'status' => PullRequestStatus::OPENED->value,
        ]);
    }

    /**
     * プルリクエストレビュアーをDBに保存
     */
    private function savePullRequestReviewers(int $pullRequestId, array $reviewerUserIds): void
    {
        $reviewerData = array_map(function ($reviewerUserId) use ($pullRequestId) {
            return [
                'pull_request_id' => $pullRequestId,
                'user_id' => $reviewerUserId,
            ];
        }, $reviewerUserIds);

        PullRequestReviewer::insert($reviewerData);
    }
}
