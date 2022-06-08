<?php
namespace App\Repositories;

use App\Models\Bookmark;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Arr;
use Nexus\Database\NexusDB;

class SearchRepository extends BaseRepository
{
    private ?Client $es = null;

    private bool $enabled = false;

    const INDEX_NAME = 'nexus_torrents';

    const DOC_TYPE_TORRENT = 'torrent';
    const DOC_TYPE_TAG = 'tag';
    const DOC_TYPE_BOOKMARK = 'bookmark';
    const DOC_TYPE_USER = 'user';

    const SEARCH_MODE_AND = '0';
    const SEARCH_MODE_OR = '1';
    const SEARCH_MODE_EXACT = '2';

    const SEARCH_MODES = [
        self::SEARCH_MODE_AND => ['text' => 'and'],
        self::SEARCH_MODE_OR => ['text' => 'or'],
        self::SEARCH_MODE_EXACT => ['text' => 'exact'],
    ];

    const SEARCH_AREA_TITLE = '0';
    const SEARCH_AREA_DESC = '1';
    const SEARCH_AREA_OWNER = '3';
    const SEARCH_AREA_IMDB = '4';

    const SEARCH_AREAS = [
        self::SEARCH_AREA_TITLE => ['text' => 'title'],
        self::SEARCH_AREA_DESC => ['text' => 'desc'],
        self::SEARCH_AREA_OWNER => ['text' => 'owner'],
        self::SEARCH_AREA_IMDB => ['text' => 'imdb'],
    ];



