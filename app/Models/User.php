<?php

namespace App\Models;

use App\Exceptions\NexusException;
use App\Http\Middleware\Locale;
use App\Models\Traits\NexusActivityLogTrait;
use App\Repositories\ExamRepository;
use App\Repositories\TokenRepository;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Nexus\Database\NexusDB;
use NexusPlugin\Permission\Models\Role;
use NexusPlugin\Permission\Models\UserPermission;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, NexusActivityLogTrait, Notifiable;

    public $timestamps = false;

    protected $perPage = 50;

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_PENDING = 'pending';

    const ENABLED_YES = 'yes';

    const ENABLED_NO = 'no';

    const CLASS_PEASANT = '0';

    const CLASS_USER = '1';

    const CLASS_POWER_USER = '2';

    const CLASS_ELITE_USER = '3';

    const CLASS_CRAZY_USER = '4';

    const CLASS_INSANE_USER = '5';

    const CLASS_VETERAN_USER = '6';

    const CLASS_EXTREME_USER = '7';

    const CLASS_ULTIMATE_USER = '8';

    const CLASS_NEXUS_MASTER = '9';

    const CLASS_VIP = '10';

    const CLASS_RETIREE = '11';

    const CLASS_UPLOADER = '12';

    const CLASS_MODERATOR = '13';

    const CLASS_ADMINISTRATOR = '14';

    const CLASS_SYSOP = '15';

    const CLASS_STAFF_LEADER = '16';

    public static array $classes = [
        self::CLASS_PEASANT => ['text' => 'Peasant'],
        self::CLASS_USER => ['text' => 'User', 'min_seed_points' => 0],
        self::CLASS_POWER_USER => ['text' => 'Power User', 'min_seed_points' => 40000],
        self::CLASS_ELITE_USER => ['text' => 'Elite User', 'min_seed_points' => 80000],
        self::CLASS_CRAZY_USER => ['text' => 'Crazy User', 'min_seed_points' => 150000],
        self::CLASS_INSANE_USER => ['text' => 'Insane User', 'min_seed_points' => 250000],
        self::CLASS_VETERAN_USER => ['text' => 'Veteran User', 'min_seed_points' => 400000],
        self::CLASS_EXTREME_USER => ['text' => 'Extreme User', 'min_seed_points' => 600000],
        self::CLASS_ULTIMATE_USER => ['text' => 'Ultimate User', 'min_seed_points' => 800000],
        self::CLASS_NEXUS_MASTER => ['text' => 'Nexus Master', 'min_seed_points' => 1000000],
        self::CLASS_VIP => ['text' => 'VIP'],
        self::CLASS_RETIREE => ['text' => 'Retiree'],
        self::CLASS_UPLOADER => ['text' => 'Uploader'],
        self::CLASS_MODERATOR => ['text' => 'Moderator'],
        self::CLASS_ADMINISTRATOR => ['text' => 'Administrator'],
        self::CLASS_SYSOP => ['text' => 'Sysop'],
        self::CLASS_STAFF_LEADER => ['text' => 'Staff Leader'],
    ];

    const DONATE_YES = 'yes';

    const DONATE_NO = 'no';

    public static $donateStatus = [
        self::DONATE_YES => ['text' => 'Yes'],
        self::DONATE_NO => ['text' => 'No'],
    ];

    const GENDER_FEMALE = 'Female';

    const GENDER_MALE = 'Male';

    const GENDER_UNKNOWN = 'N/A';

    public static array $genders = [
        self::GENDER_MALE => 'Male',
        self::GENDER_FEMALE => 'Female',
        self::GENDER_UNKNOWN => 'N/A',
    ];

    public static array $cardTitles = [
        'uploaded_human' => '上传量',
        'downloaded_human' => '下载量',
        'share_ratio' => '分享率',
        //        'seed_time' => '做种时间',
        'bonus' => '魔力值',
        'seed_points' => '做种积分',
        'invites' => '邀请',
    ];

    public static array $notificationOptions = ['topic_reply', 'hr_reached'];

    private const USER_ENABLE_LATELY = 'user_enable_lately:%s';

    public function getConnectionName()
    {
        return NexusDB::getConnectionName();
    }

    public static function getUserEnableLatelyCacheKey(int $userId): string
    {
        return sprintf(self::USER_ENABLE_LATELY, $userId);
    }

    public function getClassTextAttribute(): string
    {
        return self::getClassText($this->class);
    }

    public static function getClassText($class)
    {
        if (! is_numeric($class) || ! isset(self::$classes[$class])) {
            return '';
        }
        $classText = self::$classes[$class]['text'];
        if ($class >= self::CLASS_VIP) {
            $alias = nexus_trans('user.class_names.'.$class);
        } else {
            $alias = Setting::get("account.{$class}_alias");
        }
        if (! empty($alias)) {
            $classText .= "({$alias})";
        }

        return $classText;
    }

    public static function listClass($min = self::CLASS_PEASANT, $max = self::CLASS_STAFF_LEADER): array
    {
        $result = [];
        foreach (self::$classes as $class => $info) {
            if ($class >= $min && $class <= $max) {
                $result[$class] = self::getClassText($class);
            }
        }

        return $result;
    }

    public static function exists($id): bool
    {
        return self::query()->where('id', $id)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * @see ExamRepository::isExamMatchUser()
     */
    public function getDonateStatusAttribute(): string
    {
        if ($this->isDonating()) {
            return self::DONATE_YES;
        }

        return self::DONATE_NO;
    }

    /**
     * 为数组 / JSON 序列化准备日期。
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'email', 'passhash', 'secret', 'stylesheet', 'editsecret', 'added', 'enabled', 'status',
        'leechwarn', 'leechwarnuntil', 'page', 'class', 'uploaded', 'downloaded', 'clientselect', 'showclienterror', 'last_home',
        'seedbonus', 'downloadpos', 'vip_added', 'vip_until', 'title', 'invites', 'attendance_card',
        'seed_points_per_hour', 'passkey', 'auth_key', 'last_login', 'lang', 'provider_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'secret', 'passhash', 'passkey', 'auth_key',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'added' => 'datetime',
        'last_login' => 'datetime',
        'last_access' => 'datetime',
        'last_home' => 'datetime',
        'last_offer' => 'datetime',
        'forum_access' => 'datetime',
        'last_staffmsg' => 'datetime',
        'last_pm' => 'datetime',
        'last_comment' => 'datetime',
        'last_post' => 'datetime',
        'lastwarned' => 'datetime',
        'last_browse' => 'datetime:U',
        'last_music' => 'datetime:U',
        'last_catchup' => 'datetime:U',
        'donoruntil' => 'datetime',
        'warneduntil' => 'datetime',
        'noaduntil' => 'datetime',
        'vip_until' => 'datetime',
        'leechwarnuntil' => 'datetime',
    ];

    public static array $commonFields = [
        'id', 'username', 'email', 'class', 'status', 'added', 'avatar', 'passkey',
        'uploaded', 'downloaded', 'seedbonus', 'seedtime', 'leechtime',
        'invited_by', 'enabled', 'seed_points', 'last_access', 'invites',
        'lang', 'attendance_card', 'privacy', 'noad', 'downloadpos', 'donoruntil', 'donor',
        'downloadpos', 'vip_added', 'vip_until', 'title', 'invites', 'attendance_card',
        'seed_points_per_hour',
    ];

    public static function getDefaultUserAttributes(): array
    {
        return [
            'id' => 0,
            'username' => nexus_trans('user.deleted_username'),
            'class' => self::CLASS_PEASANT,
            'email' => '',
            'status' => self::STATUS_CONFIRMED,
            'added' => '1970-01-01 08:00:00',
            'avatar' => '',
            'uploaded' => 0,
            'downloaded' => 0,
            'seedbonus' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'enabled' => self::ENABLED_NO,
            'seed_points' => 0,
        ];
    }

    public static function defaultUser(): static
    {
        // @phpstan-ignore-next-line new.static
        return new static(self::getDefaultUserAttributes());
    }

    public static function getClassName($class, $compact = false, $b_colored = false, $I18N = false)
    {
        $class_name = self::$classes[$class]['text'] ?? '';
        if ($class >= self::CLASS_VIP && $I18N) {
            $class_name = nexus_trans("user.class_names.$class");
        }
        $class_name_color = self::$classes[$class]['text'] ?? '';
        if ($compact) {
            $class_name = str_replace(' ', '', $class_name);
        }
        if ($class_name && $b_colored) {
            return "<b class='".str_replace(' ', '', $class_name_color)."_Name'>".$class_name.'</b>';
        }

        return $class_name;
    }

    public function checkIsNormal(array $fields = ['status', 'enabled']): bool
    {
        $params = [
            'user_id' => $this->id,
            'username' => $this->username,
        ];
        if (in_array('status', $fields) && $this->getAttribute('status') != self::STATUS_CONFIRMED) {
            throw new NexusException(nexus_trans('user.user_is_not_confirmed', $params));
        }
        if (in_array('enabled', $fields) && $this->getAttribute('enabled') != self::ENABLED_YES) {
            throw new NexusException(nexus_trans('user.user_is_disabled', $params));
        }

        return true;
    }

    /**
     * Render this User in the shape that legacy procedural code expects
     * for the global `$CURUSER` array (see `userlogin()` /
     * `get_user_from_cookie()` in `include/`).
     *
     * Mirrors the legacy contract: every column from the `users` row,
     * minus the credential fields (`auth_key`, `passhash`) that legacy
     * code never reads off `$CURUSER`. `seedbonus` is forced to float
     * because the legacy code (e.g. `KPS()`) assumes numeric.
     *
     * Modern Laravel callers should use the `User` model directly. This
     * method exists for migration adapters: a Laravel route that proxies
     * to a legacy include can populate `$GLOBALS['CURUSER']` without
     * round-tripping through `dbconn()` / `userlogin()`.
     *
     * Phase 1 of the legacy migration. See `docs/migration-recipe.md`.
     *
     * @return array<string,mixed>
     */
    public function toLegacyArray(): array
    {
        $row = $this->makeVisible(['passkey', 'secret', 'editsecret'])->toArray();
        unset($row['auth_key'], $row['passhash']);
        if (array_key_exists('seedbonus', $row)) {
            $row['seedbonus'] = (float) $row['seedbonus'];
        }

        return $row;
    }

    public function getLocaleAttribute()
    {
        $locale = null;
        $log = 'user: '.$this->id;
        if (get_user_id() == $this->id) {
            $locale = Locale::getLocaleFromCookie();
            $log .= ", locale from cookie: $locale";
        }
        if (! $locale) {
            $lang = $this->language?->site_lang_folder ?? null;
            $locale = Locale::$languageMaps[$lang] ?? $lang;
            $log .= ", [NO_DATA_FROM_COOKIE], lang from database: $lang, locale: $locale";
        }
        do_log($log);

        return $locale;
    }

    public function getSiteLangFolderAttribute()
    {
        $result = optional($this->language)->site_lang_folder;
        if ($result && in_array($result, ['en', 'chs', 'cht'])) {
            return $result;
        }

        return 'en';
    }

    protected function uploadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => mksize($attributes['uploaded'])
        );
    }

    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => mksize($attributes['downloaded'])
        );
    }

    protected function genderText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => nexus_trans('user.genders.'.$attributes['gender'])
        );
    }

    protected function getTwoFactorAuthenticationStatusAttribute(): string
    {
        return $this->two_step_secret != '' ? 'yes' : 'no';
    }

    public static function getMinSeedPoints($class)
    {
        $setting = Setting::get("account.{$class}_min_seed_points");
        if (is_numeric($setting)) {
            return $setting;
        }

        return self::$classes[$class]['min_seed_points'] ?? false;
    }

    public function scopeNormal(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED)->where('enabled', self::ENABLED_YES);
    }

    public function scopeDonating(Builder $query): Builder
    {
        return $query->where('donor', 'yes')->where(function (Builder $query) {
            return $query->whereNull('donoruntil')
                ->orWhere('donoruntil', '>=', now());
        });
    }

    public function exams(): HasMany
    {
        return $this->hasMany(ExamUser::class, 'uid');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang');
    }

    public function invitee_code()
    {
        return $this->hasOne(Invite::class, 'invitee_register_uid');
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function temporary_invites()
    {
        return $this->hasMany(Invite::class, 'inviter')
            ->where('invitee', '')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', Carbon::now());
    }

    public function send_messages()
    {
        return $this->hasMany(Message::class, 'sender');
    }

    public function receive_messages()
    {
        return $this->hasMany(Message::class, 'receiver');
    }

    /**
     * torrent comments
     *
     * @return HasMany
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user');
    }

    /**
     * forum posts
     *
     * @return HasMany
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'userid');
    }

    public function torrents()
    {
        return $this->hasMany(Torrent::class, 'owner');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'userid');
    }

    public function peers_torrents()
    {
        return $this->hasManyThrough(
            Torrent::class,
            Peer::class,
            'userid',
            'id',
            'id',
            'torrent');
    }

    public function snatched_torrents()
    {
        return $this->hasManyThrough(
            Torrent::class,
            Snatch::class,
            'userid',
            'id',
            'id',
            'torrentid');
    }

    public function seeding_torrents()
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_YES);
    }

    public function leeching_torrents()
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_NO);
    }

    public function completed_torrents()
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_YES);
    }

    public function incomplete_torrents()
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_NO);
    }

    public function hitAndRuns(): HasMany
    {
        return $this->hasMany(HitAndRun::class, 'uid');
    }

    public function medals(): BelongsToMany
    {
        return $this->belongsToMany(Medal::class, 'user_medals', 'uid', 'medal_id')
            ->withPivot(['id', 'expire_at', 'status', 'priority', 'bonus_addition_expire_at'])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc');
    }

    public function valid_medals(): BelongsToMany
    {
        return $this->medals()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

    public function wearing_medals(): BelongsToMany
    {
        return $this->valid_medals()->where('user_medals.status', UserMedal::STATUS_WEARING);
    }

    public function reward_torrent_logs(): HasMany
    {
        return $this->hasMany(Reward::class, 'userid');
    }

    public function thank_torrent_logs(): HasMany
    {
        return $this->hasMany(Thank::class, 'userid');
    }

    public function poll_answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'userid');
    }

    public function metas()
    {
        return $this->hasMany(UserMeta::class, 'uid');
    }

    public function usernameChangeLogs()
    {
        return $this->hasMany(UsernameChangeLog::class, 'uid');
    }

    public function roles()
    {
        // @phpstan-ignore-next-line class.notFound
        return $this->belongsToMany(Role::class, 'user_roles', 'uid', 'role_id')->withTimestamps();
    }

    public function directPermissions()
    {
        // @phpstan-ignore-next-line class.notFound
        return $this->hasMany(UserPermission::class, 'uid');
    }

    public function examAndTasks(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_users', 'uid', 'exam_id');
    }

    public function onGoingExamAndTasks(): BelongsToMany
    {
        return $this->examAndTasks()->wherePivot('status', ExamUser::STATUS_NORMAL);
    }

    public function modifyLogs(): HasMany
    {
        return $this->hasMany(UserModifyLog::class, 'user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'uid');
    }

    public function getAvatarAttribute($value)
    {
        if ($value) {
            if (substr($value, 0, 4) == 'http') {
                return $value;
            } else {
                do_log("user: {$this->id} avatar: $value is not valid url.");
            }
        }

        return getSchemeAndHttpHost().'/pic/default_avatar.png';

    }

    public function updateWithModComment(array $update, $modComment): bool
    {
        return $this->updateWithComment($update, $modComment, 'modcomment');
    }

    public function updateWithComment(array $update, $comment, $commentField): bool
    {
        if (! $this->exists) {
            throw new \RuntimeException('This method only works when user exists !');
        }
        // @todo how to do prepare bindings here ?
        //        $comment = addslashes($comment);
        //        do_log("update: " . json_encode($update) . ", $commentField: $comment", 'notice');
        //        $update[$commentField] = NexusDB::raw("if($commentField = '', '$comment', concat_ws('\n', '$comment', $commentField))");

        if ($commentField != 'modcomment') {
            throw new \RuntimeException("unsupported commentField: $commentField !");
        }

        return NexusDB::transaction(function () use ($update, $comment) {
            $this->modifyLogs()->create(['content' => $comment]);

            return $this->update($update);
        });
    }

    public function canAccessAdmin(): bool
    {
        $targetClass = self::getAccessAdminClassMin();
        if (! $this->class || $this->class < $targetClass) {
            do_log(sprintf('user: %s, no class or class < %s, can not access admin.', $this->id, $targetClass));

            return false;
        }

        return true;
    }

    public static function getAccessAdminClassMin()
    {
        return Setting::get('system.access_admin_class_min') ?: User::CLASS_ADMINISTRATOR;
    }

    public function isDonating(): bool
    {
        $rawDonorUntil = $this->getRawOriginal('donoruntil');
        if (
            $this->donor == 'yes'
            && ($rawDonorUntil === null || $rawDonorUntil == '0000-00-00 00:00:00' || $this->donoruntil->gte(Carbon::now()))
        ) {
            return true;
        }

        return false;
    }

    public function acceptNotification($name): bool
    {
        return is_null($this->original['notifs']) || str_contains($this->notifs, "[{$name}]");
    }

    public function tokenCan(string $ability): bool
    {
        $redis = NexusDB::redis();
        $cacheKey = Setting::USER_TOKEN_PERMISSION_ALLOWED_CACHE_KRY;
        if (! $redis->exists($cacheKey)) {
            $lockKey = "$cacheKey:lock";
            if ($redis->set($lockKey, 1, ['nx', 'ex' => 5])) {
                try {
                    if (! $redis->exists($cacheKey)) {
                        $abilities = TokenRepository::listUserTokenPermissions(false);
                        do_log('load user token permissions: '.json_encode($abilities), 'alert');
                        if (! empty($abilities)) {
                            $redis->sadd($cacheKey, ...$abilities);
                        } else {
                            $redis->sadd($cacheKey, '__NO_USER_TOKEN_PERMISSION__');
                            $redis->expire($cacheKey, 900);
                        }
                    }
                } catch (\Throwable $throwable) {
                    do_log($throwable->getMessage(), 'error');
                } finally {
                    $redis->del($lockKey);
                }
            }
        }

        return $redis->sismember($cacheKey, $ability)
            && $this->accessToken && $this->accessToken->can($ability);
    }
}
