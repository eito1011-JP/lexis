<?php

namespace App\Http\Requests\Api\PullRequestEditSession;

use Illuminate\Foundation\Http\FormRequest;

class StartEditingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pull_request_id.required' => __('validation.pull_request.pull_request_id.required'),
            'pull_request_id.integer' => __('validation.pull_request.pull_request_id.integer'),
            'pull_request_id.exists' => __('validation.pull_request.pull_request_id.exists'),
        ];
    }
}
