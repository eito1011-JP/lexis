<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\FetchPullRequestsRequest;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PullRequestsController extends ApiBaseController
{
    /**
     * プルリクエスト一覧を取得
     */
    public function fetchPullRequests(FetchPullRequestsRequest $request): JsonResponse
    {
        try {
            // 1. ログイン認証
            $user = $this->getUserFromSession();

            if (!$user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. リクエストでemailをnullableで受け取る
            $email = $request->validated('email');

            // 3. pull_requestsテーブルからstatus = opened or conflict when email exists like … order by created_at ascのレコードを取得
            $query = PullRequest::with(['userBranch.user'])
                ->whereIn('status', ['opened', 'conflict'])
                ->orderBy('created_at', 'asc');

            // emailが指定されている場合、ユーザーのemailで絞り込み
            if (!empty($email)) {
                $query->whereHas('userBranch.user', function ($q) use ($email) {
                    $q->where('email', 'like', '%' . $email . '%');
                });
            }

            $pullRequests = $query->get();

            // 4. response形式で返す
            $response = $pullRequests->map(function ($pullRequest) {
                return [
                    'id' => $pullRequest->id,
                    'title' => $pullRequest->title,
                    'status' => $pullRequest->status,
                    'email' => $pullRequest->userBranch->user->email ?? null,
                    'created_at' => $pullRequest->created_at,
                ];
            });

            return response()->json([
                'pull_requests' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('プルリクエスト一覧取得エラー: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'プルリクエスト一覧の取得に失敗しました',
            ], 500);
        }
    }
} 