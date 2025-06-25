<?php

namespace App\Http\Requests\Api\Document;

use App\Rules\UniqueSlugInSameParent;
use Illuminate\Foundation\Http\FormRequest;
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
            'current_document_id' => 'required|integer',
            'category_path' => 'required|string',
            'sidebar_label' => 'required|string|max:255',
            'content' => 'required|string',
            'is_public' => 'required|boolean',
            'slug' => ['required', 'string', new UniqueSlugInSameParent($this->category_path, null, $this->current_document_id)],
            'file_order' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category' => __('attributes.document.category'),
            'sidebar_label' => __('attributes.document.sidebarLabel'),
            'content' => __('attributes.document.content'),
            'slug' => __('attributes.document.slug'),
            'file_order' => __('attributes.document.fileOrder'),
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
        }
    }
}
