<?php

namespace App\Http\Controllers\Api;

use App\Enums\PullRequestStatus;
use App\Http\Requests\FetchPullRequestDetailRequest;
use App\Http\Requests\FetchPullRequestsRequest;
use App\Models\PullRequest;
use App\Services\DocumentDiffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PullRequestsController extends ApiBaseController
{
    protected DocumentDiffService $documentDiffService;

    public function __construct(DocumentDiffService $documentDiffService)
    {
        $this->documentDiffService = $documentDiffService;
    }

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

    /**
     * プルリクエスト詳細を取得
     */
    public function fetchPullRequestDetail(FetchPullRequestDetailRequest $request, int $id): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. プルリクエストを取得（status = opened or conflict）
            $pullRequest = PullRequest::with([
                'userBranch.user',
                'userBranch.editStartVersions',
                'userBranch.editStartVersions.originalDocumentVersion',
                'userBranch.editStartVersions.currentDocumentVersion',
                'userBranch.editStartVersions.originalDocumentVersion.category',
                'userBranch.editStartVersions.currentDocumentVersion.category',
                'userBranch.editStartVersions.originalCategory',
                'userBranch.editStartVersions.currentCategory',
                'reviewers.user',
            ])
                ->where('id', $id)
                ->whereIn('status', [PullRequestStatus::OPENED->value, PullRequestStatus::CONFLICT->value])
                ->firstOrFail();

            // 3. 差分データを生成
            $diffResult = $this->documentDiffService->generateDiffData($pullRequest->userBranch->editStartVersions);

            // 4. レビュアー情報を取得
            $reviewers = $pullRequest->reviewers->map(function ($reviewer) {
                return $reviewer->user->email;
            })->toArray();

            // 5. プルリクエスト作成者の名前を取得
            $authorName = $pullRequest->userBranch->user->name ?? null;

            return response()->json([
                ...$diffResult,
                'title' => $pullRequest->title,
                'description' => $pullRequest->description,
                'status' => $pullRequest->status,
                'author_name' => $authorName,
                'reviewers' => $reviewers,
            ]);

        } catch (\Exception $e) {
            Log::error('プルリクエスト詳細取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $id,
            ]);

            return response()->json([
                'error' => 'プルリクエスト詳細の取得に失敗しました',
            ], 500);
        }
    }
}
