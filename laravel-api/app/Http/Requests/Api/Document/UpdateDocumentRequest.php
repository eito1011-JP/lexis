<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use League\HTMLToMarkdown\HtmlConverter;

class UpdateDocumentRequest extends FormRequest
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
            'category' => 'sometimes|nullable|string',
            'sidebar_label' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'isPublic' => 'sometimes|required|boolean',
            'slug' => 'sometimes|required|string|regex:/^[a-z0-9-]+$/',
            'fileOrder' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category' => __('validation.document.category.required'),
            'sidebar_label' => __('validation.document.sidebar_label.required'),
            'content' => __('validation.document.content.required'),
            'slug' => __('validation.document.slug.required'),
            'fileOrder' => __('validation.document.fileOrder.integer'),
        ];
    }

    public function passedValidation()
    {
        // HTMLコンテンツをMarkdown形式に変換
        if ($this->has('content') && ! empty($this->input('content'))) {
            $htmlContent = $this->input('content');
            $converter = new HtmlConverter;
            $markdownContent = $converter->convert($htmlContent);

            // 変換されたMarkdownコンテンツをリクエストデータに設定
            $this->merge(['content' => $markdownContent]);

            Log::info('HTML to Markdown conversion completed');
        }
    }
}
