<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GitController;

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
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// 認証が必要なルート
Route::middleware('auth.session')->group(function () {
    // ユーザー関連
    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
    });

    // ドキュメント関連
    Route::prefix('admin/documents')->group(function () {
        // カテゴリ関連
        Route::get('/categories', [DocumentController::class, 'getCategories']);
        Route::post('/categories', [DocumentController::class, 'createCategory']);
        Route::put('/categories/{id}', [DocumentController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [DocumentController::class, 'deleteCategory']);
        Route::get('/category-slug', [DocumentController::class, 'getCategoryBySlug']);
        
        // ドキュメント関連
        Route::get('/', [DocumentController::class, 'getDocuments']);
        Route::post('/', [DocumentController::class, 'createDocument']);
        Route::get('/slug', [DocumentController::class, 'getDocumentBySlug']);
        Route::put('/{id}', [DocumentController::class, 'updateDocument']);
        Route::delete('/{id}', [DocumentController::class, 'deleteDocument']);
        Route::get('/category-contents', [DocumentController::class, 'getCategoryContents']);
        
        // Git関連
        Route::prefix('git')->group(function () {
            Route::get('/check-diff', [GitController::class, 'checkDiff']);
            Route::post('/create-pr', [GitController::class, 'createPr']);
            Route::get('/diff', [GitController::class, 'diff']);
        });
    });
});

// 認証不要なルート（テスト用）
Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user
    ]);
}); 