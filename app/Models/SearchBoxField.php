<?php

namespace App\Models;

class SearchBoxField extends NexusModel
{
    protected $table = 'searchbox_fields';

    protected $fillable = ['searchbox_id', 'field_type', 'field_id', ];

    const FIELD_TYPE_SOURCE = 'source';
    const FIELD_TYPE_MEDIUM = 'medium';
    const FIELD_TYPE_CODEC = 'codec';
    const FIELD_TYPE_AUDIO_CODEC = 'audio_codec';
    const FIELD_TYPE_STANDARD = 'standard';
    const FIELD_TYPE_PROCESSING = 'processing';
    const FIELD_TYPE_TEAM = 'team';
    const FIELD_TYPE_CUSTOM = 'custom';

    public static $fieldTypes = [
        self::FIELD_TYPE_SOURCE => ['text' => 'Source', 'model' => Source::class],
        self::FIELD_TYPE_MEDIUM => ['text' => 'Medium', 'model' => Media::class],
        self::FIELD_TYPE_CODEC => ['text' => 'Codec', 'model' => Codec::class],
        self::FIELD_TYPE_AUDIO_CODEC => ['text' => 'Audio codec', 'model' => AudioCodec::class],
        self::FIELD_TYPE_STANDARD => ['text' => 'Standard', 'model' => Standard::class],
        self::FIELD_TYPE_PROCESSING => ['text' => 'Processing', 'model' => Processing::class],
        self::FIELD_TYPE_TEAM => ['text' => 'Team', 'model' => Team::class],
        self::FIELD_TYPE_CUSTOM => ['text' => 'Custom', ],
    ];


    public function searchBox(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SearchBox::class, 'searchbox_id');
    }




}
