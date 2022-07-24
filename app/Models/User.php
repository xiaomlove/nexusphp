<?php

namespace App\Models;

use App\Http\Middleware\Locale;
use App\Repositories\ExamRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Nexus\Database\NexusDB;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable, HasApiTokens;

    public $timestamps = false;

    protected $perPage = 50;

    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PENDING = 'pending';

    const ENABLED_YES = 'yes';
    const ENABLED_NO = 'no';

    const CLASS_PEASANT = "0";
    const CLASS_USER = "1";
    const CLASS_POWER_USER = "2";
    const CLASS_ELITE_USER = "3";
    const CLASS_CRAZY_USER = "4";
    const CLASS_INSANE_USER = "5";
    const CLASS_VETERAN_USER = "6";
    const CLASS_EXTREME_USER = "7";
    const CLASS_ULTIMATE_USER = "8";
    const CLASS_NEXUS_MASTER = "9";
    const CLASS_VIP = "10";
    const CLASS_RETIREE = "11";
    const CLASS_UPLOADER = "12";
    const CLASS_MODERATOR = "13";
    const CLASS_ADMINISTRATOR = "14";
    const CLASS_SYSOP = "15";
    const CLASS_STAFF_LEADER = "16";

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
        self::CLASS_VIP => ['text' => 'Vip'],
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

    public static array $cardTitles = [
        'uploaded_human' => '上传量',
        'downloaded_human' => '下载量',
        'share_ratio' => '分享率',
//        'seed_time' => '做种时间',
        'bonus' => '魔力值',
        'seed_points' => '做种积分',
        'invites' => '邀请',
    ];


    public function getClassTextAttribute(): string
    {
        if (!isset(self::$classes[$this->class]['text'])) {
            return '';
        }
        $classText = self::$classes[$this->class]['text'];
        $alias = Setting::get("account.{$this->class}_alias");
        if (!empty($alias)) {
            $classText .= "({$alias})";
        }
        return $classText;
    }

    public function canAccessFilament(): bool
    {
        return $this->canAccessAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * @see ExamRepository::isExamMatchUser()
     *
     * @return string
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
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'email', 'passhash', 'secret', 'stylesheet', 'editsecret', 'added', 'modcomment', 'enabled', 'status',
        'leechwarn', 'leechwarnuntil', 'page', 'class', 'uploaded', 'downloaded', 'clientselect', 'showclienterror', 'last_home',
        'seedbonus', 'bonuscomment', 'downloadpos', 'vip_added', 'vip_until', 'title',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'secret', 'passhash',
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

    public static $commonFields = [
        'id', 'username', 'email', 'class', 'status', 'added', 'avatar',
        'uploaded', 'downloaded', 'seedbonus', 'seedtime', 'leechtime',
        'invited_by', 'enabled', 'seed_points', 'last_access', 'invites',
        'lang', 'attendance_card', 'privacy', 'noad', 'downloadpos',
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
            'seed_points' => 0
        ];
    }

    public static function defaultUser(): static
    {
        return new static(self::getDefaultUserAttributes());
    }

    public static function getClassName($class, $compact = false, $b_colored = false, $I18N = false)
    {
        $class_name = self::$classes[$class]['text'];
        if ($class >= self::CLASS_VIP && $I18N) {
            $class_name = nexus_trans("user.class_names.$class");
        }
        $class_name_color = self::$classes[$class]['text'];
        if ($compact) {
            $class_name = str_replace(" ", "",$class_name);
        }
        if ($b_colored) {
            return "<b class='" . str_replace(" ", "",$class_name_color) . "_Name'>" . $class_name . "</b>";
        }
        return $class_name;
    }

    public function checkIsNormal(array $fields = ['status', 'enabled']): bool
    {
        if (in_array('status', $fields) && $this->getAttribute('status') != self::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException(sprintf('User: %s is not confirmed.', $this->id));
        }
        if (in_array('enabled', $fields) && $this->getAttribute('enabled') != self::ENABLED_YES) {
            throw new \InvalidArgumentException(sprintf('User: %s is not enabled.', $this->id));
        }

        return true;
    }

    public function getLocaleAttribute()
    {
        $locale = Locale::getLocaleFromCookie();
        $log = "locale from cookie: $locale";
        if (!$locale) {
            $lang = $this->language->site_lang_folder;
            $locale = Locale::$languageMaps[$lang] ?? 'en';
            $log .= ", [NO_DATA], lang from database: $lang, locale: $locale";
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
            get: fn($value, $attributes) => mksize($attributes['uploaded'])
        );
    }

    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => mksize($attributes['downloaded'])
        );
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


    public function exams()
    {
        return $this->hasMany(ExamUser::class, 'uid');
    }

    public function language()
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user');
    }

    /**
     * forum posts
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'userid');
    }

    public function torrents()
    {
        return $this->hasMany(Torrent::class, 'owner');
    }

    public function bookmarks(): \Illuminate\Database\Eloquent\Relations\HasMany
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


    public function hitAndRuns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HitAndRun::class, 'uid');
    }

    public function medals(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Medal::class, 'user_medals', 'uid', 'medal_id')
            ->withPivot(['id', 'expire_at', 'status'])
            ->withTimestamps()
            ->orderByPivot('id', 'desc')
            ;
    }

    public function valid_medals(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->medals()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

    public function wearing_medals(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->valid_medals()->where('user_medals.status', UserMedal::STATUS_WEARING);
    }

    public function reward_torrent_logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reward::class, 'userid');
    }

    public function thank_torrent_logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Thank::class, 'userid');
    }

    public function poll_answers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PollAnswer::class, 'userid');
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

        return getSchemeAndHttpHost() . '/pic/default_avatar.png';

    }

    public function updateWithModComment(array $update, $modComment): bool
    {
        return $this->updateWithComment($update, $modComment, 'modcomment');
    }

    public function updateWithComment(array $update, $comment, $commentField): bool
    {
        if (!$this->exists) {
            throw new \RuntimeException('This method only works when user exists !');
        }
        //@todo how to do prepare bindings here ?
        $comment = addslashes($comment);
        do_log("update: " . json_encode($update) . ", $commentField: $comment", 'notice');
        $update[$commentField] = NexusDB::raw("if($commentField = '', '$comment', concat_ws('\n', '$comment', $commentField))");
        return $this->update($update);
    }

    public function canAccessAdmin(): bool
    {
        $targetClass = self::CLASS_ADMINISTRATOR;
        if (!$this->class || $this->class < $targetClass) {
            do_log(sprintf('user: %s, no class or class < %s, can not access admin.', $this->id, $targetClass));
            return false;
        }
        return true;
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



}
