<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\FetchPullRequestReviewersRequest;
use App\Http\Requests\SetPullRequestReviewersRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PullRequestReviewerController extends ApiBaseController
{
    /**
     * プルリクエストレビュアー一覧を取得（検索対応）
     */
    public function index(FetchPullRequestReviewersRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // バリデーション済みの検索クエリを取得
            $searchEmail = $request->validated('email');

            // 削除されていない全ユーザーを取得
            $query = User::whereNull('deleted_at');

            // email検索が指定されている場合
            if (! empty($searchEmail)) {
                $query->where('email', 'like', '%'.$searchEmail.'%');
            }

            $users = $query->select('id', 'email', 'role', 'created_at')->whereNot('id', $user->id)->get();

            return response()->json([
                'users' => $users,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ユーザー一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストレビュアーを設定
     */
    public function store(SetPullRequestReviewersRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // バリデーション済みデータを取得
            $validated = $request->validated();
            $pullRequestId = $validated['pull_request_id'];
            $emails = $validated['emails'];

            // emailからuser_idを取得
            $users = User::whereIn('email', $emails)
                ->whereNull('deleted_at')
                ->select('id', 'email')
                ->get();

            // 既存のレビュアーを削除
            PullRequestReviewer::where('pull_request_id', $pullRequestId)->delete();

            // 新しいレビュアーをbulk insert
            $reviewerData = [];
            foreach ($users as $reviewerUser) {
                $reviewerData[] = [
                    'pull_request_id' => $pullRequestId,
                    'user_id' => $reviewerUser->id,
                ];
            }

            if (! empty($reviewerData)) {
                PullRequestReviewer::insert($reviewerData);
            }

            return response()->json();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'レビュアーの設定に失敗しました',
            ], 500);
        }
    }
}
