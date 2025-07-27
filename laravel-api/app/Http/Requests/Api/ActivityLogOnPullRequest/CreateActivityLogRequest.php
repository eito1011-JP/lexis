<?php

namespace App\Http\Requests\Api\ActivityLogOnPullRequest;

use App\Enums\PullRequestActivityAction;
use Illuminate\Foundation\Http\FormRequest;

class CreateActivityLogRequest extends FormRequest
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
            'action' => 'required|string|in:'.implode(',', PullRequestActivityAction::values()),
        ];
    }

    /**
     * バリデーションエラーメッセージを取得する
     */
    public function messages(): array
    {
        return [
            'pull_request_id.required' => trans('validation.pull_request.id.required'),
            'pull_request_id.integer' => trans('validation.pull_request.id.integer'),
            'pull_request_id.exists' => trans('validation.pull_request.id.exists'),
            'action.required' => trans('validation.activity_log.action.required'),
            'action.string' => trans('validation.activity_log.action.string'),
            'action.enum' => trans('validation.activity_log.action.enum'),
        ];
    }
}
