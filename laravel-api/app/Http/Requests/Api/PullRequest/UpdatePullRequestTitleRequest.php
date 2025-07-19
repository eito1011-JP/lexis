<?php

namespace App\Http\Requests\Api\PullRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePullRequestTitleRequest extends FormRequest
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
            'title' => 'required|string|max:255',
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
            'title.required' => trans('validation.pull_request.title.required'),
            'title.string' => trans('validation.pull_request.title.string'),
            'title.max' => trans('validation.pull_request.title.max'),
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
