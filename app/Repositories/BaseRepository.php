<?php

namespace App\Repositories;

use Illuminate\Support\Str;

class BaseRepository
{
    protected function getSortFieldAndType(array $params)
    {
        $field = $params['sort_field'] ?? 'id';
        $type = 'desc';
        if (!empty($params['sort_type']) && Str::startsWith($params['sort_type'], 'asc')) {
            $type = 'asc';
        }
        return [$field, $type];
    }
}
