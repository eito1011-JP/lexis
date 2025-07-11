<?php

namespace App\Http\Requests;

use App\Enums\PullRequestStatus;
use Illuminate\Foundation\Http\FormRequest;

class FetchPullRequestsRequest extends FormRequest
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
            'email' => 'nullable|string|max:255',
            'status' => 'nullable|array',
            'status.*' => 'nullable|string|in:'.implode(',', PullRequestStatus::values()),
        ];
    }

    /**
     * バリデーション後のデータを準備
     */
    protected function prepareForValidation()
    {
        if ($this->has('status') && is_string($this->status)) {
            $statuses = explode(',', $this->status);
            $validStatuses = PullRequestStatus::values();

            // 有効なステータスのみをフィルタリング
            $filteredStatuses = array_filter($statuses, function ($status) use ($validStatuses) {
                return in_array(trim($status), $validStatuses);
            });

            $this->merge(['status' => array_values($filteredStatuses)]);
        }
    }
}
