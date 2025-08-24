<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequestEditSession extends Model
{
    use HasFactory;

    protected $table = 'pull_request_edit_sessions';

    protected $fillable = [
        'pull_request_id',
        'user_id',
        'token',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * プルリクエストとのリレーション
     */
    public function pullRequest()
    {
        return $this->belongsTo(PullRequest::class, 'pull_request_id');
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class, 'pull_request_edit_session_id');
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function documentCategories()
    {
        return $this->hasMany(DocumentCategory::class, 'pull_request_edit_session_id');
    }

    /**
     * 編集セッション差分とのリレーション
     */
    public function editSessionDiffs()
    {
        return $this->hasMany(PullRequestEditSessionDiff::class, 'pull_request_edit_session_id');
    }

    /**
     * プルリクエスト編集セッションIDを取得
     *
     * @param  int  $pull_request_id  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $user_id  ユーザーID
     * @return int|null 編集セッションID
     */
    public static function findEditSessionId(int $pull_request_id, string $token, int $user_id): ?int
    {
        return self::where('pull_request_id', $pull_request_id)
            ->where('token', $token)
            ->whereNull('finished_at')
            ->where('user_id', $user_id)
            ->value('id');
    }
}
