<?php

namespace App\Http\Requests\Api\PullRequest;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePullRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * パスパラメータからvalidation用のルールを取得
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'pull_request_id' => $this->route('id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pull_request_id' => 'required|integer|exists:pull_requests,id',
        ];
    }

    /**
     * バリデーションエラー時のメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pull_request_id.required' => __('validation.custom.pull_request_id.required'),
            'pull_request_id.integer' => __('validation.custom.pull_request_id.integer'),
            'pull_request_id.exists' => __('validation.custom.pull_request_id.exists'),
        ];
    }
}
