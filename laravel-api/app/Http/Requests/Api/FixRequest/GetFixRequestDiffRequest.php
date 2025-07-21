<?php

namespace App\Http\Requests\Api\FixRequest;

use Illuminate\Foundation\Http\FormRequest;

class GetFixRequestDiffRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'pull_request_id' => ['required', 'integer', 'exists:pull_requests,id'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'token' => 'トークン',
            'pull_request_id' => 'プルリクエストID',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'token' => $this->route('token'),
            'pull_request_id' => $this->query('pull_request_id'),
        ]);
    }
}
