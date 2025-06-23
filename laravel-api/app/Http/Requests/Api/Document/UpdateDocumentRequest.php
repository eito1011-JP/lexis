<?php

namespace App\Http\Requests\Api\Document;

use App\Rules\UniqueSlugInSameParent;
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
            'category_path' => 'required|string',
            'category' => 'nullable|string',
            'sidebar_label' => 'required|string|max:255',
            'content' => 'required|string',
            'is_public' => 'required|boolean',
            'slug' => ['required', 'string', new UniqueSlugInSameParent($this->category_path)],
            'file_order' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category' => __('validation.document.category.required'),
            'sidebar_label' => __('validation.document.label.required'),
            'content' => __('validation.document.content.required'),
            'slug' => __('validation.document.slug.required'),
            'file_order' => __('validation.document.file_order.integer'),
        ];
    }

    public function prepareForValidation()
    {
        $categoryPath = $this->route('category_path');
        
        if ($categoryPath) {
            $this->merge(['category_path' => $categoryPath]);
        }
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
