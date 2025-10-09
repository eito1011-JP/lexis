<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBranchSession extends Model
{
    use HasFactory;

    protected $table = 'user_branch_sessions';

    protected $fillable = [
        'user_id',
        'user_branch_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        // カラムが削除されたため、castsも空に
    ];

    /**
     * 作成日時の降順でソートするスコープ
     */
    public function scopeOrderByCreatedAtDesc($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class);
    }
}
