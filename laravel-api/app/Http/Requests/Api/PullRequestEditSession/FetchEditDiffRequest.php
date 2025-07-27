<?php

namespace App\Http\Requests\Api\PullRequestEditSession;

use Illuminate\Foundation\Http\FormRequest;

class FetchEditDiffRequest extends FormRequest
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
            'token' => 'required|string|size:32|exists:pull_request_edit_sessions,token',
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
            'token.required' => __('validation.token.required'),
            'token.string' => __('validation.token.string'),
            'token.size' => __('validation.token.size'),
            'token.exists' => __('validation.token.exists'),
        ];
    }
}
