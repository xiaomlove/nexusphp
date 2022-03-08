<?php

namespace App\Models;

class Torrent extends NexusModel
{
    protected $fillable = [
        'name', 'filename', 'save_as', 'descr', 'small_descr', 'ori_descr',
        'category', 'source', 'medium', 'codec', 'standard', 'processing', 'team', 'audiocodec',
        'size', 'added', 'type', 'numfiles', 'owner', 'nfo', 'sp_state', 'promotion_time_type',
        'promotion_until', 'anonymous', 'url', 'pos_state', 'cache_stamp', 'picktype', 'picktime',
        'last_reseed', 'pt_gen', 'tags', 'technical_info'
    ];

    const VISIBLE_YES = 'yes';
    const VISIBLE_NO = 'no';

    const BANNED_YES = 'yes';
    const BANNED_NO = 'no';

    protected $casts = [
        'added' => 'datetime'
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
        $fields = ['comments', 'times_completed', 'peers_count', 'thank_users_count', 'numfiles', 'bookmark_yes', 'bookmark_no'];
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

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'torrent_tags', 'torrent_id', 'tag_id');
    }
}
