<?php

namespace App\Http\Controllers\Api;

use App\Consts\Flag;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestActivityAction;
use App\Http\Requests\Api\PullRequestEditSession\FetchEditDiffRequest;
use App\Http\Requests\Api\PullRequestEditSession\FinishEditingRequest;
use App\Http\Requests\Api\PullRequestEditSession\StartEditingRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
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

            // 2. query stringのtokenをform requestでvalidation（FetchEditDiffRequestで済み）
            $token = $request->validated('token');

            // 3. pull_request_edit_sessionsに対してwhere token = request.tokenでfirstOrFail
            $editSession = PullRequestEditSession::where('token', $token)->firstOrFail();

            // 4. pull_request_edit_sessionsに紐づくpull_request_edit_session_diffs(1対多)をrelationで取得
            $editSessionDiffs = $editSession->editSessionDiffs;

            // 5. pull_request_edit_session_diffsをforeachで処理

            // 10. return response()->json
            $documents = [];
            $categories = [];

            foreach ($editSessionDiffs as $editSessionDiff) {
                if ($editSessionDiff->target_type === EditStartVersionTargetType::DOCUMENT->value) {
                    $original = null;
                    $current = null;

                    // 元のドキュメントを取得
                    $original = DocumentVersion::withTrashed()
                        ->where('id', $editSessionDiff->original_version_id)
                        ->first();

                    // 現在のドキュメントを取得
                    $current = DocumentVersion::withTrashed()
                        ->where('id', $editSessionDiff->current_version_id)
                        ->first();

                    $documents[] = [
                        'diff_type' => $editSessionDiff->diff_type,
                        'original' => $original ? $original->toArray() : null,
                        'current' => $current ? $current->toArray() : null,
                    ];
                } elseif ($editSessionDiff->target_type === EditStartVersionTargetType::CATEGORY->value) {
                    $original = null;
                    $current = null;

                    // 元のカテゴリを取得
                    $original = DocumentCategory::withTrashed()
                        ->where('id', $editSessionDiff->original_version_id)
                        ->first();

                    // 現在のカテゴリを取得
                    $current = DocumentCategory::withTrashed()
                        ->where('id', $editSessionDiff->current_version_id)
                        ->first();

                    $categories[] = [
                        'diff_type' => $editSessionDiff->diff_type,
                        'original' => $original ? $original->toArray() : null,
                        'current' => $current ? $current->toArray() : null,
                    ];
                }
            }

            return response()->json([
                'documents' => $documents,
                'categories' => $categories,
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
                'error' => 'プルリクエスト編集差分取得に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストの編集を開始する
     */
    public function startEditingPullRequest(StartEditingRequest $request): JsonResponse
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

            // 2. pull_request_idをform requestでvalidation（StartEditingRequestで済み）
            $pullRequestId = $request->validated('pull_request_id');

            // 3. pull_request_idでfirstOrFailしてuser_branch_idを取得
            $pullRequest = PullRequest::findOrFail($pullRequestId);

            // 4. user_branch_idでuser_branchesのレコードのis_activeをFlag::TRUEに更新
            $pullRequest->userBranch()->update([
                'is_active' => Flag::TRUE,
            ]);

            // 5. tokenを\Illuminate\Support\Str::random(32);で作成
            $token = Str::random(32);

            // 6. pull_request_edit_sessionsテーブルにレコードを作成
            $editSession = PullRequestEditSession::create([
                'pull_request_id' => $pullRequestId,
                'user_id' => $user->id,
                'token' => $token,
                'started_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'token' => $token,
                'session_id' => $editSession->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエスト編集開始エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $request->validated('pull_request_id'),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => 'プルリクエスト編集開始に失敗しました',
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
