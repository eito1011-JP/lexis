<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
class GetDocumentByCategoryPathRequest extends FormRequest
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
        ];
    }

    public function prepareForValidation()
    {
        $categoryPath = $this->route('category_path');
        
        if ($categoryPath) {
            $this->merge(['category_path' => $categoryPath]);
        }
    }
}
