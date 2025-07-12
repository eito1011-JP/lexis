<?php

namespace App\Http\Requests\Api\PullRequest;

use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClosePullRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
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
            'pull_request_id' => [
                'required',
                'integer',
                Rule::exists(PullRequest::class, 'id')->where(function ($query) {
                    return $query->where('status', PullRequestStatus::OPENED->value);
                }),
            ],
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
            'pull_request_id' => 'プルリクエストID',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pull_request_id.exists' => '指定されたプルリクエストが見つからないか、既にクローズされています',
        ];
    }
}
