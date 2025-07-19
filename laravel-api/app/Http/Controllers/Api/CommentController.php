<?php

namespace App\Http\Controllers\Api;

use App\Enums\PullRequestActivityAction;
use App\Http\Requests\Api\Comment\FetchCommentsRequest;
use App\Http\Requests\Api\Comment\PostCommentRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\Comment;
use App\Models\PullRequest;
use Illuminate\Http\JsonResponse;

class CommentController extends ApiBaseController
{
    /**
     * プルリクエストに紐づくコメントを取得
     */
    public function index(FetchCommentsRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            $pullRequestId = $request->route('id');

            // プルリクエストの存在確認
            $pullRequest = PullRequest::findOrFail($pullRequestId);

            // コメントを取得（created_atの昇順、user情報も含む）
            $comments = Comment::with('user')
                ->where('pull_request_id', $pullRequest->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'author' => $comment->user ? $comment->user->email : null,
                        'content' => $comment->content,
                        'is_resolved' => $comment->is_resolved,
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
                    ];
                });

            return response()->json($comments);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => '指定されたプルリクエストが見つかりません',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'コメントの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * コメントを投稿
     */
    public function store(PostCommentRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // プルリクエストの存在確認
            $pullRequest = PullRequest::findOrFail($request->pull_request_id);

            // コメントを作成
            $comment = Comment::create([
                'pull_request_id' => $pullRequest->id,
                'user_id' => $user->id ?? null,
                'content' => $request->content,
                'is_resolved' => false,
            ]);

            // ActivityLogを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequest->id,
                'comment_id' => $comment->id,
                'action' => PullRequestActivityAction::COMMENTED->value,
            ]);

            return response()->json([]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => '指定されたプルリクエストが見つかりません',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'コメントの投稿に失敗しました',
            ], 500);
        }
    }
}
