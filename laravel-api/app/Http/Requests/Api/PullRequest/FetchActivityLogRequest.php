<?php

namespace App\Http\Requests\Api\PullRequest;

use Illuminate\Foundation\Http\FormRequest;

class FetchActivityLogRequest extends FormRequest
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
            'id' => 'required|integer|exists:pull_requests,id',
        ];
    }

    /**
     * バリデーションエラーメッセージを取得する
     */
    public function messages(): array
    {
        return [
            'id.required' => trans('validation.pull_request.id.required'),
            'id.integer' => trans('validation.pull_request.id.integer'),
            'id.exists' => trans('validation.pull_request.id.exists'),
        ];
    }

    /**
     * パスパラメータを追加する
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }
}
