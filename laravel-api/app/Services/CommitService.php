<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PullRequestActivityAction;
use App\Models\ActivityLogOnPullRequest;
use App\Models\Commit;
use App\Models\CommitCategoryDiff;
use App\Models\CommitDocumentDiff;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * コミットサービス
 */
class CommitService extends BaseService
{
    /**
     * コミットを作成
     *
     * @param  User  $user  認証ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  UserBranch  $userBranch  ユーザーブランチ
     * @param  Collection  $editStartVersions  編集開始バージョンのコレクション
     * @param  string  $message  コミットメッセージ
     */
    public function createCommit(
        User $user,
        PullRequest $pullRequest,
        UserBranch $userBranch,
        Collection $editStartVersions,
        string $message
    ): Commit {
        return DB::transaction(function () use ($user, $pullRequest, $userBranch, $editStartVersions, $message) {
            // 1. parent commitを取得(created_at DESCのcommits→first())
            $parentCommit = Commit::where('user_branch_id', $userBranch->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // 2. create commitレコード
            $commit = Commit::create([
                'parent_commit_id' => $parentCommit?->id,
                'user_branch_id' => $userBranch->id,
                'user_id' => $user->id,
                'message' => $message,
            ]);

            // 3. 取得したedit_start_versionsを元にcommit_document_diffs & commit_categories_diffsをbulk insert
            $this->createCommitDiffs($commit, $editStartVersions);

            // 4. activity_log_on_pull_requestにレコード追加
            $this->createActivityLog($user, $pullRequest, $commit);

            return $commit;
        });
    }

    /**
     * コミット差分を作成
     *
     * @param  Commit  $commit  コミット
     * @param  Collection  $editStartVersions  編集開始バージョンのコレクション
     */
    private function createCommitDiffs(Commit $commit, Collection $editStartVersions): void
    {
        $documentDiffs = [];
        $categoryDiffs = [];

        foreach ($editStartVersions as $editStartVersion) {
            $diffData = $this->buildDiffData($commit->id, $editStartVersion);

            if ($editStartVersion->target_type === 'document') {
                $documentDiffs[] = $diffData;
            } elseif ($editStartVersion->target_type === 'category') {
                $categoryDiffs[] = $diffData;
            }
        }

        // bulk insert
        if (! empty($documentDiffs)) {
            CommitDocumentDiff::insert($documentDiffs);
        }

        if (! empty($categoryDiffs)) {
            CommitCategoryDiff::insert($categoryDiffs);
        }
    }

    /**
     * 差分データを構築
     *
     * @param  int  $commitId  コミットID
     * @param  EditStartVersion  $editStartVersion  編集開始バージョン
     */
    private function buildDiffData(int $commitId, EditStartVersion $editStartVersion): array
    {
        $originalVersion = $editStartVersion->getOriginalObject();
        $currentVersion = $editStartVersion->getCurrentObject();

        // change_typeの決定
        $changeType = $this->determineChangeType($originalVersion, $currentVersion);

        // タイトルと説明の変更フラグ
        $isTitleChanged = false;
        $isDescriptionChanged = false;

        if ($changeType === 'updated' && $originalVersion && $currentVersion) {
            $isTitleChanged = $originalVersion->title !== $currentVersion->title;
            $isDescriptionChanged = $originalVersion->description !== $currentVersion->description;
        }

        $entityIdKey = $editStartVersion->target_type === 'document'
            ? 'document_entity_id'
            : 'category_entity_id';

        return [
            'commit_id' => $commitId,
            $entityIdKey => $editStartVersion->entity_id,
            'change_type' => $changeType,
            'is_title_changed' => $isTitleChanged,
            'is_description_changed' => $isDescriptionChanged,
            'first_original_version_id' => $editStartVersion->original_version_id,
            'last_current_version_id' => $editStartVersion->current_version_id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * 変更タイプを決定
     *
     * @param  mixed  $originalVersion  オリジナルバージョン
     * @param  mixed  $currentVersion  カレントバージョン
     */
    private function determineChangeType($originalVersion, $currentVersion): string
    {
        if (! $originalVersion && $currentVersion) {
            return 'created';
        }

        if ($originalVersion && ! $currentVersion) {
            return 'deleted';
        }

        return 'updated';
    }

    /**
     * アクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  Commit  $commit  コミット
     */
    private function createActivityLog(User $user, PullRequest $pullRequest, Commit $commit): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::COMMIT_CREATED->value,
            'commit_id' => $commit->id,
        ]);
    }
}
