<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendReviewRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ルートパラメータの reviewer_id を user_id にマージ
     */
    protected function prepareForValidation()
    {
        if ($this->route('reviewer_id')) {
            $this->merge([
                'user_id' => $this->route('reviewer_id'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:pending',
            'pull_request_id' => 'required|integer|exists:pull_requests,id',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => __('validation.action.required'),
            'action.string' => __('validation.action.string'),
            'action.in' => __('validation.action.in'),
            'pull_request_id.required' => __('validation.pull_request.pull_request_id.required'),
            'pull_request_id.integer' => __('validation.pull_request.pull_request_id.integer'),
            'pull_request_id.exists' => __('validation.pull_request.pull_request_id.exists'),
            'user_id.required' => __('validation.user.id.required'),
            'user_id.integer' => __('validation.user.id.integer'),
            'user_id.exists' => __('validation.user.id.exists'),
        ];
    }
}
