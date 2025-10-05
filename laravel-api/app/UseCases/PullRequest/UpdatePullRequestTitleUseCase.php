<?php

declare(strict_types=1);

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\UpdatePullRequestTitleDto;
use App\Enums\PullRequestActivityAction;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエストタイトル更新UseCase
 */
class UpdatePullRequestTitleUseCase
{
    /**
     * プルリクエストのタイトルを更新
     *
     * @param  UpdatePullRequestTitleDto  $dto  プルリクエストタイトル更新DTO
     * @param  User  $user  認証済みユーザー
     * @return void
     *
     * @throws \Exception
     */
    public function execute(UpdatePullRequestTitleDto $dto, User $user): void
    {
        DB::beginTransaction();

        try {
            // 1. プルリクエストを取得
            $pullRequest = PullRequest::findOrFail($dto->pullRequestId);

            // 2. アクティビティログを作成
            $this->createActivityLog($user, $pullRequest, $dto->title);

            // 3. プルリクエストのタイトルを更新
            $pullRequest->update([
                'title' => $dto->title,
                'description' => $dto->description,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }

    /**
     * アクティビティログを作成
     */
    private function createActivityLog(User $user, PullRequest $pullRequest, string $newTitle): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::PULL_REQUEST_TITLE_EDITED->value,
            'old_pull_request_title' => $pullRequest->title,
            'new_pull_request_title' => $newTitle,
        ]);
    }
}
