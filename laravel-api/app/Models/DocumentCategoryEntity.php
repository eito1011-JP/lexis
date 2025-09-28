<?php

namespace App\Models;

use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCategoryEntity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'document_category_entities';

    protected $fillable = [
        'organization_id',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function documentCategories()
    {
        return $this->hasMany(DocumentCategory::class, 'entity_id');
    }

    /**
     * 子カテゴリエンティティとのリレーション
     */
    public function documentCategoryChildren()
    {
        return $this->hasMany(DocumentCategory::class, 'parent_entity_id');
    }

        /**
     * 子カテゴリエンティティとのリレーション
     */
    public function documentVersionChildren()
    {
        return $this->hasMany(DocumentVersion::class, 'category_entity_id');
    }
}
