<?php

namespace App\Models;


class TorrentCustomField extends NexusModel
{
    protected $table = 'torrents_custom_fields';

    protected $fillable = [
        'name', 'label', 'type', 'required', 'is_single_row', 'options', 'help'
    ];

    public static function getCheckboxOptions(): array
    {
        $result = [];
        $records = self::query()->get();
        foreach ($records as $value) {
            $result[$value->id] = sprintf('%s[%s]', $value->name, $value->label);
        }
        return $result;
    }
}
