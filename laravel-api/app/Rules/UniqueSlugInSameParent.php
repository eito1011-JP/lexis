<?php

namespace App\Rules;

use App\Models\DocumentCategory;
use App\Services\CategoryService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueSlugInSameParent implements ValidationRule
{
    protected $categoryPath;

    protected $currentCategoryId;

    protected $currentDocumentId;

    public function __construct(
        ?string $categoryPath = null,
        ?int $currentCategoryId = null,
        ?int $currentDocumentId = null
    ) {
        $this->categoryPath = $categoryPath;
        $this->currentCategoryId = $currentCategoryId;
        $this->currentDocumentId = $currentDocumentId;
    }

    /**
     * 同じ親カテゴリ内でslugが重複していないかをチェック
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $categoryPath = $this->categoryPath ? array_filter(explode('/', $this->categoryPath)) : [];

        // LaravelのサービスコンテナからCategoryServiceを取得
        $CategoryService = app(CategoryService::class);
        $parentId = $CategoryService->getIdFromPath(implode('/', $categoryPath));

        $duplicateSlug = DocumentCategory::where('slug', $value)
            ->where('parent_entity_id', $parentId)
            ->where(function ($query) {
                if ($this->currentCategoryId !== null) {
                    $query->orWhere('id', '!=', $this->currentCategoryId);
                }
                if ($this->currentDocumentId !== null) {
                    $query->orWhere('id', '!=', $this->currentDocumentId);
                }
            })
            ->first();

        if ($duplicateSlug) {
            $fail(__('validation.unique_slug_in_same_parent', ['slug' => $value]));
        }
    }
}
