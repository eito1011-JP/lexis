<?php

namespace App\Http\Requests\Api\PullRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    /**
     * パスパラメータを追加する
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'pull_request_id' => $this->route('pull_request'),
        ]);
    }
}
