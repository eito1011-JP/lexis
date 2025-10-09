<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Dto\UseCase\PullRequest\UpdatePullRequestDto;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestReviewerActionStatus;
use App\Enums\PullRequestStatus;
use App\Http\Requests\Api\PullRequest\ApprovePullRequestRequest;
use App\Http\Requests\Api\PullRequest\ClosePullRequestRequest;
use App\Http\Requests\Api\PullRequest\DetectConflictRequest;
use App\Http\Requests\Api\PullRequest\MergePullRequestRequest;
use App\Http\Requests\Api\PullRequest\ShowRequest;
use App\Http\Requests\Api\PullRequest\UpdateRequest;
use App\Http\Requests\CreatePullRequestRequest;
use App\Http\Requests\FetchPullRequestsRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Services\CategoryFolderService;
use App\Services\DocumentDiffService;
use App\Services\GitService;
use App\Services\MdFileService;
use App\Services\PullRequestConflictService;
use App\UseCases\PullRequest\CreatePullRequestUseCase;
use App\UseCases\PullRequest\IsConflictResolvedUseCase;
use App\UseCases\PullRequest\MergePullRequestUseCase;
use App\UseCases\PullRequest\ShowPullRequestUseCase;
use App\Dto\UseCase\PullRequest\ShowDto;
use App\UseCases\PullRequest\UpdatePullRequestUseCase;
use Exception;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class PullRequestController extends ApiBaseController
{
    protected DocumentDiffService $documentDiffService;

    protected GitService $gitService;

    protected MdFileService $mdFileService;

    protected CategoryFolderService $categoryFolderService;

    protected PullRequestConflictService $pullRequestConflictService;

    protected IsConflictResolvedUseCase $isConflictResolvedUseCase;

    protected CreatePullRequestUseCase $createPullRequestUseCase;

    protected ShowPullRequestUseCase $showPullRequestUseCase;

    protected UpdatePullRequestUseCase $updatePullRequestUseCase;

    public function __construct(
        DocumentDiffService $documentDiffService,
        GitService $gitService,
        MdFileService $mdFileService,
        CategoryFolderService $categoryFolderService,
        PullRequestConflictService $pullRequestConflictService,
        IsConflictResolvedUseCase $isConflictResolvedUseCase,
        CreatePullRequestUseCase $createPullRequestUseCase,
        ShowPullRequestUseCase $showPullRequestUseCase,
        UpdatePullRequestUseCase $updatePullRequestUseCase
    ) {
        $this->documentDiffService = $documentDiffService;
        $this->gitService = $gitService;
        $this->mdFileService = $mdFileService;
        $this->categoryFolderService = $categoryFolderService;
        $this->pullRequestConflictService = $pullRequestConflictService;
        $this->isConflictResolvedUseCase = $isConflictResolvedUseCase;
        $this->createPullRequestUseCase = $createPullRequestUseCase;
        $this->showPullRequestUseCase = $showPullRequestUseCase;
        $this->updatePullRequestUseCase = $updatePullRequestUseCase;
    }

    /**
     * プルリクエスト作成
     */
    public function store(CreatePullRequestRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. DTOを作成
            $dto = CreatePullRequestDto::fromArray([
                'userBranchId' => $request->user_branch_id,
                'organizationId' => $request->organization_id,
                'title' => $request->title,
                'description' => $request->description,
                'reviewers' => $request->reviewers,
            ]);

            // 3. UseCaseを実行
            $this->createPullRequestUseCase->execute($dto, $user);

            return response()->json();

        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
            );
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * プルリクエスト一覧を取得
     */
    public function index(FetchPullRequestsRequest $request): JsonResponse
    {
        try {
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            $email = $request->validated('email');
            $statusFilters = $request->validated('status');

            // statusパラメータが指定されている場合はそれを使用、そうでなければ未対応のみ
            $defaultStatuses = [PullRequestStatus::OPENED->value, PullRequestStatus::CONFLICT->value];
            $statuses = $statusFilters ? $statusFilters : $defaultStatuses;

            $query = PullRequest::with('userBranch.creator')
                ->whereIn('status', $statuses)
                ->orderByDesc('created_at')
                ->when($email, function ($query) use ($email) {
                    return $query->whereHas('userBranch.creator', function ($q) use ($email) {
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
                    'email' => $pullRequest->userBranch->creator->email ?? null,
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
    public function show(ShowRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. UseCaseを実行してプルリクエスト詳細を取得
            $dto = new ShowDto($request->validated('pull_request_id'));
            $result = $this->showPullRequestUseCase->execute($dto, $user);

            return response()->json($result);

        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * プルリクエストをマージ
     */
    public function merge(MergePullRequestRequest $request, MergePullRequestUseCase $mergePullRequestUseCase): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. DTOを作成してマージ処理を実行
            $dto = new MergePullRequestDto(
                $request->pull_request_id,
                $user->id
            );

            $mergePullRequestUseCase->execute($dto);

            return response()->json();

        } catch (AuthorizationException) {
            return $this->sendError(
                ErrorType::CODE_NOT_AUTHORIZED,
                __('errors.MSG_FORBIDDEN'),
                ErrorType::STATUS_NOT_AUTHORIZED,
                LogLevel::ERROR,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
                LogLevel::ERROR,
            );
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * プルリクエストのコンフリクト状態を検知
     */
    public function detectConflict(DetectConflictRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. プルリクエストを取得
            $pullRequest = PullRequest::find($request->id);

            if (! $pullRequest) {
                return response()->json([
                    'error' => 'プルリクエストが見つかりません',
                ], 404);
            }

            // 3. GitHub APIでプルリクエスト情報を取得
            $prInfo = $this->gitService->getPullRequestInfo($pullRequest->pr_number);

            return response()->json([
                'mergeable' => $prInfo['mergeable'],
                'mergeable_state' => $prInfo['mergeable_state'],
            ]);

        } catch (\Exception $e) {
            Log::error('プルリクエストコンフリクト検知エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $request->id,
            ]);

            return response()->json([
                'error' => 'コンフリクト検知に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストをクローズ
     */
    public function close(ClosePullRequestRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. プルリクエストを取得（Form Requestでバリデーション済み）
            $pullRequest = PullRequest::find($id);

            // 3. GitHub APIでプルリクエストをクローズ
            $this->gitService->closePullRequest($pullRequest->pr_number);

            // 4. pull_requestsテーブルのstatusをclosedに更新
            $pullRequest->update([
                'status' => PullRequestStatus::CLOSED->value,
            ]);

            // 5. ActivityLogを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequest->id,
                'action' => PullRequestActivityAction::PULL_REQUEST_CLOSED->value,
            ]);

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエストクローズエラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $id,
            ]);

            return response()->json([
                'error' => 'プルリクエストのクローズに失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストを承認する
     */
    public function approve(ApprovePullRequestRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. バリデーション済みデータを取得
            $validated = $request->validated();
            $pullRequestId = $validated['pull_request_id'];

            // 3. 該当のレビュアーレコードを取得または作成
            $reviewer = PullRequestReviewer::where('pull_request_id', $pullRequestId)
                ->where('user_id', $user->id)
                ->first();

            if (! $reviewer) {
                // レビュアーとして登録されていない場合は新規作成
                $reviewer = PullRequestReviewer::create([
                    'pull_request_id' => $pullRequestId,
                    'user_id' => $user->id,
                    'action_status' => PullRequestReviewerActionStatus::APPROVED,
                ]);
            } else {
                // 既存のレビュアーレコードを更新
                $reviewer->update([
                    'action_status' => PullRequestReviewerActionStatus::APPROVED,
                ]);
            }

            // ActivityLogを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequestId,
                'action' => PullRequestActivityAction::REVIEWER_APPROVED->value,
            ]);

            return response()->json();

        } catch (\Exception $e) {
            Log::error('プルリクエスト承認エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $request->pull_request_id ?? null,
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => 'プルリクエストの承認に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエストのタイトルを更新する
     */
    public function update(UpdateRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. DTOを作成
            $dto = new UpdatePullRequestDto(
                pullRequestId: $request->validated('pull_request_id'),
                title: $request->validated('title'),
                description: $request->validated('description'),
            );

            // 3. UseCaseを実行
            $this->updatePullRequestUseCase->execute($dto, $user);

            return response()->json();

        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * コンフリクト発生時の3-way差分を取得
     */
    public function fetchConflictDiff(DetectConflictRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->user();
            if (! $user) {
                return response()->json(['error' => '認証されていません'], 401);
            }

            $pullRequestId = $request->validated('id');
            $result = $this->pullRequestConflictService->fetchConflictDiffData($pullRequestId);

            return response()->json($result);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('コンフリクト差分取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $id,
            ]);

            return response()->json(['error' => 'コンフリクト差分の取得に失敗しました'], 500);
        }
    }

    /**
     * フロントのコンフリクト修正一時検証
     * - 本文(編集用)テキストにコンフリクトマーカーが含まれていないかを確認
     * - 含まれていなければ各ファイルの状態をOKとして返す
     * - 含まれていればエラーを返す
     */
    public function isConflictResolved(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->user();
            if (! $user) {
                return response()->json(['error' => '認証されていません'], 401);
            }

            // 単一のファイルオブジェクトのみを処理
            $file = $request->input('file');
            if (! is_array($file)) {
                return response()->json(['error' => '不正なリクエスト形式です'], 422);
            }

            // UseCaseクラスのメソッドを呼び出し
            $result = $this->isConflictResolvedUseCase->execute($file);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('handleFixConflictTemporary エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $id,
            ]);

            return response()->json(['error' => '一時検証に失敗しました'], 500);
        }
    }
}
