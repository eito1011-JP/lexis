<?php

namespace App\Http\Controllers\Api;

use App\Enums\PullRequestStatus;
use App\Http\Requests\FetchPullRequestsRequest;
use App\Models\PullRequest;
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
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            $email = $request->validated('email');

            $query = PullRequest::with('userBranch.user')
                ->whereIn('status', [PullRequestStatus::OPENED->value, PullRequestStatus::CONFLICT->value])
                ->orderByDesc('created_at')
                ->when($email, function ($query) use ($email) {
                    return $query->whereHas('userBranch.user', function ($q) use ($email) {
                        $q->where('email', 'like', $email.'%');
                    });
                });

            $pulls = $query->paginate(10);

            $counts = PullRequest::selectRaw(
                'SUM(status IN (?,?)) AS opened_count,
                 SUM(status IN (?,?)) AS closed_count',
                [
                    PullRequestStatus::OPENED->value,
                    PullRequestStatus::CONFLICT->value,
                    PullRequestStatus::CLOSED->value,
                    PullRequestStatus::MERGED->value,
                ]
            )->first();

            $response = collect($pulls->items())->map(function ($pullRequest) {
                return [
                    'id' => $pullRequest->id,
                    'title' => $pullRequest->title,
                    'status' => $pullRequest->status,
                    'email' => $pullRequest->userBranch->user->email ?? null,
                    'github_url' => $pullRequest->github_url,
                    'created_at' => $pullRequest->created_at,
                ];
            });

            return response()->json([
                'pull_requests' => $response,
                'total_opened_count' => $counts->opened_count,
                'total_closed_count' => $counts->closed_count,
                'pagination' => [
                    'current_page' => $pulls->currentPage(),
                    'last_page' => $pulls->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('プルリクエスト一覧取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'プルリクエスト一覧の取得に失敗しました',
            ], 500);
        }
    }
}
