<?php

namespace App\Http\Controllers\Api;

use App\Consts\Flag;
use App\Enums\PullRequestActivityAction;
use App\Http\Requests\Api\PullRequestEditSession\FetchEditDiffRequest;
use App\Http\Requests\Api\PullRequestEditSession\FinishEditingRequest;
use App\Http\Requests\Api\PullRequestEditSession\StartEditingRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSession;
use App\Services\DocumentDiffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PullRequestEditSessionController extends ApiBaseController
{
    protected $documentDiffService;

    public function __construct(DocumentDiffService $documentDiffService)
    {
        $this->documentDiffService = $documentDiffService;
    }

    /**
     * プルリクエスト編集差分を取得する
     */
    public function fetchEditDiff(FetchEditDiffRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. query stringのtokenをvalidation
            $token = $request->validated('token');

            // 3. pull_request_edit_sessionsに対してwhere token = request.tokenでfirstOrFail
            $editSession = PullRequestEditSession::where('token', $token)->firstOrFail();

            // 4. pull_request_edit_sessionsに紐づくdocument_versionsとdocument_categoriesをrelationで取得
            $pullRequest = $editSession->pullRequest;
            $userBranch = $pullRequest->userBranch;

            // document_versionsとdocument_categoriesを取得
            $documentVersions = DocumentVersion::where('user_branch_id', $userBranch->id)->get();
            $documentCategories = DocumentCategory::where('user_branch_id', $userBranch->id)->get();

            // 5. edit_start_versionsを取得
            $editStartVersions = EditStartVersion::where('user_branch_id', $userBranch->id)
                ->get();

            $documentResults = [];
            $categoryResults = [];

            // 6. edit_start_versionsをforeach
            foreach ($editStartVersions as $edit) {
                if ($edit->target_type === 'document') {
                    // documentの処理
                    $original = $documentVersions->firstWhere('id', $edit->original_version_id);
                    $current = $documentVersions->firstWhere('id', $edit->current_version_id);

                    $documentResults[] = [
                        'original_version_id' => $edit->original_version_id,
                        'current_version_id' => $edit->current_version_id,
                        'is_deleted' => $edit->is_deleted,
                        'original_document_version' => $original,
                        'current_document_version' => $current,
                    ];
                } elseif ($edit->target_type === 'category') {
                    // categoryの処理
                    $original = $documentCategories->firstWhere('id', $edit->original_version_id);
                    $current = $documentCategories->firstWhere('id', $edit->current_version_id);

                    $categoryResults[] = [
                        'original_version_id' => $edit->original_version_id,
                        'current_version_id' => $edit->current_version_id,
                        'is_deleted' => $edit->is_deleted,
                        'original_category_version' => $original,
                        'current_category_version' => $current,
                    ];
                }
            }

            return response()->json([
                'documents' => $documentResults,
                'categories' => $categoryResults,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('プルリクエスト編集セッションが見つかりません', [
                'token' => $request->validated('token'),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => '編集セッションが見つかりません',
            ], 404);

        } catch (\Exception $e) {
            Log::error('プルリクエスト編集差分取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'token' => $request->validated('token'),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => 'プルリクエスト編集差分の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストの編集を終了する
     */
    public function finishEditingPullRequest(FinishEditingRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. pull_request_id, tokenをform requestでvalidation（FinishEditingRequestで済み）
            $pullRequestId = $request->validated('pull_request_id');
            $token = $request->validated('token');

            // 3. pull_request_idでfirstOrFailしてuser_branch_idを取得
            $pullRequest = PullRequest::findOrFail($pullRequestId);

            // 4. user_branch_idでuser_branchesのレコードのis_activeをFlag::FALSEに更新
            $pullRequest->userBranch()->update([
                'is_active' => Flag::FALSE,
            ]);

            // 3. pull_request_edit_sessionsテーブルをwhere token = request.tokenでfirstOrFail
            $editSession = PullRequestEditSession::where('token', $token)
                ->where('pull_request_id', $pullRequestId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // 4. pull_request_edit_sessionsのfinished_at = now()でupdate
            $editSession->update([
                'finished_at' => now(),
            ]);

            // 5. ActivityLogOnPullRequestをcreate
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequestId,
                'pull_request_edit_session_id' => $editSession->id,
                'action' => PullRequestActivityAction::PULL_REQUEST_EDITED->value,
            ]);

            DB::commit();

            return response()->json();

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::warning('プルリクエスト編集セッションが見つかりません', [
                'pull_request_id' => $request->validated('pull_request_id'),
                'token' => $request->validated('token'),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => '編集セッションが見つかりません',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエスト編集終了エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $request->validated('pull_request_id'),
                'token' => $request->validated('token'),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => 'プルリクエスト編集終了に失敗しました',
            ], 500);
        }
    }
}
