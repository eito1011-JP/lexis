<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiBaseController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::all();
        return response()->json($users);
    }
}
