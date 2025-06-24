<?php

namespace App\Rules;

use App\Models\DocumentCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueSlugInSameParent implements ValidationRule
{
    protected $categoryPath;

    public function __construct(?string $categoryPath = null)
    {
        $this->categoryPath = $categoryPath;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $categoryPath = $this->categoryPath ? array_filter(explode('/', $this->categoryPath)) : [];

        $parentId = DocumentCategory::getIdFromPath($categoryPath);

        $duplicateSlug = DocumentCategory::where('slug', $value)
            ->where('parent_id', $parentId)
            ->first();

        if ($duplicateSlug) {
            $fail(__('validation.unique_slug_in_same_parent', ['slug' => $value]));
        }
    }
}
