<?php

namespace App\Models;

use App\Repositories\TagRepository;
use Illuminate\Database\Eloquent\Casts\Attribute;
use JeroenG\Explorer\Application\Explored;
use Laravel\Scout\Searchable;

class Torrent extends NexusModel
{
    protected $fillable = [
        'name', 'filename', 'save_as', 'descr', 'small_descr', 'ori_descr',
        'category', 'source', 'medium', 'codec', 'standard', 'processing', 'team', 'audiocodec',
        'size', 'added', 'type', 'numfiles', 'owner', 'nfo', 'sp_state', 'promotion_time_type',
        'promotion_until', 'anonymous', 'url', 'pos_state', 'cache_stamp', 'picktype', 'picktime',
        'last_reseed', 'pt_gen', 'technical_info', 'leechers', 'seeders', 'cover', 'last_action',
        'times_completed', 'approval_status', 'banned', 'visible',
    ];

    private static $globalPromotionState;

    const VISIBLE_YES = 'yes';
    const VISIBLE_NO = 'no';

    const BANNED_YES = 'yes';
    const BANNED_NO = 'no';

    protected $casts = [
        'added' => 'datetime',
        'pt_gen' => 'array',
        'promotion_until' => 'datetime',
    ];

    public static $commentFields = [
        'id', 'name', 'added', 'visible', 'banned', 'owner', 'sp_state', 'pos_state', 'hr', 'picktype', 'picktime',
        'last_action', 'leechers', 'seeders', 'times_completed', 'views', 'size', 'cover', 'anonymous', 'approval_status'
    ];

    public static $basicRelations = [
        'basic_category', 'basic_audio_codec', 'basic_codec', 'basic_media',
        'basic_source', 'basic_standard', 'basic_team',
    ];

    const POS_STATE_STICKY_NONE = 'normal';
    const POS_STATE_STICKY_FIRST = 'sticky';
    /**
     * alphabet 'r' is  after 'n' and before 's', so it will fit: order by pos_state desc,
     * first sticky, then r_sticky, then normal
     */
    const POS_STATE_STICKY_SECOND = 'r_sticky';

    public static $posStates = [
        self::POS_STATE_STICKY_NONE => ['text' => 'Normal', 'icon_counts' => 0],
        self::POS_STATE_STICKY_SECOND => ['text' => 'Sticky second', 'icon_counts' => 1],
        self::POS_STATE_STICKY_FIRST => ['text' => 'Sticky first', 'icon_counts' => 2],
    ];

    const HR_YES = 1;
    const HR_NO = 0;

    public static $hrStatus = [
        self::HR_NO => ['text' => 'NO'],
        self::HR_YES => ['text' => 'YES'],
    ];

    const PROMOTION_NORMAL = 1;
    const PROMOTION_FREE = 2;
    const PROMOTION_TWO_TIMES_UP = 3;
    const PROMOTION_FREE_TWO_TIMES_UP = 4;
    const PROMOTION_HALF_DOWN = 5;
    const PROMOTION_HALF_DOWN_TWO_TIMES_UP = 6;
    const PROMOTION_ONE_THIRD_DOWN = 7;

