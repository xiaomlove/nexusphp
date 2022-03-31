<?php

namespace App\Models;

use App\Repositories\TagRepository;
use JeroenG\Explorer\Application\Explored;
use Laravel\Scout\Searchable;

class Torrent extends NexusModel
{
    protected $fillable = [
        'name', 'filename', 'save_as', 'descr', 'small_descr', 'ori_descr',
        'category', 'source', 'medium', 'codec', 'standard', 'processing', 'team', 'audiocodec',
        'size', 'added', 'type', 'numfiles', 'owner', 'nfo', 'sp_state', 'promotion_time_type',
        'promotion_until', 'anonymous', 'url', 'pos_state', 'cache_stamp', 'picktype', 'picktime',
        'last_reseed', 'pt_gen', 'technical_info'
    ];

    private static $globalPromotionState;

    const VISIBLE_YES = 'yes';
    const VISIBLE_NO = 'no';

    const BANNED_YES = 'yes';
    const BANNED_NO = 'no';

    protected $casts = [
        'added' => 'datetime'
    ];

    public static $commentFields = [
        'id', 'name', 'added', 'visible', 'banned', 'owner', 'sp_state', 'pos_state', 'hr', 'picktype', 'picktime',
        'last_action', 'leechers', 'seeders', 'times_completed', 'views', 'size'
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
        $global = self::getGlobalPromotionState();
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

    public static function getGlobalPromotionState()
    {
        if (is_null(self::$globalPromotionState)) {
            $result = TorrentState::query()->first(['global_sp_state']);
            if (!$result) {
                do_log("global sp state no value", 'error');
            }
            self::$globalPromotionState = $result->global_sp_state ?? false;
        }
        return self::$globalPromotionState;
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

    public static function getBasicInfo(): array
    {
        $result = [];
        foreach (self::$basicRelations as $relation) {
            $result[$relation] = nexus_trans("torrent.show.$relation");
        }
        return $result;
    }

    public static function listPosStates(): array
    {
        $result = self::$posStates;
        foreach ($result as $key => &$value) {
            $value['text'] = nexus_trans('torrent.pos_state_' . $key);
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
}