    private array $indexSetting = [
        'index' => self::INDEX_NAME,
        'body' => [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
            'mappings' => [
                'properties' => [
                    '_doc_type' => ['type' => 'keyword'],

                    //torrent
                    'torrent_id' => ['type' => 'long', ],

                    //user
                    'username' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],

                    //bookmark + user + tag
                    'user_id' => ['type' => 'long', ],

                    //tag
                    'tag_id' => ['type' => 'long', ],

                    //use for category.mode
                    'mode' => ['type' => 'long', ],

                    //relations
                    'torrent_relations' => [
                        'type' => 'join',
                        'eager_global_ordinals' => true,
                        'relations' => [
                            'user' => ['torrent'],
                            'torrent' => ['bookmark', 'tag'],
                        ],
                    ],
                ],
            ]
        ],
    ];

    //cat401=1&source1=1&medium1=1&codec1=1&audiocodec1=1&standard1=1&processing1=1&team1=1&incldead=1&spstate=2&inclbookmarked=1&search=tr&search_area=1&search_mode=1
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
        '2' => 'numfiles',
        '3' => 'comments',
        '4' => 'added',
        '5' => 'size',
        '6' => 'times_completed',
        '7' => 'seeders',
        '8' => 'leechers',
        '9' => 'owner',
    ];

    public function __construct()
    {
        $elasticsearchEnabled = nexus_env('ELASTICSEARCH_ENABLED');
        if ($elasticsearchEnabled) {
            $this->enabled = true;
        } else {
            $this->enabled = false;
        }
    }

    private function getEs(): Client
    {
        if (is_null($this->es)) {
            $config = nexus_config('nexus.elasticsearch');
            $builder = ClientBuilder::create()->setHosts($config['hosts']);
            if (!empty($config['ssl_verification'])) {
                $builder->setSSLVerification($config['ssl_verification']);
            }
            $this->es = $builder->build();
        }
        return $this->es;
    }

    private function getTorrentRawMappingFields(): array
    {
        return [
            'name' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
            'descr' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
            'small_descr' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
            'category' => ['type' => 'long', ],
            'source' => ['type' => 'long', ],
            'medium' => ['type' => 'long', ],
            'codec' => ['type' => 'long', ],
            'standard' => ['type' => 'long', ],
            'processing' => ['type' => 'long', ],
            'team' => ['type' => 'long', ],
            'audiocodec' => ['type' => 'long', ],
            'size' => ['type' => 'long', ],
            'added' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
            'numfiles' => ['type' => 'long', ],
            'comments' => ['type' => 'long', ],
            'views' => ['type' => 'long', ],
            'hits' => ['type' => 'long', ],
            'times_completed' => ['type' => 'long', ],
            'leechers' => ['type' => 'long', ],
            'seeders' => ['type' => 'long', ],
            'last_action' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
            'visible' => ['type' => 'keyword', ],
            'banned' => ['type' => 'keyword', ],
            'owner' => ['type' => 'long', ],
            'sp_state' => ['type' => 'long', ],
            'url' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
            'pos_state' => ['type' => 'keyword', ],
            'picktype' => ['type' => 'keyword', ],
            'hr' => ['type' => 'long', ],
        ];
    }

    public function getEsInfo(): callable|array
    {
        return $this->getEs()->info();
    }

    public function createIndex()
    {
        $params = $this->indexSetting;
        $properties = $params['body']['mappings']['properties'];
        $properties = array_merge($properties, $this->getTorrentRawMappingFields());
        $params['body']['mappings']['properties'] = $properties;
        return $this->getEs()->indices()->create($params);
    }

    public function deleteIndex()
    {
        $params = ['index' => self::INDEX_NAME];
        return $this->getEs()->indices()->delete($params);
    }

    public function import($torrentId = null)
    {
        $page = 1;
        $size = 1000;
        $fields = $this->getTorrentBaseFields();
        array_unshift($fields, 'id');
        $query = Torrent::query()
            ->with(['user', 'torrent_tags', 'bookmarks', 'basic_category'])
            ->select($fields);
        if (!is_null($torrentId)) {
            $idArr = preg_split('/[,\s]+/', $torrentId);
            $query->whereIn('id', $idArr);
        }
        while (true) {
            $log = "page: $page, size: $size";
            $torrentResults = (clone $query)->forPage($page, $size)->get();
            if ($torrentResults->isEmpty()) {
                do_log("$log, no more data...", 'info', true);
                break;
            }
            do_log("$log, get counts: " . $torrentResults->count(), 'info', true);

            $torrentBodyBulk = $userBodyBulk = $tagBodyBulk = $bookmarkBodyBulk = ['body' => []];
            foreach ($torrentResults as $torrent) {
                $body  = $this->buildUserBody($torrent->user, true);
                $userBodyBulk['body'][] = ['index' => $body['index']];
                $userBodyBulk['body'][] = $body['body'];

                $body = $this->buildTorrentBody($torrent, true);
                $torrentBodyBulk['body'][] = ['index' => $body['index']];
                $torrentBodyBulk['body'][] = $body['body'];

                foreach ($torrent->torrent_tags as $torrentTag) {
                    $body = $this->buildTorrentTagBody($torrent, $torrentTag, true);
                    $tagBodyBulk['body'][] = ['index' => $body['index']];
                    $tagBodyBulk['body'][] = $body['body'];
                }

                foreach ($torrent->bookmarks as $bookmark) {
                    $body = $this->buildBookmarkBody($torrent, $bookmark, true);
                    $bookmarkBodyBulk['body'][] = ['index' => $body['index']];
                    $bookmarkBodyBulk['body'][] = $body['body'];
                }

            }

            //index user
            $result = $this->getEs()->bulk($userBodyBulk);
            $this->logEsResponse("$log, bulk index user done!", $result);

            //index torrent
            $result = $this->getEs()->bulk($torrentBodyBulk);
            $this->logEsResponse("$log, bulk index torrent done!", $result);

            //index tag
            $result = $this->getEs()->bulk($tagBodyBulk);
            $this->logEsResponse("$log, bulk index tag done!", $result);

            //index bookmark
            $result = $this->getEs()->bulk($bookmarkBodyBulk);
            $this->logEsResponse("$log, bulk index bookmark done!", $result);

            $page++;

        }
    }

    private function buildUserBody(User $user, bool $underlinePrefix = false)
    {
        $docType = self::DOC_TYPE_USER;
        $indexName = 'index';
        $idName = 'id';
        if ($underlinePrefix) {
            $indexName = "_$indexName";
            $idName = "_$idName";
        }
        $index = [
            $indexName => self::INDEX_NAME,
            $idName => $this->getUserId($user->id),
            'routing' => $user->id,
        ];
        $body = [
            '_doc_type' => $docType,
            'user_id' => $user->id,
            'username' => $user->username,
            'torrent_relations' => [
                'name' => $docType,
            ],
        ];
        return compact('index', 'body');
    }


    private function buildTorrentBody($torrent, bool $underlinePrefix = false): array
    {
        $baseFields = $this->getTorrentBaseFields();
        if (!$torrent instanceof Torrent) {
            $torrent = Torrent::query()->findOrFail((int)$torrent, array_merge(['id'], $baseFields));
        }
        $docType = self::DOC_TYPE_TORRENT;
        $indexName = 'index';
        $idName = 'id';
        if ($underlinePrefix) {
            $indexName = "_$indexName";
            $idName = "_$idName";
        }
        $index = [
            $indexName => self::INDEX_NAME,
            $idName => $this->getTorrentId($torrent->id),
            'routing' => $torrent->owner,
        ];
        $data = Arr::only($torrent->toArray(), $baseFields);
        $data['mode'] = $torrent->basic_category->mode;
        $body = array_merge($data, [
            '_doc_type' => $docType,
            'torrent_id' => $torrent->id,
            'torrent_relations' => [
                'name' => $docType,
                'parent' => 'user_' . $torrent->owner,
            ],
        ]);
        return compact('index', 'body');
    }



    private function buildTorrentTagBody(Torrent $torrent, TorrentTag $torrentTag, bool $underlinePrefix = false)
    {
        $docType = self::DOC_TYPE_TAG;
        $indexName = 'index';
        $idName = 'id';
        if ($underlinePrefix) {
            $indexName = "_$indexName";
            $idName = "_$idName";
        }
        $index = [
            $indexName => self::INDEX_NAME,
            $idName => $this->getTorrentTagId($torrentTag->id),
            'routing' => $torrent->owner,
        ];
        $body = [
            '_doc_type' => $docType,
            'torrent_id' => $torrentTag->torrent_id,
            'tag_id' => $torrentTag->tag_id,
            'torrent_relations' => [
                'name' => $docType,
                'parent' => 'torrent_' . $torrent->id,
            ],
        ];
        return compact('index', 'body');
    }

    private function buildBookmarkBody(Torrent $torrent, Bookmark $bookmark, bool $underlinePrefix = false)
    {
        $docType = self::DOC_TYPE_BOOKMARK;
        $indexName = 'index';
        $idName = 'id';
        if ($underlinePrefix) {
            $indexName = "_$indexName";
            $idName = "_$idName";
        }
        $index = [
            $indexName => self::INDEX_NAME,
            $idName => $this->getBookmarkId($bookmark->id),
            'routing' => $torrent->owner,
        ];
        $body = [
            '_doc_type' => $docType,
            'torrent_id' => $bookmark->torrentid,
            'user_id' => $bookmark->userid,
            'torrent_relations' => [
                'name' => $docType,
                'parent' => 'torrent_' . $torrent->id,
            ],
        ];
        return compact('index', 'body');
    }


    private function logEsResponse($msg, $response)
    {
        if (isset($response['errors']) && $response['errors'] == true) {
            $msg .= var_export($response, true);
        }
        do_log($msg, 'info', isRunningInConsole());
    }

    private function getTorrentId($id): string
    {
        return "torrent_" . intval($id);
    }

    private function getTorrentTagId($id): string
    {
        return "torrent_tag_" . intval($id);
    }

    private function getUserId($id): string
    {
        return "user_" . intval($id);
    }

    private function getBookmarkId($id): string
    {
        return "bookmark_" . intval($id);
    }

    /**
     * detect elastic response has error or not
     *
     * @param $esResponse
     * @return bool
     */
    private function isEsResponseError($esResponse)
    {
        if (isset($esResponse['error'])) {
            return true;
        }
        //bulk insert
        if (isset($esResponse['errors']) && $esResponse['errors']) {
            return true;
        }
        //update by query
        if (!empty($esResponse['failures'])) {
            return true;
        }
        return false;
    }

    /**
     * build es query
     *
     * @param array $params
     * @param $user
     * @param string $queryString cat401=1&cat404=1&source2=1&medium2=1&medium3=1&codec3=1&audiocodec3=1&standard2=1&standard3=1&processing2=1&team3=1&team4=1&incldead=1&spstate=0&inclbookmarked=0&search=&search_area=0&search_mode=0
     * @return array
     */
    public function buildQuery(array $params, $user, string $queryString)
    {
        if (!($user instanceof User) || !$user->torrentsperpage || !$user->notifs) {
            $user = User::query()->findOrFail(intval($user));
        }
        //[cat401][cat404][sou1][med1][cod1][sta2][sta3][pro2][tea2][aud2][incldead=0][spstate=3][inclbookmarked=2]
        $userSetting = $user->notifs;
        $must = $must_not = [];
        $mustBoolShould = [];
        $must[] = ['match' => ['_doc_type' => self::DOC_TYPE_TORRENT]];
        if (!empty($params['mode'])) {
            $must[] = ['match' => ['mode' => $params['mode']]];
        }

        foreach (self::$queryFieldToTorrentFieldMaps as $queryField => $torrentField) {
            if (isset($params[$queryField]) && $params[$queryField] !== '') {
                $mustBoolShould[$torrentField][] = ['match' => [$torrentField => $params[$queryField]]];
                do_log("get mustBoolShould for $torrentField from params through $queryField: {$params[$queryField]}");
            } elseif (preg_match_all("/{$queryField}([\d]+)=/", $queryString, $matches)) {
                if (count($matches) == 2 && !empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $mustBoolShould[$torrentField][] = ['match' => [$torrentField => $match]];
                        do_log("get mustBoolShould for $torrentField from params through $queryField: $match");
                    }
                }
            } else {
                //get user setting
                $pattern = sprintf("/\[%s([\d]+)\]/", substr($queryField, 0, 3));
                if (preg_match($pattern, $userSetting, $matches)) {
                    if (count($matches) == 2 && !empty($matches[1])) {
                        foreach ($matches[1] as $match) {
                            $mustBoolShould[$torrentField][] = ['match' => [$torrentField => $match]];
                            do_log("get mustBoolShould for $torrentField from user setting through $queryField: $match");
                        }
                    }
                }
            }
        }

        $includeDead = 1;
        if (isset($params['incldead'])) {
            $includeDead = (int)$params['incldead'];
            do_log("maybe get must for visible from params");
        } elseif (preg_match("/\[incldead=([\d]+)\]/", $userSetting, $matches)) {
            $includeDead = $matches[1];
            do_log("maybe get must for visible from user setting");
        }
        if ($includeDead == 1) {
            //active torrent
            $must[] = ['match' => ['visible' => 'yes']];
            do_log("get must for visible = yes through incldead: $includeDead");
        } elseif ($includeDead == 2) {
            //dead torrent
            $must[] = ['match' => ['visible' => 'no']];
            do_log("get must for visible = no through incldead: $includeDead");
        }


        $includeBookmarked = 0;
        if (isset($params['inclbookmarked'])) {
            $includeBookmarked = (int)$params['inclbookmarked'];
            do_log("maybe get must or must_not for has_child.bookmark from params");
        } elseif (preg_match("/\[inclbookmarked=([\d]+)\]/", $userSetting, $matches)) {
            $includeBookmarked = $matches[1];
            do_log("maybe get must or must_not for has_child.bookmark from user setting");
        }
        if ($includeBookmarked == 1) {
            //only bookmark
            $must[] = ['has_child' => ['type' => 'bookmark', 'query' => ['match' => ['user_id' => $user->id]]]];
            do_log("get must for has_child.bookmark through inclbookmarked: $includeBookmarked");
        } elseif ($includeBookmarked == 2) {
            //only not bookmark
            $must_not[] = ['has_child' => ['type' => 'bookmark', 'query' => ['match' => ['user_id' => $user->id]]]];
            do_log("get must_not for has_child.bookmark through inclbookmarked: $includeBookmarked");
        }


        $spState = 0;
        if (isset($params['spstate'])) {
            $spState = (int)$params['spstate'];
            do_log("maybe get must for spstate from params");
        } elseif (preg_match("/\[spstate=([\d]+)\]/", $userSetting, $matches)) {
            $spState = $matches[1];
            do_log("maybe get must for spstate from user setting");
        }
        if ($spState > 0) {
            $must[] = ['match' => ['sp_state' => $spState]];
            do_log("get must for sp_state = $spState through spstate: $spState");
        }

        if (!empty($params['tag_id'])) {
            $must[] = ['has_child' => ['type' => 'tag', 'query' => ['match' => ['tag_id' => $params['tag_id']]]]];
            do_log("get must for has_child.tag through params.tag_id: {$params['tag_id']}");
        }


        if (!empty($params['search'])) {
            $searchMode = isset($params['search_mode']) && isset(self::SEARCH_MODES[$params['search_mode']]) ? $params['search_mode'] : self::SEARCH_MODE_AND;
            if (in_array($searchMode, [self::SEARCH_MODE_AND, self::SEARCH_MODE_OR])) {
                //and, or
                $keywordsArr = preg_split("/[\.\s]+/", trim($params['search']));
            } else {
                $keywordsArr = [trim($params['search'])];
            }
            $keywordsArr = array_slice($keywordsArr, 0, 10);
            $searchArea = isset($params['search_area']) && isset(self::SEARCH_AREAS[$params['search_area']]) ? $params['search_area'] : self::SEARCH_AREA_TITLE;
            if ($searchMode == self::SEARCH_MODE_AND || $searchMode == self::SEARCH_MODE_EXACT) {
                $keywordFlag = $searchMode == self::SEARCH_MODE_EXACT ? ".keyword" : "";
                if ($searchArea == self::SEARCH_AREA_TITLE) {
                    foreach ($keywordsArr as $keyword) {
                        $tmpMustBoolShould = [];
                        $tmpMustBoolShould[] = ['match' => ["name{$keywordFlag}" => $keyword]];
                        $tmpMustBoolShould[] = ['match' => ["small_descr{$keywordFlag}" => $keyword]];
                        $must[]['bool']['should'] = $tmpMustBoolShould;
                        do_log("get must bool should [SEARCH_MODE_AND + SEARCH_MODE_EXACT] for name+small_descr match '$keyword' through search");
                    }
                } elseif ($searchArea == self::SEARCH_AREA_DESC) {
                    foreach ($keywordsArr as $keyword) {
                        $must[] = ['match' => ["descr{$keywordFlag}" => $keyword]];
                        do_log("get must [SEARCH_MODE_AND + SEARCH_MODE_EXACT] for descr match '$keyword' through search");
                    }
                } elseif ($searchArea == self::SEARCH_AREA_IMDB) {
                    foreach ($keywordsArr as $keyword) {
                        $must[] = ['match' => ["url{$keywordFlag}" => $keyword]];
                        do_log("get must [SEARCH_MODE_AND + SEARCH_MODE_EXACT] for url match '$keyword' through search");
                    }
                } elseif ($searchArea == self::SEARCH_AREA_OWNER) {
                    foreach ($keywordsArr as $keyword) {
                        $must[] = ['has_parent' => ['parent_type' => 'user', 'query' => ['match' => ["username{$keywordFlag}" => $keyword]]]];
                        do_log("get must [SEARCH_MODE_AND + SEARCH_MODE_EXACT] has_parent.user match '$keyword' through search");
                    }
                }
            } elseif ($searchMode == self::SEARCH_MODE_OR) {
                if ($searchArea == self::SEARCH_AREA_TITLE) {
                    $tmpMustBoolShould = [];
                    foreach ($keywordsArr as $keyword) {
                        $tmpMustBoolShould[] = ['match' => ['name' => $keyword]];
                        $tmpMustBoolShould[] = ['match' => ['small_descr' => $keyword]];
                        do_log("get must bool should [SEARCH_MODE_OR] for name+small_descr match '$keyword' through search");
                    }
                    $must[]['bool']['should'] = $tmpMustBoolShould;
                } elseif ($searchArea == self::SEARCH_AREA_DESC) {
                    $tmpMustBoolShould = [];
                    foreach ($keywordsArr as $keyword) {
                        $tmpMustBoolShould[] = ['match' => ['descr' => $keyword]];
                        do_log("get must bool should [SEARCH_MODE_OR] for descr match '$keyword' through search");
                    }
                    $must[]['bool']['should'] = $tmpMustBoolShould;
                } elseif ($searchArea == self::SEARCH_AREA_IMDB) {
                    $tmpMustBoolShould = [];
                    foreach ($keywordsArr as $keyword) {
                        $tmpMustBoolShould[] = ['match' => ['url' => $keyword]];
                        do_log("get must bool should [SEARCH_MODE_OR] for url match '$keyword' through search");
                    }
                    $must[]['bool']['should'] = $tmpMustBoolShould;
                } elseif ($searchArea == self::SEARCH_AREA_OWNER) {
                    $tmpMustBoolShould = [];
                    foreach ($keywordsArr as $keyword) {
                        $tmpMustBoolShould[] = ['has_parent' => ['parent_type' => 'user', 'query' => ['match' => ['username' => $keyword]]]];
                        do_log("get must bool should [SEARCH_MODE_OR] has_parent.user match '$keyword' through search");
                    }
                    $must[]['bool']['should'] = $tmpMustBoolShould;
                }
            }
        }
        $query = [
            'bool' => [
                'must' => $must
            ]
        ];
        foreach ($mustBoolShould as $torrentField => $boolShoulds) {
            $query['bool']['must'][]['bool']['should'] = $boolShoulds;
        }
        if (!empty($must_not)) {
            $query['bool']['must_not'] = $must_not;
        }


        $sort = [];
        $sort[] = ['pos_state' => ['order' => 'desc']];
        $hasAddSetSortField = false;
        if (!empty($params['sort'])) {
            $direction = isset($params['type']) && in_array($params['type'], ['asc', 'desc']) ? $params['type'] : 'desc';
            foreach (self::$sortFieldMaps as $key => $value) {
                if ($key == $params['sort']) {
                    $hasAddSetSortField = true;
                    $sort[] = [$value => ['order' => $direction]];
                }
            }
        }
        if (!$hasAddSetSortField) {
            $sort[] = ['torrent_id' => ['order' => 'desc']];
        }

        $page = isset($params['page']) && is_numeric($params['page']) ? $params['page'] : 0;
        if ($user->torrentsperpage) {
            $size = $user->torrentsperpage;
        } elseif (($sizeFromConfig = Setting::get('main.torrentsperpage')) > 0) {
            $size = $sizeFromConfig;
        } else {
            $size = 50;
        }
        $size = min($size, 200);
        $offset = $page * $size;

        $result = [
            'query' => $query,
            'sort' => $sort,
            'from' => $offset,
            'size' => $size,
            '_source' => ['torrent_id', 'name', 'small_descr', 'owner']
        ];
        do_log(sprintf(
            "params: %s, user: %s, queryString: %s, result: %s",
            nexus_json_encode($params), $user->id, $queryString, nexus_json_encode($result)
        ));
        return $result;

    }

    public function listTorrentFromEs(array $params, $user, string $queryString)
    {
        $query = $this->buildQuery($params, $user, $queryString);
        $esParams = [
            'index' => self::INDEX_NAME,
            'body' => $query,
        ];
        $response = $this->getEs()->search($esParams);
        $result = [
            'total' => 0,
            'data' => [],
        ];
        if ($this->isEsResponseError($response)) {
            do_log("error response: " . nexus_json_encode($response), 'error');
            return $result;
        }
        if (empty($response['hits'])) {
            do_log("empty response hits: " . nexus_json_encode($response), 'error');
            return $result;
        }
        if ($response['hits']['total']['value'] == 0) {
            do_log("total = 0, " . nexus_json_encode($response));
            return $result;
        }
        $result['total'] = $response['hits']['total']['value'];
        $torrentIdArr = [];
        foreach ($response['hits']['hits'] as $value) {
            $torrentIdArr[] = $value['_source']['torrent_id'];
        }
//        $fieldStr = 'id, sp_state, promotion_time_type, promotion_until, banned, picktype, pos_state, category, source, medium, codec, standard, processing, team, audiocodec, leechers, seeders, name, small_descr, times_completed, size, added, comments,anonymous,owner,url,cache_stamp, pt_gen, hr';
        $fields = Torrent::getFieldsForList();
        $idStr = implode(',', $torrentIdArr);
        $result['data'] = Torrent::query()
            ->select($fields)
            ->whereIn('id', $torrentIdArr)
            ->orderByRaw("field(id,$idStr)")
            ->get()
            ->toArray()
        ;

        return $result;


    }

    private function getTorrentBaseFields()
    {
        return array_keys($this->getTorrentRawMappingFields());
    }

    public function updateTorrent(int $id): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $log = "[UPDATE_TORRENT]: $id";
        $result = $this->getTorrent($id);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        if ($result['found'] === false) {
            do_log("$log, not exists, do insert");
            return $this->addTorrent($id);
        }

        $baseFields = $this->getTorrentBaseFields();
        $torrent = Torrent::query()->findOrFail($id, array_merge(['id'], $baseFields));
        $data = $this->buildTorrentBody($torrent);
        $params = $data['index'];
        $params['body']['doc'] = $data['body'];
        $result = $this->getEs()->update($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));

        return $this->syncTorrentTags($torrent);
    }

    public function addTorrent(int $id): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $log = "[ADD_TORRENT]: $id";
        $baseFields = $this->getTorrentBaseFields();
        $torrent = Torrent::query()->findOrFail($id, array_merge(['id'], $baseFields));
        $data = $this->buildTorrentBody($torrent, true);
        $params = ['body' => []];
        $params['body'][] = ['index' => $data['index']];
        $params['body'][] = $data['body'];
        $result = $this->getEs()->bulk($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));

        return $this->syncTorrentTags($torrent);
    }

    public function getTorrent($id): callable|bool|array
    {
        if (!$this->enabled) {
            return false;
        }
        $params = [
            'index' => self::INDEX_NAME,
            'id' => $this->getTorrentId($id),
        ];
        return $this->getEs()->get($params);
    }

    public function deleteTorrent(int $id): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $log = "[DELETE_TORRENT]: $id";
        $params = [
            'index' => self::INDEX_NAME,
            'id' => $this->getTorrentId($id),
        ];
        $result = $this->getEs()->delete($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));

        return $this->syncTorrentTags($id, true);
    }

    public function syncTorrentTags($torrent, $onlyDelete = false): bool
    {
        if (!$this->enabled) {
            return true;
        }
        if (!$torrent instanceof Torrent) {
            $torrent = Torrent::query()->findOrFail((int)$torrent, ['id']);
        }
        $log = "sync torrent tags, torrent: " . $torrent->id;
        //remove first
        $params = [
            'index' => self::INDEX_NAME,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['_doc_type' => self::DOC_TYPE_TAG]],
                            ['has_parent' => ['parent_type' => 'torrent', 'query' => ['match' => ['torrent_id' => $torrent->id]]]]
                        ]
                    ]
                ]
            ]
        ];
        $result = $this->getEs()->deleteByQuery($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, delete torrent tag fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, delete torrent tag success: " . nexus_json_encode($result));
        if ($onlyDelete) {
            do_log("$log, only delete, return true");
            return true;
        }

        //then insert new
        $bulk = ['body' => []];
        foreach ($torrent->torrent_tags as $torrentTag) {
            $body = $this->buildTorrentTagBody($torrent, $torrentTag, true);
            $bulk['body'][] = ['index' => $body['index']];
            $bulk['body'][] = $body['body'];
        }
        if (empty($bulk['body'])) {
            do_log("$log, no tags, return true");
            return true;
        }
        $result = $this->getEs()->bulk($bulk);
        if ($this->isEsResponseError($result)) {
            do_log("$log, insert torrent tag fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, insert torrent tag success: " . nexus_json_encode($result));
        return true;
    }

    public function updateUser($user): bool
    {
        if (!$this->enabled) {
            return true;
        }
        if (!$user instanceof User) {
            $user = User::query()->findOrFail((int)$user, ['id', 'username']);
        }
        $log = "[UPDATE_USER]: " . $user->id;
        $data = $this->buildUserBody($user);
        $params = $data['index'];
        $params['body']['doc'] = $data['body'];
        $result = $this->getEs()->update($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));
        return true;
    }

    public function addBookmark($bookmark): bool
    {
        if (!$this->enabled) {
            return true;
        }
        if (!$bookmark instanceof Bookmark) {
            $bookmark = Bookmark::query()->with([
                'torrent' => function ($query) {$query->select(['id', 'owner']);}
            ])->findOrFail((int)$bookmark);
        }
        $log = "[ADD_BOOKMARK]: " . $bookmark->toJson();
        $bulk = ['body' => []];
        $body = $this->buildBookmarkBody($bookmark->torrent, $bookmark, true);
        $bulk['body'][] = ['index' => $body['index']];
        $bulk['body'][] = $body['body'];
        $result = $this->getEs()->bulk($bulk);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));
        return true;
    }

    public function deleteBookmark(int $id): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $log = "[DELETE_BOOKMARK]: $id";
        $params = [
            'index' => self::INDEX_NAME,
            'id' => $this->getBookmarkId($id),
        ];
        $result = $this->getEs()->delete($params);
        if ($this->isEsResponseError($result)) {
            do_log("$log, fail: " . nexus_json_encode($result), 'error');
            return false;
        }
        do_log("$log, success: " . nexus_json_encode($result));
        return true;
    }






}