    public static array $promotionTypes = [
        self::PROMOTION_NORMAL => [
            'text' => 'Normal',
            'up_multiplier' => 1,
            'down_multiplier' => 1,
            'color' => ''
        ],
        self::PROMOTION_FREE => [
            'text' => 'Free',
            'up_multiplier' => 1,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,52,206,0.5), rgba(0,52,206,1), rgba(0,52,206,0.5))'
        ],
        self::PROMOTION_TWO_TIMES_UP => [
            'text' => '2X',
            'up_multiplier' => 2,
            'down_multiplier' => 1,
            'color' => 'linear-gradient(to right, rgba(0,153,0,0.5), rgba(0,153,0,1), rgba(0,153,0,0.5))'
        ],
        self::PROMOTION_FREE_TWO_TIMES_UP => [
            'text' => '2X Free',
            'up_multiplier' => 2,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(0,52,206,1)'
        ],
        self::PROMOTION_HALF_DOWN => [
            'text' => '50%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(220,0,3,0.5), rgba(220,0,3,1), rgba(220,0,3,0.5))'
        ],
        self::PROMOTION_HALF_DOWN_TWO_TIMES_UP => [
            'text' => '2X 50%',
            'up_multiplier' => 2,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(220,0,3,1)'
        ],
        self::PROMOTION_ONE_THIRD_DOWN => [
            'text' => '30%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.3,
            'color' => 'linear-gradient(to right, rgba(65,23,73,0.5), rgba(65,23,73,1), rgba(65,23,73,0.5))'
        ],
    ];

    const PICK_NORMAL = 'normal';
    const PICK_HOT = 'hot';
    const PICK_CLASSIC = 'classic';
    const PICK_RECOMMENDED = 'recommended';

    public static array $pickTypes = [
        self::PICK_NORMAL => ['text' => self::PICK_NORMAL, 'color' => ''],
        self::PICK_HOT => ['text' => self::PICK_HOT, 'color' => '#e78d0f'],
        self::PICK_CLASSIC => ['text' => self::PICK_CLASSIC, 'color' => '#77b300'],
        self::PICK_RECOMMENDED => ['text' => self::PICK_RECOMMENDED, 'color' => '#820084'],
    ];

    const BONUS_REWARD_VALUES = [50, 100, 200, 500, 1000];

    const APPROVAL_STATUS_NONE = 0;
    const APPROVAL_STATUS_ALLOW = 1;
    const APPROVAL_STATUS_DENY = 2;

    public static array $approvalStatus = [
        self::APPROVAL_STATUS_NONE => [
            'text' => 'None',
            'badge_color' => 'primary',
            'icon' => '<svg t="1655184824967" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="34118" width="16" height="16"><path d="M450.267 772.245l0 92.511 92.511 0 0-92.511L450.267 772.245zM689.448 452.28c13.538-24.367 20.311-50.991 20.311-79.875 0-49.938-19.261-92.516-57.765-127.713-38.517-35.197-90.114-52.8-154.797-52.8-61.077 0-110.191 16.4-147.342 49.188-37.16 32.798-59.497 80.032-67.014 141.703l83.486 9.927c7.218-46.025 22.41-79.875 45.576-101.533 23.166-21.665 52.047-32.494 86.647-32.494 35.802 0 66.038 11.957 90.711 35.874 24.667 23.92 37.01 51.675 37.01 83.266 0 17.451-4.222 33.55-12.642 48.284-8.425 14.747-26.698 34.526-54.83 59.346s-47.607 43.701-58.442 56.637c-14.741 17.754-25.424 35.354-32.037 52.797-9.028 23.172-13.537 50.701-13.537 82.584 0 5.418 0.146 13.539 0.45 24.374l78.069 0c0.599-32.495 2.855-55.966 6.772-70.4 3.903-14.44 9.926-27.229 18.047-38.363 8.127-11.123 25.425-28.43 51.901-51.895C649.43 506.288 675.908 476.656 689.448 452.28L689.448 452.28z" p-id="34119" fill="#e78d0f"></path></svg>',
        ],
        self::APPROVAL_STATUS_ALLOW => [
            'text' => 'Allow',
            'badge_color' => 'success',
            'icon' => '<svg t="1655145688503" class="icon" viewBox="0 0 1413 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="16225" width="16" height="16"><path d="M1381.807797 107.47394L1274.675044 0.438669 465.281736 809.880718l-322.665524-322.714266L35.434718 594.152982l430.041982 430.041982 107.084012-107.035271-0.243705-0.292446z" fill="#1afa29" p-id="16226"></path></svg>',
        ],
        self::APPROVAL_STATUS_DENY => [
            'text' => 'Deny',
            'badge_color' => 'danger',
            'icon' => '<svg t="1655184952662" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="35029" width="16" height="16"><path d="M220.8 812.8l22.4 22.4 272-272 272 272 48-44.8-275.2-272 275.2-272-48-48-272 275.2-272-275.2-22.4 25.6-22.4 22.4 272 272-272 272z" fill="#d81e06" p-id="35030"></path></svg>',
        ],
    ];

    public function getPickInfoAttribute()
    {
        $info = self::$pickTypes[$this->picktype] ?? null;
        if ($info) {
            $info['text'] = nexus_trans('torrent.pick_info.' . $this->picktype);
            return $info;
        }
    }

    public function getPromotionInfoAttribute()
    {
        return self::$promotionTypes[$this->sp_state_real] ?? null;
    }

    public function getSpStateRealTextAttribute()
    {
        $spStateReal = $this->sp_state_real;
        return self::$promotionTypes[$spStateReal]['text'] ?? '';
    }

    public function getSpStateRealAttribute()
    {
        if ($this->getRawOriginal('sp_state') === null) {
            throw new \RuntimeException('no select sp_state field');
        }
        $spState = $this->sp_state;
        $global = get_global_sp_state();
        $log = sprintf('torrent: %s sp_state: %s, global sp state: %s', $this->id, $spState, $global);
        if ($global != self::PROMOTION_NORMAL) {
            $spState = $global;
            $log .= sprintf(", global != %s, set sp_state to global: %s", self::PROMOTION_NORMAL, $global);
        }
        if (!isset(self::$promotionTypes[$spState])) {
            $log .= ", but now sp_state: $spState, is invalid, reset to: " . self::PROMOTION_NORMAL;
            $spState = self::PROMOTION_NORMAL;
        }
        do_log($log, 'debug');
        return $spState;
    }

    protected function posStateText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => nexus_trans('torrent.pos_state_' . $attributes['pos_state'])
        );
    }

    protected function approvalStatusText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => nexus_trans('torrent.approval.status_text.' . $attributes['approval_status'])
        );
    }

    protected function spStateText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => self::$promotionTypes[$this->sp_state]['text'] ?? ''
        );
    }

    public static function getFieldsForList($appendTableName = false): array|bool
    {
        $fields = 'id, sp_state, promotion_time_type, promotion_until, banned, picktype, pos_state, category, source, medium, codec, standard, processing, team, audiocodec, leechers, seeders, name, small_descr, times_completed, size, added, comments,anonymous,owner,url,cache_stamp, pt_gen, hr, approval_status';
        $fields = preg_split('/[,\s]+/', $fields);
        if ($appendTableName) {
            foreach ($fields as &$value) {
                $value = "torrents." . $value;
            }
        }
        return $fields;
    }

    public static function listApprovalStatus($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$approvalStatus;
        $keyValue = [];
        foreach ($result as $status => &$info) {
            $text = nexus_trans("torrent.approval.status_text.$status");
            $info['text'] = $text;
            $keyValue[$status] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }
        return $result;
    }

    public static function listPromotionTypes($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$promotionTypes;
        $keyValue = [];
        foreach ($result as $status => &$info) {
            $text = $info['text'];
            $info['text'] = $text;
            $keyValue[$status] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }
        return $result;
    }

    public function getHrAttribute(): string
    {
        $hrMode = Setting::get('hr.mode');
        if ($hrMode == HitAndRun::MODE_GLOBAL) {
            return self::HR_YES;
        }
        if ($hrMode == HitAndRun::MODE_DISABLED) {
            return self::HR_NO;
        }
        return $this->getRawOriginal('hr');
    }

    public function getHrTextAttribute()
    {
        return self::$hrStatus[$this->hr] ?? '';
    }

    public function getTagsFormattedAttribute(): string
    {
        $html = [];
        foreach ($this->tags as $tag) {
            $html[] = sprintf(
                '<span style="color: %s;background-color: %s;border-radius: %s;font-size: %s;padding: %s;margin: %s">%s</span>',
                $tag->font_color, $tag->color, $tag->border_radius, $tag->font_size, $tag->padding, $tag->margin, $tag->name
            );
        }
        return implode('', $html);
    }

    public static function getBasicInfo(): array
    {
        $result = [];
        foreach (self::$basicRelations as $relation) {
            $result[$relation] = nexus_trans("torrent.show.$relation");
        }
        return $result;
    }

    public static function listPosStates($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$posStates;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $value['text'] = nexus_trans('torrent.pos_state_' . $key);
            $keyValues[$key] = $value[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    public static function getFieldLabels(): array
    {
        $fields = [
            'comments', 'times_completed', 'peers_count', 'thank_users_count', 'numfiles', 'bookmark_yes', 'bookmark_no',
            'reward_yes', 'reward_no', 'reward_logs', 'download', 'thanks_yes', 'thanks_no'
        ];
        $result = [];
        foreach($fields as $field) {
            $result[$field] = nexus_trans("torrent.show.{$field}_label");
        }
        return $result;
    }

    public function checkIsNormal(array $fields = ['visible', 'banned'])
    {
        if (in_array('visible', $fields) && $this->getAttribute('visible') != self::VISIBLE_YES) {
            throw new \InvalidArgumentException(sprintf('Torrent: %s is not visible.', $this->id));
        }
        if (in_array('banned', $fields) && $this->getAttribute('banned') == self::BANNED_YES) {
            throw new \InvalidArgumentException(sprintf('Torrent: %s is banned.', $this->id));
        }

        return true;
    }

    public function bookmarks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Bookmark::class, 'torrentid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner')->withDefault(User::getDefaultUserAttributes());
    }

    public function thanks()
    {
        return $this->hasMany(Thank::class, 'torrentid');
    }

    public function thank_users()
    {
        return $this->belongsToMany(User::class, 'thanks', 'torrentid', 'userid');
    }

    /**
     * 同伴
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function peers()
    {
        return $this->hasMany(Peer::class, 'torrent');
    }

    /**
     * 完成情况
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function snatches()
    {
        return $this->hasMany(Snatch::class, 'torrentid');
    }

    public function upload_peers()
    {
        return $this->peers()->where('seeder', Peer::SEEDER_YES);
    }

    public function download_peers()
    {
        return $this->peers()->where('seeder', Peer::SEEDER_NO);
    }

    public function finish_peers()
    {
        return $this->peers()->where('finishedat', '>', 0);
    }

    public function files()
    {
        return $this->hasMany(File::class, 'torrent');
    }

    public function basic_category()
    {
        return $this->belongsTo(Category::class, 'category');
    }

    public function basic_source()
    {
        return $this->belongsTo(Source::class, 'source');
    }

    public function basic_media()
    {
        return $this->belongsTo(Media::class, 'medium');
    }

    public function basic_codec()
    {
        return $this->belongsTo(Codec::class, 'codec');
    }

    public function basic_standard()
    {
        return $this->belongsTo(Standard::class, 'standard');
    }

    public function basic_processing()
    {
        return $this->belongsTo(Processing::class, 'processing');
    }

    public function basic_team()
    {
        return $this->belongsTo(Team::class, 'team');
    }

    public function basic_audio_codec()
    {
        return $this->belongsTo(AudioCodec::class, 'audiocodec');
    }

    public function scopeVisible($query, $visible = self::VISIBLE_YES)
    {
        $query->where('visible', $visible);
    }

    public function torrent_tags(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TorrentTag::class, 'torrent_id');
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'torrent_tags', 'torrent_id', 'tag_id')
            ->orderByRaw(sprintf("field(`tags`.`id`,%s)", TagRepository::getOrderByFieldIdString()));
    }

    public function reward_logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reward::class, 'torrentid');
    }

    public function operationLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TorrentOperationLog::class, 'torrent_id');
    }
}
