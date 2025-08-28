<?php

namespace App\Models;

use App\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequestEditSession extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $table = 'pull_request_edit_sessions';

    protected $fillable = [
        'pull_request_id',
        'user_id',
        'token',
        'started_at',
        'finished_at',
        'organization_id',
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
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
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
     * プルリクエストIDでフィルター
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPullRequest($query, int $pull_request_id)
    {
        return $query->where('pull_request_id', $pull_request_id);
    }

    /**
     * トークンでフィルター
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }

    /**
     * ユーザーIDでフィルター
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, int $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    /**
     * アクティブな編集セッション（finished_atがnull）でフィルター
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNull('finished_at');
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
        return self::byPullRequest($pull_request_id)
            ->byToken($token)
            ->byUser($user_id)
            ->active()
            ->value('id');
    }

    /**
     * 有効な編集セッションを検索
     *
     * @param  int  $pull_request_id  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $user_id  ユーザーID
     * @return static|null 編集セッション
     */
    public static function findValidSession(int $pull_request_id, string $token, int $user_id): ?self
    {
        return self::byPullRequest($pull_request_id)
            ->byToken($token)
            ->byUser($user_id)
            ->active()
            ->first();
    }
}
