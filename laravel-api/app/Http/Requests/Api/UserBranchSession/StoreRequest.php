<?php

namespace App\Http\Requests\Api\UserBranchSession;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * リクエストが承認されるかを判定する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを取得する
     */
    public function rules(): array
    {
        return [
            'pull_request_id' => 'required|integer|exists:pull_requests,id',
        ];
    }
}

