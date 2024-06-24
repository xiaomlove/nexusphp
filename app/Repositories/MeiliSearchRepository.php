<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Nexus\Database\NexusDB;

class MeiliSearchRepository extends BaseRepository
{
    private static $client;

    const INDEX_NAME = 'torrents';

    const SEARCH_AREA_TITLE = '0';
    const SEARCH_AREA_DESC = '1';
    const SEARCH_AREA_OWNER = '3';
    const SEARCH_AREA_IMDB = '4';

    private static array $searchAreas = [
        self::SEARCH_AREA_TITLE => ['text' => 'title'],
        self::SEARCH_AREA_DESC => ['text' => 'desc'],
        self::SEARCH_AREA_OWNER => ['text' => 'owner'],
        self::SEARCH_AREA_IMDB => ['text' => 'imdb'],
    ];

    private static array $queryFieldToTorrentFieldMaps = [
        'cat' => 'category',
        'source' => 'source',
        'medium' => 'medium',
        'codec' => 'codec',
        'audiocodec' => 'audiocodec',
        'standard' => 'standard',
        'processing' => 'processing',
        'team' => 'team',
    ];

    private static array $sortFieldMaps = [
        '1' => 'name',
//        '2' => 'numfiles',
        '3' => 'comments',
        '4' => 'added',
        '5' => 'size',
        '6' => 'times_completed',
        '7' => 'seeders',
        '8' => 'leechers',
        '9' => 'owner',
    ];


    private static array $filterableAttributes = [
        "id", "category", "source", "medium", "codec", "standard", "processing", "team", "audiocodec", "owner",
        "sp_state", "visible", "banned", "approval_status", "size", "leechers", "seeders", "times_completed", "added",
    ];

    private static array $sortableAttributes = [
        "id", "name", "comments", "added", "size", "leechers", "seeders", "times_completed", "owner",
        "pos_state", "anonymous"
    ];

    private static array $intFields = [
        "id", "category", "source", "medium", "codec", "standard", "processing", "team", "audiocodec", "owner",
        "sp_state", "approval_status", "size", "leechers", "seeders", "times_completed", "url", "comments",
    ];

    private static array $timestampFields = ['added'];

    private static array $yesOrNoFields = ['visible', 'anonymous', 'banned'];



    public function getClient(): Client
    {
        if (is_null(self::$client)) {
            $config = nexus_config('nexus.meilisearch');
            $url = sprintf('%s://%s:%s', $config['scheme'], $config['host'], $config['port']);
            do_log("get client with url: $url, master key: " . $config['master_key']);
            self::$client = new Client($url, $config['master_key']);
        }
        return self::$client;
    }

    public function isEnabled(): bool
    {
        return Setting::get('meilisearch.enabled') == 'yes';
    }

