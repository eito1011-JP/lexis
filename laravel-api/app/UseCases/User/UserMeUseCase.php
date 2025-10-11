<?php

namespace App\UseCases\User;

use App\Enums\PullRequestStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;

class UserMeUseCase
{
    /**
     * ユーザー情報、組織情報、ユーザーブランチ情報を取得
     *
     * @return array{user: User, organization: Organization|null, activeUserBranch: UserBranch|null, nextAction: string|null}
     */
    public function execute(User $user, UserBranchService $userBranchService): array
    {
        $userInfo = $user->with(['organizationMember.organization', 'userBranchSessions'])->find($user->id);

        if (!$userInfo) {
            throw new NotFoundException();
        }   

        // 組織情報を取得
        $organization = $userInfo->organizationMember->organization;

        if (!$organization) {
            throw new NotFoundException();
        }

        // アクティブなユーザーブランチを取得
        $activeUserBranch = $userBranchService->hasUserActiveBranchSession($user, $organization->id);

        // アクティブなユーザーブランチがある場合、pullRequestsをロード
        if ($activeUserBranch) {
            $activeUserBranch->load(['pullRequests' => function ($query) {
                $query->where('status', PullRequestStatus::OPENED->value);
            }]);
        }

        // next actionを決定
        $nextAction = $this->determineNextAction($activeUserBranch);

        return [
            'user' => $userInfo,
            'organization' => $organization,
            'activeUserBranch' => $activeUserBranch,
            'nextAction' => $nextAction,
        ];
    }

    /**
     * 次のアクションを決定
     *
     * @param UserBranch|null $activeUserBranch アクティブなユーザーブランチ
     * @return string|null 次のアクション
     */
    private function determineNextAction(?UserBranch $activeUserBranch): ?string
    {
        if (!$activeUserBranch) {
            return null;
        }

        // commit_id = nullのedit_start_versionsの差分をチェック
        $hasUncommittedChanges = $activeUserBranch->editStartVersions()
            ->whereNull('commit_id')
            ->exists();

        if (!$hasUncommittedChanges) {
            return null;
        }

        $hasOpenedPullRequest = $activeUserBranch->pullRequests()
            ->where('status', PullRequestStatus::OPENED->value)
            ->exists();

        if ($hasOpenedPullRequest) {
            return 'create_subsequent_commit';
        } else {
            return 'create_initial_commit';
        }
    }
}
