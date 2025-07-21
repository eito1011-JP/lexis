<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FixRequest\GetFixRequestDiffRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\FixRequest;
use App\Models\PullRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FixRequestController extends ApiBaseController
{
    /**
     * 修正リクエストの差分データを取得
     */
    public function getFixRequestDiff(GetFixRequestDiffRequest $request): JsonResponse
    {
        // 認証ユーザーかチェック
        if (! Auth::check()) {
            return response()->json([
                'error' => '認証が必要です。',
            ], 401);
        }

        $token = $request->validated('token');
        $pullRequestId = $request->validated('pull_request_id');

        try {
            // 現在のプルリクエストの変更差分を取得
            $currentPr = PullRequest::with(['documentVersions', 'documentCategories'])
                ->findOrFail($pullRequestId);

            // tokenで修正リクエストのレコードを取得（複数レコードあり得る）
            $fixRequests = FixRequest::where('token', $token)->get();

            if ($fixRequests->isEmpty()) {
                return response()->json([
                    'error' => '指定されたトークンの修正リクエストが見つかりません。',
                ], 404);
            }

            // 修正リクエストの差分とするdocumentやcategoryのidを配列として格納
            $fixRequestDocumentIds = [];
            $fixRequestCategoryIds = [];

            foreach ($fixRequests as $fixRequest) {
                if ($fixRequest->document_id) {
                    $fixRequestDocumentIds[] = $fixRequest->document_id;
                }
                if ($fixRequest->category_id) {
                    $fixRequestCategoryIds[] = $fixRequest->category_id;
                }
            }

            // 重複を除去
            $fixRequestDocumentIds = array_unique($fixRequestDocumentIds);
            $fixRequestCategoryIds = array_unique($fixRequestCategoryIds);

            // documentsとcategoriesをwhereInで一気に取得
            $fixRequestDocuments = collect();
            $fixRequestCategories = collect();

            if (! empty($fixRequestDocumentIds)) {
                $fixRequestDocuments = Document::whereIn('id', $fixRequestDocumentIds)->get();
            }

            if (! empty($fixRequestCategoryIds)) {
                $fixRequestCategories = DocumentCategory::whereIn('id', $fixRequestCategoryIds)->get();
            }

            // レスポンスデータを構築
            $response = [
                'current_pr' => [
                    'documents' => $currentPr->documentVersions,
                    'categories' => $currentPr->documentCategories,
                ],
                'fix_request' => [
                    'documents' => $fixRequestDocuments,
                    'categories' => $fixRequestCategories,
                ],
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'データの取得に失敗しました。',
            ], 500);
        }
    }
}
