<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Comment\PostCommentRequest;
use App\Models\Comment;
use App\Models\PullRequest;
use Illuminate\Http\JsonResponse;

class CommentController extends ApiBaseController
{
    /**
     * コメントを投稿
     */
    public function store(PostCommentRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (!$user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // プルリクエストの存在確認
            $pullRequest = PullRequest::findOrFail($request->pull_request_id);

            // コメントを作成
            Comment::create([
                'pull_request_id' => $pullRequest->id,
                'user_id' => $user->id ?? null,
                'content' => $request->content,
                'is_resolved' => false,
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