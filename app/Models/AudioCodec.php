<?php

namespace App\Models;


class AudioCodec extends NexusModel
{
    protected $table = 'audiocodecs';

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_audio_codec_label');
    }
}
