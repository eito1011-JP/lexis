<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ActivityLogOnPullRequest\CreateActivityLogRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ActivityLogOnPullRequestController extends ApiBaseController
{
    /**
     * 編集終了を記録する
     */
    public function create(CreateActivityLogRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. プルリクエストの存在確認（Form Requestでバリデーション済み）
            $pullRequest = PullRequest::findOrFail($request->pull_request_id);

            // 3. ActivityLogを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequest->id,
                'action' => $request->action,
            ]);

            return response()->json();

        } catch (\Exception $e) {
            Log::error('編集終了記録エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $request->pull_request_id,
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => '編集終了の記録に失敗しました',
            ], 500);
        }
    }
}
