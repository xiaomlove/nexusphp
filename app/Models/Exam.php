<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Exam extends NexusModel
{
    protected $fillable = ['name', 'description', 'begin', 'end', 'duration', 'status', 'is_discovered', 'filters', 'indexes', 'priority'];

    public $timestamps = true;

    protected $casts = [
        'filters' => 'object',
        'indexes' => 'array',
    ];

    const STATUS_ENABLED = 0;
    const STATUS_DISABLED = 1;

    public static $status = [
        self::STATUS_ENABLED => ['text' => 'Enabled'],
        self::STATUS_DISABLED => ['text' => 'Disabled'],
    ];

    const DISCOVERED_YES = 1;
    const DISCOVERED_NO = 0;

    public static $discovers = [
        self::DISCOVERED_NO => ['text' => 'No'],
        self::DISCOVERED_YES => ['text' => 'Yes'],
    ];

    const INDEX_UPLOADED = 1;
    const INDEX_SEED_TIME_AVERAGE = 2;
    const INDEX_DOWNLOADED = 3;
    const INDEX_SEED_BONUS = 4;

    public static $indexes = [
        self::INDEX_UPLOADED => ['name' => 'Uploaded', 'unit' => 'GB', 'source_user_field' => 'uploaded'],
        self::INDEX_DOWNLOADED => ['name' => 'Downloaded', 'unit' => 'GB', 'source_user_field' => 'downloaded'],
        self::INDEX_SEED_TIME_AVERAGE => ['name' => 'Seed time average', 'unit' => 'Hour', 'source_user_field' => 'seedtime'],
        self::INDEX_SEED_BONUS => ['name' => 'Bonus', 'unit' => '', 'source_user_field' => 'seedbonus'],
    ];

    const FILTER_USER_CLASS = 'classes';
    const FILTER_USER_REGISTER_TIME_RANGE = 'register_time_range';
    const FILTER_USER_DONATE = 'donate_status';

    public static $filters = [
        self::FILTER_USER_CLASS => ['name' => 'User class'],
        self::FILTER_USER_REGISTER_TIME_RANGE => ['name' => 'User register time range'],
        self::FILTER_USER_DONATE => ['name' => 'User donated'],
    ];

    protected static function booted()
    {
        static::saving(function (Model $model) {
            $model->duration = (int)$model->duration;
        });
    }

    public static function listIndex($onlyKeyValue = false): array
    {
        $result = self::$indexes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans("exam.index_text_$key");
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status == self::STATUS_ENABLED ? nexus_trans('label.enabled') : nexus_trans('label.disabled');
    }

    public function getIsDiscoveredTextAttribute(): string
    {
        return self::$discovers[$this->is_discovered]['text'] ?? '';
    }

    public function getDurationTextAttribute(): string
    {
        if ($this->duration > 0) {
            return $this->duration . ' Days';
        }
        return '';
    }

    public function getIndexFormattedAttribute(): string
    {
        $indexes = $this->indexes;
        $arr = [];
        foreach ($indexes as $index) {
            if (isset($index['checked']) && $index['checked']) {
                $arr[] = sprintf(
                    '%s: %s %s',
                    nexus_trans("exam.index_text_{$index['index']}"),
                    $index['require_value'],
                    self::$indexes[$index['index']]['unit'] ?? ''
                );
            }
        }
        return implode("<br/>", $arr);
    }

    public function getFilterFormattedAttribute(): string
    {
        $currentFilters = $this->filters;
        $arr = [];
        $filter = self::FILTER_USER_CLASS;
        if (!empty($currentFilters->{$filter})) {
            $classes = collect(User::$classes)->only($currentFilters->{$filter});
            $arr[] = sprintf('%s: %s', nexus_trans("exam.filters.$filter"), $classes->pluck('text')->implode(', '));
        }

        $filter = self::FILTER_USER_REGISTER_TIME_RANGE;
        if (!empty($currentFilters->{$filter})) {
            $range = $currentFilters->{$filter};
            $arr[] = sprintf(
                "%s: <br/>%s ~ %s",
                nexus_trans("exam.filters.$filter"),
                $range[0] ? Carbon::parse($range[0])->toDateTimeString() : '--',
                $range[1] ? Carbon::parse($range[1])->toDateTimeString() : '--'
            );
        }

        $filter = self::FILTER_USER_DONATE;
        if (!empty($currentFilters->{$filter})) {
            $donateStatus = $classes = collect(User::$donateStatus)->only($currentFilters->{$filter});
            $arr[] = sprintf('%s: %s', nexus_trans("exam.filters.$filter"), $donateStatus->pluck('text')->implode(', '));
        }

        return implode("<br/>", $arr);
    }

}
