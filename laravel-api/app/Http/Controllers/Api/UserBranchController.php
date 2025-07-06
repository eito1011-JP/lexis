<?php

namespace App\Http\Controllers\Api;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\UserBranchPrStatus;
use App\Http\Requests\CreatePullRequestRequest;
use App\Http\Requests\FetchDiffRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Services\CategoryFolderService;
use App\Services\DocumentDiffService;
use App\Services\GitService;
use App\Services\MdFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserBranchController extends ApiBaseController
{
    protected GitService $gitService;

    protected MdFileService $mdFileService;

    protected CategoryFolderService $categoryFolderService;

    protected DocumentDiffService $documentDiffService;

    public function __construct(
        GitService $gitService,
        MdFileService $mdFileService,
        CategoryFolderService $categoryFolderService,
        DocumentDiffService $documentDiffService
    ) {
        $this->gitService = $gitService;
        $this->mdFileService = $mdFileService;
        $this->categoryFolderService = $categoryFolderService;
        $this->documentDiffService = $documentDiffService;
    }

    /**
     * Git差分チェック
     */
    public function hasUserChanges(Request $request): JsonResponse
    {
        try {
            // Cookieセッションからユーザー情報を取得
            $loginUser = $this->getUserFromSession();

            if (! $loginUser) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // アクティブなユーザーブランチを取得
            $activeBranch = $loginUser->userBranches()->active()->where('pr_status', UserBranchPrStatus::NONE->value)->first();

            $hasUserChanges = ! is_null($activeBranch);
            $userBranchId = $hasUserChanges ? $activeBranch->id : null;

            return response()->json([
                'has_user_changes' => $hasUserChanges,
                'user_branch_id' => $userBranchId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking document versions: '.$e->getMessage());

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * プルリクエスト作成
     */
    public function createPullRequest(CreatePullRequestRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. diffをidとuser_branch_idで絞り込んでfetch
            $userBranch = $user->userBranches()
                ->active()
                ->where('pr_status', UserBranchPrStatus::NONE->value)
                ->orderBy('id', 'desc')
                ->first();

            if (! $userBranch) {
                return response()->json([
                    'error' => 'ユーザーブランチが見つかりません',
                ], 404);
            }

            // 3. diffアイテムを取得
            $diffItems = $request->diff_items;
            $documentVersions = collect();
            $documentCategories = collect();

            Log::info('diffItems: '.json_encode($diffItems));

            // document と category のIDを分別して一括取得用の配列を作成
            $documentIds = [];
            $categoryIds = [];

            foreach ($diffItems as $item) {
                if ($item['type'] === 'document') {
                    $documentIds[] = $item['id'];
                } elseif ($item['type'] === 'category') {
                    $categoryIds[] = $item['id'];
                }
            }

            // 一括でDocumentVersionsを取得
            if (! empty($documentIds)) {
                $documentVersions = DocumentVersion::where('user_branch_id', $userBranch->id)
                    ->whereIn('id', $documentIds)
                    ->get();

                // 取得したdocumentsのstatusをpushedにbulk update
                DocumentVersion::where('user_branch_id', $userBranch->id)
                    ->whereIn('id', $documentIds)
                    ->update(['status' => DocumentStatus::PUSHED->value]);
            }

            // 一括でDocumentCategoriesを取得
            if (! empty($categoryIds)) {
                $documentCategories = DocumentCategory::where('user_branch_id', $userBranch->id)
                    ->whereIn('id', $categoryIds)
                    ->get();

                // 取得したcategoriesのstatusをpushedにbulk update
                DocumentCategory::where('user_branch_id', $userBranch->id)
                    ->whereIn('id', $categoryIds)
                    ->update(['status' => DocumentCategoryStatus::PUSHED->value]);
            }

            // 4. tree api用にpath, contentを動的に作成
            $treeItems = [];

            foreach ($documentVersions as $documentVersion) {
                $markdownContent = $this->mdFileService->createMdFileContent($documentVersion);
                Log::info('slug: '.$documentVersion->slug);
                Log::info('category_path: '.$documentVersion->category_path);
                $filePath = $this->mdFileService->generateFilePath($documentVersion->slug, $documentVersion->category_path);
                Log::info('filePath: '.$filePath);
                $treeItems[] = [
                    'path' => $filePath,
                    'mode' => '100644',
                    'type' => 'blob',
                    'content' => $markdownContent,
                ];
            }

            foreach ($documentCategories as $documentCategory) {
                // _category_.jsonファイルの内容を作成
                $categoryJsonData = [
                    'label' => $documentCategory->sidebar_label,
                    'position' => $documentCategory->position,
                    'link' => [
                        'type' => 'generated-index',
                        'description' => $documentCategory->description,
                    ],
                ];
                $categoryJsonContent = json_encode($categoryJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                $categoryFolderPath = $this->categoryFolderService->generateCategoryFilePath($documentCategory->slug, $documentCategory->parent_id && $documentCategory->parent_id !== DocumentCategoryConstants::DEFAULT_CATEGORY_ID ? $documentCategory->parent_id : null);

                Log::info('categoryFolderPath: '.$categoryFolderPath);
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

            // 5. GitHubのrepositoryにリモートbranchを作成
            $branchResult = $this->gitService->createRemoteBranch(
                $userBranch->branch_name,
                $userBranch->snapshot_commit
            );

            // 6. リモートレポジトリで直接ファイルを編集（tree作成）
            $treeResult = $this->gitService->createTree(
                $userBranch->snapshot_commit,
                $treeItems
            );

            // 7. コミット作成
            $commitResult = $this->gitService->createCommit(
                $request->title,
                $treeResult['sha'],
                [$userBranch->snapshot_commit]
            );

            // 8. ブランチの最新コミットを更新
            $this->gitService->updateBranchReference(
                $userBranch->branch_name,
                $commitResult['sha']
            );

            // 9. プルリクエスト作成
            $prResult = $this->gitService->createPullRequest(
                $userBranch->branch_name,
                $request->title,
                $request->description ?? ''
            );

            // 10. レビュアー設定
            $reviewerUserIds = [];
            if ($request->reviewers && ! empty($request->reviewers)) {
                // レビュアーのGitHubユーザー名を取得
                $reviewerUsers = User::whereIn('email', $request->reviewers)->get();
                $reviewerUsernames = [];

                foreach ($reviewerUsers as $reviewerUser) {
                    $reviewerUserIds[] = $reviewerUser->id;
                    // GitHubユーザー名がある場合は追加（今回は仮でemailのローカル部分を使用）
                    $reviewerUsernames[] = explode('@', $reviewerUser->email)[0];
                }

                // レビュアーを設定
                $this->gitService->addReviewersToPullRequest(
                    $prResult['pr_number'],
                    $reviewerUsernames
                );
            }

            // 11. pull_requestsテーブルにデータを保存
            $pullRequest = PullRequest::create([
                'user_branch_id' => $userBranch->id,
                'title' => $request->title,
                'description' => $request->description,
                'github_url' => $prResult['pr_url'],
                'status' => 'opened',
            ]);

            // 12. pull_request_reviewersテーブルにレビュアーを保存
            if (! empty($reviewerUserIds)) {
                $reviewerData = array_map(function ($reviewerUserId) use ($pullRequest) {
                    return [
                        'pull_request_id' => $pullRequest->id,
                        'user_id' => $reviewerUserId,
                    ];
                }, $reviewerUserIds);

                PullRequestReviewer::insert($reviewerData);
            }

            // 13. ユーザーブランチのステータスを更新
            $userBranch->update([
                'pr_status' => UserBranchPrStatus::OPENED->value,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'プルリクエストを作成しました',
                'pr_url' => $prResult['pr_url'],
                'pr_number' => $prResult['pr_number'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエスト作成エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'プルリクエストの作成に失敗しました',
            ], 500);
        }
    }

    /**
     * Git差分取得
     */
    public function fetchDiff(FetchDiffRequest $request): JsonResponse
    {
        try {
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // ユーザーブランチと関連データを一括取得
            $userBranch = $user->userBranches()->active()->where('pr_status', UserBranchPrStatus::NONE->value)
                ->with([
                    'editStartVersions',
                    'editStartVersions.originalDocumentVersion',
                    'editStartVersions.currentDocumentVersion',
                    'editStartVersions.originalDocumentVersion.category',
                    'editStartVersions.currentDocumentVersion.category',
                    'editStartVersions.originalCategory',
                    'editStartVersions.currentCategory',
                ])
                ->findOrFail($request->user_branch_id);

            $diffResult = $this->documentDiffService->generateDiffData($userBranch->editStartVersions);

            return response()->json($diffResult);

        } catch (\Exception $e) {
            Log::error('Git差分の取得に失敗しました: '.$e->getMessage());

            return response()->json([
                'error' => 'Git差分の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ブランチスナップショット初期化
     */
    public static function initBranchSnapshot(int $userId, string $email): void
    {
        try {
            // 最新のコミットハッシュを取得（GitHub APIを使用）
            $latestCommit = self::findLatestCommit();

            $timestamp = date('Ymd');
            $branchName = "feature/{$email}_{$timestamp}";

            // user_branchesテーブルに新しいブランチを挿入
            DB::table('user_branches')->insert([
                'user_id' => $userId,
                'branch_name' => $branchName,
                'snapshot_commit' => $latestCommit,
                'is_active' => 1,
                'pr_status' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('ブランチスナップショット初期化エラー: '.$e->getMessage());

            throw new \Exception('ブランチの作成に失敗しました');
        }
    }

    /**
     * 最新のコミットハッシュを取得
     */
    private static function findLatestCommit(): string
    {
        try {
            // GitHub APIを使用して最新のコミットハッシュを取得
            // 実際の実装ではGitHub APIクライアントを使用
            $githubToken = config('services.github.token');
            $githubOwner = config('services.github.owner');
            $githubRepo = config('services.github.repo');

            // 簡易的な実装（実際にはGitHub APIを使用）
            return 'latest_commit_hash';

        } catch (\Exception $e) {
            Log::error('GitHub APIエラー: '.$e->getMessage());

            throw new \Exception('最新のコミット取得に失敗しました');
        }
    }
}
