<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPullRequestReviewersRequest extends FormRequest
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
            'emails' => 'required|array|max:15',
            'emails.*' => 'required|email|exists:users,email',
        ];
    }
}