    public function import()
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        $client = $this->getClient();
        $stats = $client->stats();
        if (isset($stats['indexes'][self::INDEX_NAME])) {
            $doSwap = true;
            $indexName = self::INDEX_NAME . "_" . date('Ymd_His');
        } else {
            $doSwap = false;
            $indexName = self::INDEX_NAME;
        }
        do_log("indexName: $indexName will be created, doSwap: $doSwap");
        $index = $this->createIndex($indexName);
        try {
            $total = $this->doImportFromDatabase(null, $index);
            if ($doSwap) {
                $swapResult = $client->swapIndexes([[self::INDEX_NAME, $indexName]]);
                $times = 0;
                while (true) {
                    if ($times == 3600) {
                        $msg = "total: $total, swap too long, times: $times, return false";
                        do_log($msg);
                        throw new NexusException($msg);
                    }
                    sleep(1);
                    $task = $client->getTask($swapResult['taskUid']);
                    if ($task['status'] == 'succeeded') {
                        do_log("total: $total, swap success at times: $times");
                        $client->deleteIndex($indexName);
                        return $total;
                    }
                    do_log("waiting swap success, times: $times");
                    $times++;
                }
            }
            return $total;
        } catch (\Exception $exception) {
            $client->deleteIndex($indexName);
            throw $exception;
        }
    }

    private function createIndex($indexName)
    {
        $client = $this->getClient();
        $params = [
            'primaryKey' => 'id',
        ];
        $client->createIndex($indexName, $params);
        $index = $client->index($indexName);
        $settings = [
            "distinctAttribute" => "id",
            "displayedAttributes" => $this->getRequiredFields(),
            "searchableAttributes" => $this->getSearchableAttributes(),
            "filterableAttributes" => self::$filterableAttributes,
            "sortableAttributes" => self::$sortableAttributes,
            "rankingRules" => [
                "words",
                "sort",
                "typo",
                "proximity",
                "attribute",
                "exactness"
            ],
        ];
        $index->updateSettings($settings);

        return $index;

    }

    public function getRequiredFields(): array
    {
        return array_values(array_unique(array_merge(
            self::$filterableAttributes, self::$sortableAttributes, $this->getSearchableAttributes()
        )));
    }

    public function doImportFromDatabase($id = null, $index = null)
    {
        if (!$this->isEnabled() && $index === null) {
            do_log("Not enabled!");
            return false;
        }
        $page = 1;
        $size = 1000;
        if (!$index instanceof Indexes) {
            $index = $this->getIndex();
        }
        $total = 0;
        while (true) {
            $query = NexusDB::table("torrents")->forPage($page, $size);
            if ($id) {
                $query->whereIn("id", Arr::wrap($id));
            }
            $torrents = $query->get($this->getRequiredFields());
            $count = $torrents->count();
            $total += $count;
            if ($count == 0) {
                do_log("page: $page no data...");
                break;
            }
            do_log(sprintf('importing page: %s with id: %s, %s records...', $page, $id, $count));
            $data = [];
            foreach ($torrents as $torrent) {
                $row = [];
                foreach ($torrent as $field => $value) {
                    $row[$field] = $this->formatValueForMeili($field, $value);
                }
                $data[] = $row;
            }
            $index->updateDocuments($data);
            do_log(sprintf('import page: %s with id: %s, %s records success.', $page, $id, $count));
            $page++;
        }
        return $total;
    }

    public function search(array $params, $user)
    {
        $results['total'] = 0;
        $results['list'] = [];
        if (!$this->isEnabled()) {
            do_log("Not enabled!");
            return $results;
        }
        $filters = [];
        //think about search area
        $searchArea = $this->getSearchArea($params);
        if ($searchArea == self::SEARCH_AREA_OWNER) {
            $searchOwner = User::query()->where('username', trim($params['search']))->first(['id']);
            if (!$searchOwner) {
                //No user match, no results
                return $results;
            } else {
                $filters[] = "owner = " . $searchOwner->id;
            }
        }
        if (!($user instanceof User) || !$user->torrentsperpage || !$user->notifs) {
            $user = User::query()->findOrFail(intval($user));
        }
        $filters = array_merge($filters, $this->getFilters($params, $user));
        $query = $this->getQuery($params);
        $page = isset($params['page']) && is_numeric($params['page']) ? $params['page'] : 0;
        $perPage = $this->getPerPage($user);
        $index = $this->getIndex();
        $searchParams = [
            "q" => $query,
            "hitsPerPage" => $perPage,
            //NP starts from 0, but meilisearch starts from 1
            "page" => $page + 1,
            "filter" => $filters,
            "attributesToRetrieve" => $this->getAttributesToRetrieve(),
        ];
        if (isset($params['sort'], $params['type'])) {
            $searchParams['sort'] = $this->getSort($params);
        }
        $searchResult = $index->search($query, $searchParams);
        $total = $searchResult->getTotalHits();
        do_log("search params: " . nexus_json_encode($searchParams) . ", total: $total");
        $results['total'] = $total;
        if ($total > 0) {
            $torrentIdArr = array_column($searchResult->getHits(), 'id');
            $fields = Torrent::getFieldsForList();
            $idStr = implode(',', $torrentIdArr);
            $torrents = Torrent::query()
                ->select($fields)
                ->with('basic_category')
                ->whereIn('id', $torrentIdArr)
                ->orderByRaw("field(id,$idStr)")
                ->get()
            ;
            $list = [];
            foreach ($torrents as $torrent) {
                $searchBoxId = $torrent->basic_category->mode;
                $arr = $torrent->toArray();
                $arr['search_box_id'] = $searchBoxId;
                $list[] = $arr;
            }
            $results['list'] = $list;
        }
        return $results;
    }

    /**
     * @param array $params
     * @param User $user
     * @return array
     */
    private function getFilters(array $params, User $user): array
    {
        $filters = [];
        $taxonomies = [];
        $categoryIdArr = [];
        //[cat401][cat404][sou1][med1][cod1][sta2][sta3][pro2][tea2][aud2][incldead=0][spstate=3][inclbookmarked=2]
        $userSetting = $user->notifs;
        //cat401=1&source2=1&medium10=1&codec2=1&audiocodec2=1&standard3=1&processing2=1&team2=1&incldead=2&spstate=1&inclbookmarked=0&approval_status=&size_begin=&size_end=&seeders_begin=&seeders_end=&leechers_begin=&leechers_end=&times_completed_begin=&times_completed_end=&added_begin=&added_end=&search=a+b&search_area=0&search_mode=2
        $queryString = http_build_query($params);
        //section
        if (!empty($params['mode'])) {
            $categoryIdArr = Category::query()->whereIn('mode', Arr::wrap($params['mode']))->pluck('id')->toArray();
        }
        foreach (self::$queryFieldToTorrentFieldMaps as $queryField => $torrentField) {
            if (isset($params[$queryField]) && $params[$queryField] !== '') {
                $taxonomies[$torrentField][] = $params[$queryField];
                do_log("$torrentField from params through $queryField: {$params[$queryField]}");
            } elseif (preg_match_all("/{$queryField}(\d+)=/", $queryString, $matches)) {
                if (count($matches) == 2 && !empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $taxonomies[$torrentField][] = $match;
                        do_log("$torrentField from params through $queryField: $match");
                    }
                }
            } else {
                //get user setting
                $pattern = sprintf("/\[%s([\d]+)\]/", substr($queryField, 0, 3));
                if (preg_match($pattern, $userSetting, $matches)) {
                    if (count($matches) == 2 && !empty($matches[1])) {
                        foreach ($matches[1] as $match) {
                            $taxonomies[$torrentField][] = $match;
                            do_log("$torrentField from user setting through $queryField: $match");
                        }
                    }
                }
            }
        }
        if (empty($taxonomies['category']) && !empty($categoryIdArr)) {
            //Restricted to the category of the specified section
            $taxonomies['category'] = $categoryIdArr;
        }
        foreach ($taxonomies as $key => $values) {
            if (!empty($values)) {
                $filters[] = sprintf("%s IN [%s]", $key, implode(', ', $values));
            }
        }

        $includeDead = 1;
        if (isset($params['incldead'])) {
            $includeDead = (int)$params['incldead'];
        } elseif (preg_match("/\[incldead=(\d+)\]/", $userSetting, $matches)) {
            $includeDead = $matches[1];
        }
        if ($includeDead == 1) {
            //active torrent
            $filters[] = "visible = 1";
            do_log("visible = yes through incldead: $includeDead");
        } elseif ($includeDead == 2) {
            //dead torrent
            $filters[] = "visible = 0";
            do_log("visible = no through incldead: $includeDead");
        }

        $includeBookmarked = 0;
        if (isset($params['inclbookmarked'])) {
            $includeBookmarked = (int)$params['inclbookmarked'];
        } elseif (preg_match("/\[inclbookmarked=(\d+)\]/", $userSetting, $matches)) {
            $includeBookmarked = $matches[1];
        }
        if ($includeBookmarked > 0) {
            $userBookmarkedTorrentIdStr = Bookmark::query()->where('userid', $user->id)->pluck('torrentid')->implode(',');
            if ($includeBookmarked == 1) {
                //only bookmark
                $filters[] = "id IN [$userBookmarkedTorrentIdStr]";
                do_log("bookmark through inclbookmarked: $includeBookmarked");
            } elseif ($includeBookmarked == 2) {
                //only not bookmark
                $filters[] = "id NOT IN [$userBookmarkedTorrentIdStr]";
                do_log("bookmark through inclbookmarked: $includeBookmarked");
            }
        }

        $spState = 0;
        if (isset($params['spstate'])) {
            $spState = (int)$params['spstate'];
            do_log("spstate from params");
        } elseif (preg_match("/\[spstate=(\d+)\]/", $userSetting, $matches)) {
            $spState = $matches[1];
            do_log("spstate from user setting");
        }
        if ($spState > 0) {
            $filters[] = "sp_state = $spState";
            do_log("sp_state = $spState through spstate: $spState");
        }

        if (isset($params['approval_status']) && is_numeric($params['approval_status'])) {
            $filters[] = "approval_status = " . $params['approval_status'];
            do_log("approval_status = {$params['approval_status']} through approval_status: {$params['approval_status']}");
        }

        //size
        if (!empty($params['size_begin'])) {
            $atomicValue = intval($params['size_begin']) * 1024 * 1024 * 1024;
            $filters[] = "size >= $atomicValue";
            do_log("size >= $atomicValue through size_begin: $atomicValue");
        }
        if (!empty($params['size_end'])) {
            $atomicValue = intval($params['size_end']) * 1024 * 1024 * 1024;
            $filters[] = "size <= $atomicValue";
            do_log("size <= $atomicValue through size_end: $atomicValue");
        }


        //seeders
        if (!empty($params['seeders_begin'])) {
            $atomicValue = intval($params['seeders_begin']);
            $filters[] = "seeders >= $atomicValue";
            do_log("seeders >= $atomicValue through seeders_begin: $atomicValue");
        }
        if (!empty($params['seeders_end'])) {
            $atomicValue = intval($params['seeders_end']);
            $filters[] = "seeders <= $atomicValue";
            do_log("seeders <= $atomicValue through seeders_end: $atomicValue");
        }

        //leechers
        if (!empty($params['leechers_begin'])) {
            $atomicValue = intval($params['leechers_begin']);
            $filters[] = "leechers >= $atomicValue";
            do_log("leechers >= $atomicValue through leechers_begin: $atomicValue");
        }
        if (!empty($params['leechers_end'])) {
            $atomicValue = intval($params['leechers_end']);
            $filters[] = "leechers <= $atomicValue";
            do_log("leechers <= $atomicValue through leechers_end: $atomicValue");
        }


        //times_completed
        if (!empty($params['times_completed_begin'])) {
            $atomicValue = intval($params['times_completed_begin']);
            $filters[] = "times_completed >= $atomicValue";
            do_log("times_completed >= $atomicValue through times_completed_begin: $atomicValue");
        }
        if (!empty($params['times_completed_end'])) {
            $atomicValue = intval($params['times_completed_end']);
            $filters[] = "times_completed <= $atomicValue";
            do_log("times_completed <= $atomicValue through times_completed_end: $atomicValue");
        }

        //added
        if (!empty($params['added_begin'])) {
            $atomicValue = $params['added_begin'];
            $filters[] = "added >= " . strtotime($atomicValue);
            do_log("added >= $atomicValue through added_begin: $atomicValue");
        }
        if (!empty($params['added_end'])) {
            $atomicValue = Carbon::parse($params['added_end'])->endOfDay()->toDateTimeString();
            $filters[] = "added <= " . strtotime($atomicValue);
            do_log("added <= $atomicValue through added_end: $atomicValue");
        }

        //permission see banned
        if (isset($params['banned']) && in_array($params['banned'], ['yes', 'no'])) {
            if ($params['banned'] == 'yes') {
                $filters[] = "banned = 1";
            } else {
                $filters[] = "banned = 0";
            }
        }

        do_log("[GET_FILTERS]: " . json_encode($filters));
        return $filters;
    }

    private function getQuery(array $params): string
    {
        $q = trim($params['search']);
        $searchMode = SearchBox::getDefaultSearchMode();
        if (isset($params['search_mode'], SearchBox::$searchModes[$params['search_mode']])) {
            $searchMode = $params['search_mode'];
        }
        do_log("search mode: " . SearchBox::$searchModes[$searchMode]['text']);
        if ($searchMode == SearchBox::SEARCH_MODE_AND) {
            return $q;
        }
        return sprintf('"%s"', $q);
    }

    private function getSearchArea(array $params)
    {
        if (isset($params['search_area'], self::$searchAreas[$params['search_area']])) {
            return $params['search_area'];
        }
        return self::SEARCH_AREA_TITLE;
    }

    public function getIndex(): \Meilisearch\Endpoints\Indexes
    {
        return $this->getClient()->index(self::INDEX_NAME);
    }

    private function getSort(array $params): array
    {
        if (!isset($params['sort']) || !isset($params['type'])) {
            //Use default
            return [];
        }
        if (isset($params['sort'], self::$sortFieldMaps[$params['sort']]) && isset($params['type']) && in_array($params['type'], ['asc', 'desc'])) {
            $sortField = self::$sortFieldMaps[$params['sort']];
        } else {
            $sortField = "id";
        }
        if (isset($params['type']) && in_array($params['type'], ['desc', 'asc'])) {
            $sortType = $params['type'];
        } else {
            $sortType = "desc";
        }
        //when searching, ignore promotion
//        if ($sortField == "id") {
//            return ["pos_state:desc", "$sortField:$sortType"];
//        } else {
//            return ["pos_state:desc", "$sortField:$sortType", "id:desc"];
//        }

        return ["$sortField:$sortType"];
    }

    private function getPerPage(User $user)
    {
        if ($user->torrentsperpage) {
            $size = $user->torrentsperpage;
        } elseif (($sizeFromConfig = Setting::get('main.torrentsperpage')) > 0) {
            $size = $sizeFromConfig;
        } else {
            $size = 100;
        }
        return intval(min($size, 200));
    }

    private function formatValueForMeili($field, $value)
    {
        if (in_array($field, self::$intFields)) {
            return intval($value);
        }
        if (in_array($field, self::$timestampFields)) {
            return strtotime($value);
        }
        if (in_array($field, self::$yesOrNoFields)) {
            return $value == 'yes' ? 1 : 0;
        }
        return strval($value);
    }

    public function deleteDocuments($id)
    {
        if ($this->isEnabled()) {
            return $this->getIndex()->deleteDocuments(Arr::wrap($id));
        }
    }

    private function getAttributesToRetrieve(): array
    {
        if (nexus_env("APP_ENV") == 'production') {
            return ['id'];
        }
        return ['*'];
    }

    private function getSearchableAttributes(): array
    {
        $attributes = ["name", "small_descr", "url"];
        if (Setting::get("meilisearch.search_description") == 'yes') {
            $attributes[] = "descr";
        }
        return $attributes;
    }




}
