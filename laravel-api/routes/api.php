<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FixRequestController;
use App\Http\Controllers\Api\PullRequestController;
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

// 認証関連のルート
Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/session', [AuthController::class, 'session']);
});

// 認証が必要なルート
Route::middleware('auth.session')->group(function () {
    // ユーザー関連
    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
    });

    // プルリクエストレビュアー関連
    Route::prefix('admin/pull-request-reviewers')->group(function () {
        Route::get('/', [PullRequestReviewerController::class, 'index']);
        Route::post('/', [PullRequestReviewerController::class, 'store']);
        Route::patch('/{reviewer_id}/resend', [PullRequestReviewerController::class, 'sendReviewRequestAgain']);
    });

    // プルリクエスト関連
    Route::prefix('admin/pull-requests')->group(function () {
        Route::get('/', [PullRequestController::class, 'fetchPullRequests']);
        Route::get('/{id}', [PullRequestController::class, 'fetchPullRequestDetail']);
        Route::get('/{id}/conflict', [PullRequestController::class, 'detectConflict']);
        Route::get('/{id}/comments', [CommentController::class, 'index']);
        Route::get('/{id}/activity-log-on-pull-request', [PullRequestController::class, 'fetchActivityLog']);
        Route::post('/create', [PullRequestController::class, 'createPullRequest']);
        Route::post('/{id}/fix-request', [FixRequestController::class, 'sendFixRequest']);
        Route::put('/{id}', [PullRequestController::class, 'merge']);
        Route::patch('/{id}/close', [PullRequestController::class, 'close']);
        Route::patch('/{id}/approve', [PullRequestController::class, 'approve']);
        Route::patch('/{id}/title', [PullRequestController::class, 'updateTitle']);
    });

    // コメント関連
    Route::prefix('comments')->group(function () {
        Route::post('/', [CommentController::class, 'store']);
    });

    // ドキュメント関連
    Route::prefix('admin/documents')->group(function () {
        // ドキュメント関連
        Route::get('/', [DocumentController::class, 'getDocuments']);
        Route::get('/detail', [DocumentController::class, 'getDocumentDetail']);
        Route::post('/create', [DocumentController::class, 'createDocument']);
        Route::put('/update', [DocumentController::class, 'updateDocument']);
        Route::delete('/delete', [DocumentController::class, 'deleteDocument']);
        Route::get('/category-contents', [DocumentController::class, 'getCategoryContents']);
    });

    // カテゴリ関連
    Route::prefix('admin/document-categories')->group(function () {
        Route::get('/', [DocumentCategoryController::class, 'getCategory']);
        Route::post('/create', [DocumentCategoryController::class, 'createCategory']);
        Route::put('/update', [DocumentCategoryController::class, 'updateCategory']);
        Route::delete('/delete', [DocumentCategoryController::class, 'deleteCategory']);
        Route::get('/category-contents', [DocumentCategoryController::class, 'getCategoryContents']);
    });

    // ユーザーブランチ関連
    Route::prefix('admin/user-branches')->group(function () {
        Route::get('/has-changes', [UserBranchController::class, 'hasUserChanges']);
        Route::get('/diff', [UserBranchController::class, 'fetchDiff']);
    });

    // 修正リクエスト関連
    Route::prefix('admin/fix-requests')->group(function () {
        Route::get('/{token}', [FixRequestController::class, 'getFixRequestDiff']);
        Route::post('/apply', [FixRequestController::class, 'applyFixRequest']);
    });
});

// 認証不要なルート（テスト用）
Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user,
    ]);
});
