<?php
namespace App\Repositories;

use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

class TagRepository extends BaseRepository
{
    private static $orderByFieldIdString;

    private static $allTags;

    public function getList(array $params)
    {
        $query = $this->createBasicQuery();
        return $query->paginate();
    }

    public function store(array $params)
    {
        $model = Tag::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $model = Tag::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = Tag::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = Tag::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }

    public static function createBasicQuery()
    {
        return Tag::query()->orderBy('priority', 'desc')->orderBy('id', 'desc');
    }

    public function renderCheckbox(int $searchBoxId, array $checked = [], $ignorePermission = false): string
    {
        $html = '';
        $results = $this->listAll($searchBoxId);
        if (!$ignorePermission && !user_can('torrent-set-special-tag')) {
            $specialTags = Tag::listSpecial();
            $results = $results->filter(fn ($item) => !in_array($item->id, $specialTags));
        }
        foreach ($results as $value) {
            $html .= sprintf(
                '<label><input type="checkbox" name="tags[%s][]" value="%s"%s />%s</label>',
                $searchBoxId, $value->id, in_array($value->id, $checked) ? ' checked' : '', $value->name
            );
        }
        return $html;
    }

    public function renderSpan(int $searchBoxId, array $renderIdArr = [], $withFilterLink = false): string
    {
        $html = '';
        foreach ($this->listAll($searchBoxId) as $value) {
            if (in_array($value->id, $renderIdArr) || (isset($renderIdArr[0]) && $renderIdArr[0] == '*')) {
                $tagId = $value->id;
                if ($value) {
                    $item = sprintf(
                        "<span style=\"background-color:%s;color:%s;border-radius:%s;font-size:%s;margin:%s;padding:%s\" title=\"%s\">%s</span>",
                        $value->color, $value->font_color, $value->border_radius, $value->font_size, $value->margin, $value->padding, $value->description, $value->name
                    );
                    if ($withFilterLink) {
                        $html .= sprintf('<a href="?tag_id=%s">%s</a>', $tagId, $item);
                    } else {
                        $html .= $item;
                    }
                }
            }
        }
        return $html;
    }

    public function migrateTorrentTag()
    {
        $page = 1;
        $size = 1000;
        $baseQuery = Torrent::query()->where('tags', '>', 0);
        do_log("torrent to migrate hr counts: " . (clone $baseQuery)->count());
        $dateTimeStringNow = date('Y-m-d H:i:s');
        $tags = [];
        $priority = count(Tag::DEFAULTS);
        foreach (Tag::DEFAULTS as $value) {
            $attributes = [
                'name' => $value['name'],
            ];
            $values = [
                'priority' => $priority,
                'color' => $value['color'],
                'created_at' => $dateTimeStringNow,
                'updated_at' => $dateTimeStringNow,
            ];
            $tags[] = Tag::query()->firstOrCreate($attributes, $values);
            $priority--;
        }
        do_log("insert default tags done!");

        $sql = "insert into torrent_tags (torrent_id, tag_id, created_at, updated_at) values ";
        $values = [];
        while (true) {
            $logPrefix = "page: $page, size: $size";
            $results = (clone $baseQuery)->forPage($page, $size)->get();
            if ($results->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            foreach ($results as $torrent) {
                foreach ($tags as $key => $tag) {
                    $currentValue = pow(2, $key);
                    if ($currentValue & $torrent->tags) {
                        //this torrent has this tag
                        $values[] = sprintf("(%d, %d, '%s', '%s')", $torrent->id, $tag->id, $dateTimeStringNow, $dateTimeStringNow);
                    }
                }
            }
            $page++;
        }
        $sql .= sprintf("%s on duplicate key update updated_at = values(updated_at)", implode(', ', $values));
        do_log("migrate sql: $sql");
        NexusDB::statement($sql);
        do_log("[MIGRATE_TORRENT_TAG] done!");
        return count($values);
    }

    public static function getOrderByFieldIdString(): string
    {
        if (is_null(self::$orderByFieldIdString)) {
            $results = self::createBasicQuery()->get(['id']);
            self::$orderByFieldIdString = $results->isEmpty() ? '0' : $results->implode('id', ',');
        }
        return self::$orderByFieldIdString;
    }

    public function listAll(int $searchBoxId = 0): \Illuminate\Database\Eloquent\Collection|array
    {
        if (empty(self::$allTags)) {
            self::$allTags = self::createBasicQuery()->get();
        }
        if ($searchBoxId > 0) {
            return self::$allTags->filter(fn ($d) => in_array($d->mode, [0, $searchBoxId]));
        }
        return self::$allTags;
    }

    public function buildSelect(int $searchBoxId, $name, $value): string
    {
        $list = $this->listAll($searchBoxId);
        $select = sprintf('<select name="%s"><option value="">%s</option>', $name, nexus_trans('nexus.select_one_please'));
        foreach ($list as $item) {
            $selected = '';
            if ($item->id == $value) {
                $selected = ' selected';
            }
            $select .= sprintf('<option value="%s"%s>%s</option>', $item->id, $selected, $item->name);
        }
        $select .= '</select>';
        return $select;
    }


}
