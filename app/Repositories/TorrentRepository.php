<?php

namespace App\Repositories;

use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Codec;
use App\Models\Media;
use App\Models\Processing;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Team;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TorrentRepository extends BaseRepository
{
    /**
     *  fetch torrent list
     *
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getList(array $params)
    {
        $query = Torrent::query();
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }
        if (!empty($params['source'])) {
            $query->where('source', $params['source']);
        }
        if (!empty($params['medium'])) {
            $query->where('medium', $params['medium']);
        }
        if (!empty($params['codec'])) {
            $query->where('codec', $params['codec']);
        }
        if (!empty($params['audio_codec'])) {
            $query->where('audiocodec', $params['audio_codec']);
        }
        if (!empty($params['standard'])) {
            $query->where('standard', $params['standard']);
        }
        if (!empty($params['processing'])) {
            $query->where('processing', $params['processing']);
        }
        if (!empty($params['team'])) {
            $query->where('team', $params['team']);
        }
        if (!empty($params['owner'])) {
            $query->where('owner', $params['owner']);
        }
        if (!empty($params['visible'])) {
            $query->where('visible', $params['visible']);
        }

        if (!empty($params['query'])) {
            $query->where(function (Builder $query) use ($params) {
                $query->where('name', 'like', "%{$params['query']}%")
                    ->orWhere('small_descr', 'like', "%{$params['query']}%");
            });
        }

        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        $with = ['user'];
        $torrents = $query->with($with)->paginate();
        return $torrents;
    }

    public function getSearchBox()
    {
        $category = Category::query()->orderBy('sort_index')->orderBy('id')->get();
        $source = Source::query()->orderBy('sort_index')->orderBy('id')->get();
        $media = Media::query()->orderBy('sort_index')->orderBy('id')->get();
        $codec = Codec::query()->orderBy('sort_index')->orderBy('id')->get();
        $standard = Standard::query()->orderBy('sort_index')->orderBy('id')->get();
        $processing = Processing::query()->orderBy('sort_index')->orderBy('id')->get();
        $team = Team::query()->orderBy('sort_index')->orderBy('id')->get();
        $audioCodec = AudioCodec::query()->orderBy('sort_index')->orderBy('id')->get();

        $modalRows = [];
        $modalRows[] = $categoryFormatted = $this->formatRow('类型', $category, 'category');
        $modalRows[] = $this->formatRow('媒介', $source, 'source');
        $modalRows[] = $this->formatRow('媒介', $media, 'medium');
        $modalRows[] = $this->formatRow('编码', $codec, 'codec');
        $modalRows[] = $this->formatRow('音频编码', $audioCodec, 'audio_codec');
        $modalRows[] = $this->formatRow('分辨率', $standard, 'standard');
        $modalRows[] = $this->formatRow('处理', $processing, 'processing');
        $modalRows[] = $this->formatRow('制作组', $team, 'team');

        $results = [];
        $categories = $categoryFormatted['rows'];
        $categories[0]['active'] = 1;
        $results['categories'] = $categories;
        $results['modal_rows'] = $modalRows;


        return $results;
    }

    private function formatRow($header, $items, $name)
    {
        $result['header'] = $header;
        $result['rows'][] = [
            'label' => '全部',
            'value' => 0,
            'name' => $name,
            'active' => 1,
        ];
        foreach ($items as $value) {
            $item = [
                'label' => $value->name,
                'value' => $value->id,
                'name' => $name,
                'active' => 0,
            ];
            $result['rows'][] = $item;
        }
        return $result;
    }

}
