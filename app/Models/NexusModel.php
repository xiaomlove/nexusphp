<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexus\Database\NexusDB;

class NexusModel extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $perPage = 50;

    protected $connection = NexusDB::ELOQUENT_CONNECTION_NAME;

    /**
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * Check is valid date string
     *
     * @see https://stackoverflow.com/questions/19271381/correctly-determine-if-date-string-is-a-valid-date-in-that-format
     * @param $name
     * @param string $format
     * @return bool
     */
    public function isValidDate($name, $format = 'Y-m-d H:i:s'): bool
    {
        $date = $this->getRawOriginal($name);
        $d = \DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    public function getDeadlineText($field = 'deadline')
    {
        $raw = $this->getRawOriginal($field);
        if (in_array($raw, [null, '0000-00-00 00:00:00', ''], true)) {
            return nexus_trans("label.permanent");
        }
        return sprintf('%s: %s', nexus_trans('label.deadline'), $raw);
    }

    public static function listStaticProps($dataSource, $textTransPrefix, $onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = $dataSource;
        $keyValue = [];
        foreach ($result as $key => &$info) {
            if (str_contains($textTransPrefix, '%s')) {
                $transKey = sprintf($textTransPrefix, $key);
            } else {
                $transKey = "$textTransPrefix.$key";
            }
            $text = $textTransPrefix ? nexus_trans($transKey) : $info['text'];
            $info['text'] = $text;
            $keyValue[$key] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }
        return $result;
    }

}
