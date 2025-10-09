<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commit extends Model
{
    use HasFactory;

    protected $table = 'commits';

    protected $fillable = [
        'parent_commit_id',
        'user_branch_id',
        'user_id',
        'message',
    ];

    protected $casts = [
        'parent_commit_id' => 'integer',
        'user_branch_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * 親コミットとのリレーション
     */
    public function parentCommit()
    {
        return $this->belongsTo(Commit::class, 'parent_commit_id');
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class, 'user_branch_id');
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * コミットドキュメント差分とのリレーション
     */
    public function commitDocumentDiffs()
    {
        return $this->hasMany(CommitDocumentDiff::class, 'commit_id');
    }

    /**
     * コミットカテゴリ差分とのリレーション
     */
    public function commitCategoryDiffs()
    {
        return $this->hasMany(CommitCategoryDiff::class, 'commit_id');
    }

    /**
     * 編集開始バージョンとのリレーション
     */
    public function editStartVersions()
    {
        return $this->hasMany(EditStartVersion::class, 'commit_id');
    }
}
