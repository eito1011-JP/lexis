<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePullRequestRequest extends FormRequest
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
            'organization_id' => 'required|integer|exists:organizations,id',
            'user_branch_id' => 'required|integer|exists:user_branches,id,is_active,1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reviewers' => 'nullable|array|max:15',
            'reviewers.*' => 'required|string|email|exists:users,email',
        ];
    }
}
