<?php

declare(strict_types=1);

namespace App\UseCases\UserBranchSession;

use App\Dto\UseCase\UserBranchSession\StoreDto;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestStatus;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Exceptions\NotAuthorizedException;
use App\Exceptions\TargetDocumentNotFoundException;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;
use App\Models\UserBranchSession;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ユーザーブランチセッション作成UseCase
 */
class StoreUseCase
{
    /**
     * ユーザーブランチセッションを作成
     *
     * @param StoreDto $dto ユーザーブランチセッション作成DTO
     * @return UserBranchSession 作成されたユーザーブランチセッション
     *
     * @throws AuthenticationException 認証エラー
     * @throws TargetDocumentNotFoundException プルリクエストが見つからない
     * @throws DuplicateExecutionException すでにセッションが存在する
     */
    public function execute(StoreDto $dto): UserBranchSession
    {
        DB::beginTransaction();

        try {
            // 認証ユーザーの組織を取得
            $organizationMember = $dto->user->organizationMember;
            if (! $organizationMember) {
                throw new NotFoundException();
            }

            $organizationId = $organizationMember->organization_id;

            // ユーザーのロールを確認
            $roleBinding = OrganizationRoleBinding::where('user_id', $dto->user->id)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $roleBinding) {
                throw new NotFoundException();
            }

            $role = OrganizationRoleBindingRole::from($roleBinding->role);

            // viewerロールの場合はエラー
            if ($role->isViewer()) {
                throw new NotAuthorizedException();
            }

            // SELECT FOR UPDATE でpull_requestを取得
            $pullRequest = PullRequest::lockForUpdate()
                ->where('id', $dto->pullRequestId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $pullRequest) {
                throw new NotFoundException();
            }

            // pull_requestに紐づくuser_branchのuser_branch_sessionsを取得
            $existingSession = UserBranchSession::with('user:id,nickname')
                ->lockForUpdate()
                ->where('user_branch_id', $pullRequest->user_branch_id)
                ->first();

            // すでにセッションが存在する場合はエラー
            if ($existingSession) {
                $editingUserNickname = $existingSession->user->nickname ?? '他のユーザー';
                $exception = new DuplicateExecutionException();
                $exception->setErrorMessage("{$editingUserNickname}が編集中です");
                throw $exception;
            }

            // user_branch_sessionsを作成
            $userBranchSession = UserBranchSession::create([
                'user_id' => $dto->user->id,
                'user_branch_id' => $pullRequest->user_branch_id,
            ]);

            DB::commit();

            return $userBranchSession;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }
}

