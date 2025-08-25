<?php

namespace App\Http\Controllers\Api;

use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;

class UserController extends ApiBaseController
{
    /**
     * ユーザー一覧を取得（検索対応）
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'users' => DocumentVersion::all(),
        ]);
    }
}