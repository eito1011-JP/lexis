<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryEntity extends Model
{
    use HasFactory;

    protected $table = 'category_entities';

    protected $fillable = [
        'organization_id',
    ];

    protected $casts = [
        'organization_id' => 'integer',
    ];

    /**
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * カテゴリバージョンとのリレーション
     */
    public function categoryVersions()
    {
        return $this->hasMany(CategoryVersion::class, 'entity_id');
    }

    /**
     * 子カテゴリエンティティとのリレーション
     */
    public function categoryVersionChildren()
    {
        return $this->hasMany(CategoryVersion::class, 'parent_entity_id');
    }

    /**
     * 子カテゴリエンティティとのリレーション
     */
    public function documentVersionChildren()
    {
        return $this->hasMany(DocumentVersion::class, 'category_entity_id');
    }
}
