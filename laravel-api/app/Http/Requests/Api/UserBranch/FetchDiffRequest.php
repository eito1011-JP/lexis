<?php

namespace App\Http\Requests\Api\UserBranch;

use Illuminate\Foundation\Http\FormRequest;

class FetchDiffRequest extends FormRequest
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
            'user_branch_id' => 'required|integer|exists:user_branches,id',
        ];
    }
}
