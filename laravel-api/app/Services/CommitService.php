<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitChangeType;
use App\Enums\PullRequestActivityAction;
use App\Models\ActivityLogOnPullRequest;
use App\Models\CategoryVersion;
use App\Models\Commit;
use App\Models\CommitCategoryDiff;
use App\Models\CommitDocumentDiff;
use App\Models\DocumentVersion;
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
     * ユーザーブランチの編集内容からコミットを作成（EditStartVersionsの取得と更新を含む）
     *
     * @param  User  $user  認証ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  UserBranch  $userBranch  ユーザーブランチ
     * @param  string  $message  コミットメッセージ
     * @return Commit|null コミット（EditStartVersionsが存在しない場合はnull）
     */
    public function createCommitFromUserBranch(
        User $user,
        PullRequest $pullRequest,
        UserBranch $userBranch,
        string $message
    ): ?Commit {
        return DB::transaction(function () use ($user, $pullRequest, $userBranch, $message) {
            // 1. commit_id = nullのedit_start_versionsを取得
            $editStartVersions = EditStartVersion::where('user_branch_id', $userBranch->id)
                ->whereNull('commit_id')
                ->get();

            // EditStartVersionsが存在しない場合はnullを返す
            if ($editStartVersions->isEmpty()) {
                return null;
            }

            // 2. コミット作成（EditStartVersionsのcommit_id更新も含む）
            $commit = $this->createCommit(
                $user,
                $pullRequest,
                $userBranch,
                $editStartVersions,
                $message
            );

            // 3. バージョンのステータスを更新（draft => pushed）
            $this->updateVersionStatus($editStartVersions);

            return $commit;
        });
    }

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

            // 3. activity_log_on_pull_requestにレコード追加
            $this->createActivityLog($user, $pullRequest, $commit);

            // 4. edit_start_versionsにcommit_idを設定
            EditStartVersion::whereIn('id', $editStartVersions->pluck('id'))
                ->update(['commit_id' => $commit->id]);

            // 5. 取得したedit_start_versionsを元にcommit_document_diffs & commit_categories_diffsをbulk insert
            $this->createCommitDiffs($commit, $editStartVersions);

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

        // entity_idとtarget_typeでグループ化
        $grouped = $editStartVersions->groupBy(function ($item) {
            return $item->target_type.'_'.$item->entity_id;
        });

        foreach ($grouped as $group) {
            // 各グループの最初と最後のEditStartVersionを取得
            $sortedGroup = $group->sortBy('id');
            $first = $sortedGroup->first();
            $last = $sortedGroup->last();
            $groupSize = $sortedGroup->count();

            $diffData = $this->buildDiffDataFromGroup($commit->id, $first, $last, $groupSize);

            if ($first->target_type === 'document') {
                $documentDiffs[] = $diffData;
            } elseif ($first->target_type === 'category') {
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
     * グループ化されたEditStartVersionsから差分データを構築
     *
     * @param  int  $commitId  コミットID
     * @param  EditStartVersion  $first  最初のEditStartVersion
     * @param  EditStartVersion  $last  最後のEditStartVersion
     * @param  int  $groupSize  グループのサイズ
     */
    private function buildDiffDataFromGroup(int $commitId, EditStartVersion $first, EditStartVersion $last, int $groupSize): array
    {
        $firstOriginalVersion = $first->getOriginalObject();
        $lastCurrentVersion = $last->getCurrentObject();

        // change_typeの決定
        $changeType = $this->determineChangeType(
            $first->original_version_id,
            $first->current_version_id,
            $last->current_version_id,
            $firstOriginalVersion,
            $lastCurrentVersion
        );

        // タイトルと説明の変更フラグ
        $isTitleChanged = false;
        $isDescriptionChanged = false;

        // UPDATEDの場合、またはCREATEDでグループサイズが2以上の場合に変更フラグを検出
        if ($firstOriginalVersion && $lastCurrentVersion) {
            if ($changeType === CommitChangeType::UPDATED || 
                ($changeType === CommitChangeType::CREATED && $groupSize >= 2 && $first->target_type === 'document')) {
                $isTitleChanged = $firstOriginalVersion->title !== $lastCurrentVersion->title;
                $isDescriptionChanged = $firstOriginalVersion->description !== $lastCurrentVersion->description;
            }
        }

        $entityIdKey = $first->target_type === 'document'
            ? 'document_entity_id'
            : 'category_entity_id';

        return [
            'commit_id' => $commitId,
            $entityIdKey => $first->entity_id,
            'change_type' => $changeType->value,
            'is_title_changed' => $isTitleChanged,
            'is_description_changed' => $isDescriptionChanged,
            'first_original_version_id' => $first->original_version_id,
            'last_current_version_id' => $last->current_version_id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * 変更タイプを決定
     *
     * @param  int|null  $firstOriginalVersionId  最初のオリジナルバージョンID
     * @param  int  $firstCurrentVersionId  最初のカレントバージョンID
     * @param  int  $lastCurrentVersionId  最後のカレントバージョンID
     * @param  mixed  $firstOriginalVersion  最初のオリジナルバージョン
     * @param  mixed  $lastCurrentVersion  最後のカレントバージョン
     */
    private function determineChangeType(
        ?int $firstOriginalVersionId,
        int $firstCurrentVersionId,
        int $lastCurrentVersionId,
        $firstOriginalVersion,
        $lastCurrentVersion
    ): CommitChangeType {
        // 削除されている場合
        if ($lastCurrentVersion && isset($lastCurrentVersion->is_deleted) && $lastCurrentVersion->is_deleted) {
            return CommitChangeType::DELETED;
        }

        // 新規作成の場合（最初のEditStartVersionのoriginal_version_id == current_version_id）
        if ($firstOriginalVersionId === $firstCurrentVersionId) {
            return CommitChangeType::CREATED;
        }

        // オリジナルバージョンが存在しない場合は新規作成
        if (! $firstOriginalVersion) {
            return CommitChangeType::CREATED;
        }

        // それ以外は更新
        return CommitChangeType::UPDATED;
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

    /**
     * バージョンのステータスを更新（draft => pushed）
     *
     * @param  Collection  $editStartVersions  編集開始バージョンのコレクション
     */
    private function updateVersionStatus(Collection $editStartVersions): void
    {
        $documentVersionIds = [];
        $categoryVersionIds = [];

        foreach ($editStartVersions as $editStartVersion) {
            if ($editStartVersion->target_type === 'document') {
                $documentVersionIds[] = $editStartVersion->current_version_id;
            } elseif ($editStartVersion->target_type === 'category') {
                $categoryVersionIds[] = $editStartVersion->current_version_id;
            }
        }

        if (! empty($documentVersionIds)) {
            DocumentVersion::whereIn('id', $documentVersionIds)
                ->where('status', 'draft')
                ->update(['status' => 'pushed']);
        }

        if (! empty($categoryVersionIds)) {
            CategoryVersion::whereIn('id', $categoryVersionIds)
                ->where('status', 'draft')
                ->update(['status' => 'pushed']);
        }
    }
}
