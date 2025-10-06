<?php

use App\Http\Controllers\Api\ActivityLogOnPullRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CategoryEntityController;
use App\Http\Controllers\Api\DocumentEntityController;
use App\Http\Controllers\Api\EmailAuthnController;
use App\Http\Controllers\Api\ExplorerController;
use App\Http\Controllers\Api\FixRequestController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PullRequestController;
use App\Http\Controllers\Api\PullRequestEditSessionController;
use App\Http\Controllers\Api\PullRequestReviewerController;
use App\Http\Controllers\Api\UserBranchController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// EC2でのヘルスチェック用
// Route::any('__ping', function () {
//     return response()->json([
//         'ok'     => true,
//         'method' => request()->method(),
//         'path'   => request()->path(),
//         'routes_cache_exists' => file_exists(base_path('bootstrap/cache/routes-v7.php')),
//         'app_env' => config('app.env'),
//         'release_path' => base_path(), // 実行中リリースの実パス確認
//     ]);
// });

// Route::get('/health-check', [HealthCheckController::class, 'healthCheck']);

// 認証関連のルート（認証不要）
Route::prefix('auth')->group(function () {
    Route::post('/pre-users', [EmailAuthnController::class, 'sendAuthnEmail']);
    Route::get('/pre-users', [EmailAuthnController::class, 'identifyToken']);
    Route::post('/signin-with-email', [EmailAuthnController::class, 'signinWithEmail']);
});

// 認証が必要なルート
Route::middleware('auth:api')->group(function () {
    // 認証関連
    Route::prefix('auth')->group(function () {
        // ログインユーザー情報取得
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
    // ユーザー関連
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/me', [UserController::class, 'me']);
    });

    // プルリクエストレビュアー関連
    Route::prefix('pull-request-reviewers')->group(function () {
        Route::get('/', [PullRequestReviewerController::class, 'index']);
        Route::post('/', [PullRequestReviewerController::class, 'store']);
        Route::patch('/{reviewer_id}/resend', [PullRequestReviewerController::class, 'sendReviewRequestAgain']);
    });

    // プルリクエスト関連
    Route::resource('pull-requests', PullRequestController::class, ['only' => ['index', 'show', 'store', 'update']]);
    Route::prefix('pull-requests')->group(function () {
        Route::get('/{id}/conflict', [PullRequestController::class, 'detectConflict']);
        Route::get('/{id}/conflict/diff', [PullRequestController::class, 'fetchConflictDiff']);
        Route::post('/{id}/conflict/temporary', [PullRequestController::class, 'isConflictResolved']);
        Route::get('/{id}/comments', [CommentController::class, 'index']);
        Route::post('/{id}/fix-request', [FixRequestController::class, 'sendFixRequest']);
        Route::put('/{id}', [PullRequestController::class, 'merge']);
        Route::patch('/{id}/close', [PullRequestController::class, 'close']);
        Route::patch('/{id}/approve', [PullRequestController::class, 'approve']);
    });

    // プルリクエスト編集セッション関連
    Route::prefix('pull-request-edit-sessions')->group(function () {
        Route::get('/', [PullRequestEditSessionController::class, 'fetchEditDiff']);
        Route::post('/', [PullRequestEditSessionController::class, 'startEditingPullRequest']);
        Route::patch('/', [PullRequestEditSessionController::class, 'finishEditingPullRequest']);
    });

    // アクティビティログ関連
    Route::prefix('activity-logs')->group(function () {
        Route::post('/', [ActivityLogOnPullRequestController::class, 'create']);
    });

    // コメント関連
    Route::prefix('comments')->group(function () {
        Route::post('/', [CommentController::class, 'store']);
    });

    // ドキュメント関連
    Route::resource('document-entities', DocumentEntityController::class);

    // カテゴリ関連
    Route::resource('category-entities', CategoryEntityController::class);

    // ユーザーブランチ関連
    Route::prefix('user-branches')->group(function () {
        Route::get('/has-changes', [UserBranchController::class, 'hasUserChanges']);
        Route::get('/diff', [UserBranchController::class, 'fetchDiff']);
    });

    // 修正リクエスト関連
    Route::prefix('fix-requests')->group(function () {
        Route::get('/{token}', [FixRequestController::class, 'fetchFixRequestDiff']);
        Route::post('/apply', [FixRequestController::class, 'applyFixRequest']);
    });

    // エクスプローラー関連
    Route::get('/nodes', [ExplorerController::class, 'fetchNodes']);
});

// 組織登録（プリサインアップフロー）
Route::post('/organizations', [OrganizationController::class, 'create']);

// 認証不要なルート（テスト用）
Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user,
    ]);
});
