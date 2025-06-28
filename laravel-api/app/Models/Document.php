<?php

namespace App\Models;

use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * モデルが使用するテーブル名
     */
    protected $table = 'document_versions';

    protected $fillable = [
        'user_id',
        'user_branch_id',
        'file_path',
        'category_id',
        'sidebar_label',
        'slug',
        'content',
        'is_public',
        'status',
        'last_edited_by',
        'file_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_order' => 'integer',
    ];

    /**
     * カテゴリとのリレーション
     */
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }
}
