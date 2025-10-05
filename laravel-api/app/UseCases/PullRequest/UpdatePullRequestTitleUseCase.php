<?php

declare(strict_types=1);

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\UpdatePullRequestDto;
use App\Models\PullRequest;
use App\Models\User;
use App\Services\PullRequestActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエスト更新UseCase
 */
class UpdatePullRequestUseCase
{
    public function __construct(
        private PullRequestActivityLogService $activityLogService
    ) {}
    /**
     * プルリクエストを更新
     *
     * @param  UpdatePullRequestDto  $dto  プルリクエストタイトル更新DTO
     * @param  User  $user  認証済みユーザー
     * @return void
     *
     * @throws \Exception
     */
    public function execute(UpdatePullRequestDto $dto, User $user): void
    {
        DB::beginTransaction();

        try {
            // 1. プルリクエストを取得
            $pullRequest = PullRequest::findOrFail($dto->pullRequestId);

            // 2. アクティビティログを作成
            if ($dto->title) {
                $this->activityLogService->createTitleEditLog($user, $pullRequest, $dto->title);
            }

            // 3. プルリクエストを更新
            $updateData = [];
            
            if ($dto->title !== null) {
                $updateData['title'] = $dto->title;
            }
            
            if ($dto->description !== null) {
                $updateData['description'] = $dto->description;
            }
            
            if (!empty($updateData)) {
                $pullRequest->update($updateData);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }

}
