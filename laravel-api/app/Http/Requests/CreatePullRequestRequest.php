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
            'user_branch_id' => 'required|integer|exists:user_branches,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'diff_items' => 'required|array',
            'diff_items.*.id' => 'required|integer',
            'diff_items.*.type' => 'required|string|in:document,category',
            'reviewers' => 'nullable|array|max:15',
            'reviewers.*' => 'required|string|email|exists:users,email',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_branch_id' => 'ユーザーブランチID',
            'title' => 'プルリクエストタイトル',
            'body' => 'プルリクエスト本文',
            'diff_items' => '差分アイテム',
            'diff_items.*.id' => 'アイテムID',
            'diff_items.*.type' => 'アイテムタイプ',
            'reviewers' => 'レビュアー',
            'reviewers.*' => 'レビュアーのメールアドレス',
        ];
    }
}
