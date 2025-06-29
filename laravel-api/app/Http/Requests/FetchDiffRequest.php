<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchDiffRequest extends FormRequest
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
            'user_branch_id' => 'required|integer|exists:user_branches,id',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_branch_id' => __('validation.attributes.user_branch_id'),
        ];
    }
}
