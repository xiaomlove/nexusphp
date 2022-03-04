<?php

namespace App\Repositories;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

class BaseRepository
{
    private static $enctyper;

    protected function getSortFieldAndType(array $params): array
    {
        $field = !empty($params['sort_field']) ? $params['sort_field'] : 'id';
        $type = 'desc';
        if (!empty($params['sort_type']) && Str::startsWith($params['sort_type'], 'asc')) {
            $type = 'asc';
        }
        return [$field, $type];
    }

}
