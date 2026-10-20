<?php
namespace NexusPlugin\Tracker;

use App\Filament\OptionsTrait;
use App\Models\User;
use App\Repositories\ToolRepository;
use Carbon\Carbon;
use Filament\Schemas\Components\Tabs\Tab;
use GuzzleHttp\Client;
use Nexus\Database\NexusDB;
use Nexus\Nexus;
use Nexus\Plugin\BasePlugin;
use Filament\Forms;
use Livewire\Component;
use Livewire\Livewire;
use Filament\Facades\Filament;
use Nexus\Plugin\Plugin;

class TrackerRepository extends BasePlugin
{
    use OptionsTrait;

    const ID = Tracker::ID;

    const SETTING_PREFIX = "go_tracker";

    const COMPATIBLE_NP_VERSION = '1.9.4';

    const VERSION = '3.0.0';

    private static array $statusData = [];


    public function install()
    {
        $redis = NexusDB::redis();
        $info = $redis->info("server");
        $redisCurrentVersion = $info['redis_version'];
        $redisMinVersion = "7.4.0";
        if (version_compare($redisCurrentVersion, $redisMinVersion, '<')) {
            throw new \RuntimeException("Redis version: $redisCurrentVersion not supported, require >= $redisMinVersion");
        }
        $this->runMigrations($this->getMigrationFilePath());
    }

    public function uninstall()
    {
        $this->runMigrations($this->getMigrationFilePath(), true);
    }

    public function boot()
    {
        $self = self::getInstance();
        $basePath = dirname(__DIR__);
        Nexus::addTranslationNamespace($basePath . '/resources/lang', self::ID);

        add_filter('nexus_setting_tabs', [$self, 'filterAddSettingTab'], 10, 1);
        add_filter('torrent_list', [$self, 'filterAddSeederLeecherCountForList'], 10, 1);
        add_filter('torrent_detail', [$self, 'filterAddSeederLeecherCountForDetail'], 10, 1);
        add_filter('torrent_seeder_leecher_list', [$self, 'filterListSeederAndLeechers'], 10, 2);

//        add_action('nexus_setting_update', [$self, 'actionSaveToRedisForNginx']);
    }


    private function getMigrationFilePath(): string
    {
        return dirname(__DIR__) . '/database/migrations';
    }

    private function isFrontendUseTrackerApi(): bool
    {
        return get_setting($this->getIsFrontendUseTrackerApiSettingName()) == "yes";
    }

    public function filterAddSettingTab(array $tabs): array
    {
        $prefix = self::SETTING_PREFIX;
        $tabs[] = Tab::make($this->trans("tracker.name"))
            ->id(self::ID)
            ->schema([
                Forms\Components\TextInput::make("$prefix.internal_host")
                    ->label($this->trans("tracker.internal_host"))
                    ->helperText($this->trans("tracker.internal_host_help"))
                ,
                Forms\Components\TextInput::make("$prefix.token")
                    ->label($this->trans("tracker.token"))
                    ->helperText($this->trans("tracker.token_help"))
                ,
                Forms\Components\Radio::make($this->getIsFrontendUseTrackerApiSettingName())
                    ->options(self::$yesOrNo)
                    ->inline()
                    ->label($this->trans("tracker.is_frontend_use_tracker_api"))
                    ->helperText($this->trans("tracker.is_frontend_use_tracker_api_help"))
                ,
//                Forms\Components\Radio::make($this->getIsTestModeSettingName())
//                    ->options(self::$yesOrNo)
//                    ->inline()
//                    ->label($this->trans("tracker.is_test_mode"))
//                    ->helperText($this->trans("tracker.is_test_mode_help"))
//                ,
//                Forms\Components\Textarea::make($this->getTestModeUidSettingName())
//                    ->label($this->trans("tracker.test_mode_uid"))
//                    ->helperText($this->trans("tracker.test_mode_uid_help"))
//                    ->columnSpanFull()
//                ,
            ])->columns(2);

        return $tabs;
    }

    private function getTrackerInternalUrl($path = ""): string
    {
       $url = rtrim(get_setting(sprintf("%s.internal_host", self::SETTING_PREFIX)), '/');
       if (trim($path, "/") != "status") {
           $url .= "/api";
       }
       if ($path != "" && $path != "/") {
           $url .= "/" . trim($path, "/");
       }
       return $url;
    }


    public function filterAddSeederLeecherCountForList(array $rows): array
    {
        if (!$this->isFrontendUseTrackerApi()) {
            return $rows;
        }
        $idArr = [];
        foreach ($rows as $row) {
            $idArr[] = intval($row['id']);
        }
        unset($row);
        $result = $this->requestTrackerApi("list-seeder-leecher-count", ['ids' => $idArr]);
        if ($result === false) {
            return $rows;
        }
        foreach ($rows as &$row) {
            $id = $row['id'];
            if (isset($result['data'][$id]['SeederCount'])) {
                $row['seeders'] = $result['data'][$id]['SeederCount'];
                do_log("torrent: $id -> seeders: {$row['seeders']}", "debug");
            }
            if (isset($result['data'][$id]['LeecherCount'])) {
                $row['leechers'] = $result['data'][$id]['LeecherCount'];
                do_log("torrent: $id -> leechers: {$row['leechers']}", "debug");
            }
        }
        return $rows;
    }

    public function filterAddSeederLeecherCountForDetail(array $torrent): array
    {
        if (!$this->isFrontendUseTrackerApi()) {
            return $torrent;
        }
        $result = $this->filterAddSeederLeecherCountForList([$torrent]);
        return $result[0];
    }

    public function filterListSeederAndLeechers(array $seederAndLeechers, int $torrentId): array
    {
        if (!$this->isFrontendUseTrackerApi()) {
            return $seederAndLeechers;
        }
        $result = $this->requestTrackerApi("list-peer", ['id' => $torrentId]);
        if ($result === false) {
            return $seederAndLeechers;
        }
        $newSeederAndLeechers = ["seeders" => [], "leechers" => []];
        foreach ($result['data'] as $item) {
            $item['st'] = strtotime($item['started']);
            $item['la'] = strtotime($item['last_action']);
            if ($item['seeder'] == "yes") {
                $newSeederAndLeechers["seeders"][] = $item;
            } else {
                $newSeederAndLeechers["leechers"][] = $item;
            }
        }
        return $newSeederAndLeechers;
    }

    private function requestTrackerApi(string $api, array $params = []): array|false
    {
        $start = microtime(true);
        $url = $this->getTrackerInternalUrl($api);
        $logPrefix = sprintf("[requestTrackerApi] url: %s, params: %s", $url, nexus_json_encode($params));
        $httpClient = new Client();
        try {
            if (str_ends_with($api, "status")) {
                $response = $httpClient->get($url);
            } else {
                $response = $httpClient->post($url, ["json" => $params, 'headers' => ['X-Passkey' => $params['passkey'] ?? get_user_passkey()]]);
            }
        } catch (\Throwable $throwable) {
            do_log(sprintf("$logPrefix, error: %s", $throwable->getMessage()), "error");
            return false;
        }
        if ($response->getStatusCode() != 200) {
            do_log("$logPrefix, status code: " . $response->getStatusCode(), 'error');
            return false;
        }
        $result = json_decode((string)$response->getBody(), true);
        if (!isset($result['ret']) || $result['ret'] != 0) {
            do_log(sprintf("$logPrefix, ret: %s, msg: %s", $result['ret'], $result['msg']), 'error');
            return false;
        }
        do_log(sprintf("$logPrefix, cost time: %s seconds", number_format(microtime(true) - $start, 3)));
        return $result;
    }

    private function getStatusData(): array
    {
        if (empty(self::$statusData)) {
            $data = $this->requestTrackerApi("status");
            if ($data === false) {
                self::$statusData = [];
            } else {
                self::$statusData = $data['data'];
            }
        }
        return self::$statusData;
    }

    public function getWidgetTableRows($widgetName): array
    {
        $result = [];
        $statusData = $this->getStatusData();
        if (!empty($statusData[$widgetName])) {
           foreach ($statusData[$widgetName] as $name => $value) {
               $item = [
                   'name' => $name,
                   'text' => $this->trans("status.$name"),
                   'value' => $value,
               ];
               if ($name == "IsSyncRunning" && $value) {
                   $item['class'] = "bg-warning-500";
               } elseif ($name == "LastSyncFailCount" && $value > 0) {
                   $item['class'] = "bg-danger-500";
               }
               $result[] = $item;
           }
        }
        return $result;
    }

    private function getIsFrontendUseTrackerApiSettingName(): string
    {
        return sprintf("%s.is_frontend_use_tracker_api", self::SETTING_PREFIX);
    }

    private function getIsTestModeSettingName(): string
    {
        return sprintf("%s.is_test_mode", self::SETTING_PREFIX);
    }

    private function getTestModeUidSettingName(): string
    {
        return sprintf("%s.test_mode_uid", self::SETTING_PREFIX);
    }


    private function getIsTestModeEnableKeyForNginx(): string
    {
        return sprintf("%s:is_test_mode", self::SETTING_PREFIX);
    }

    private function getTestPasskeyHashKeyForNginx(): string
    {
        return sprintf("%s:test_passkey", self::SETTING_PREFIX);
    }

    /**
     * 用于 nginx 判断用户是否测试用户
     * @return void
     */
    public function actionSaveToRedisForNginx(): void
    {
        $redis = NexusDB::redis();
        $isTestModeEnabledKey = $this->getIsTestModeEnableKeyForNginx();
        $isTestModeEnabled = get_setting_from_db($this->getIsTestModeSettingName());

        $uidStr = get_setting_from_db($this->getTestModeUidSettingName());
        $uidArr = preg_split("/[\r\n\s,，]+/", trim($uidStr));
        do_log("uidArr: " . json_encode($uidArr));
        $passkeyResult = User::query()->whereIn("id", $uidArr)->get(["passkey"]);
        $testPasskeyHashKey = $this->getTestPasskeyHashKeyForNginx();
        $values = [];
        foreach ($passkeyResult as $item) {
            $values[$item->passkey] = 1;
        }
        try {
            $redis->set($isTestModeEnabledKey, $isTestModeEnabled);
            $redis->del($testPasskeyHashKey);
            if (empty($values)) {
                do_log("no test uid...", 'debug');
            } else {
                $redis->hMSet($testPasskeyHashKey, $values);
            }
        } catch (\Throwable $throwable) {
            do_log(sprintf("actionSaveTestUserPasskeyToRedisHash error: %s", $throwable->getMessage()), "error");
        }
    }

    public function isCurrentUserTest(): bool
    {
        try {
            $result = NexusDB::redis()->hGet($this->getTestPasskeyHashKeyForNginx(), get_user_passkey());
            return $result == "1";
        } catch (\Throwable $throwable) {
            do_log($throwable->getMessage() . $throwable->getTraceAsString());
            return false;
        }
    }

    public function checkStatus(): void
    {
        $cacheKey = self::SETTING_PREFIX . ":latest_status";
//        $statusResponse = $this->requestTrackerApi("status", ['passkey' => nexus_env("GO_TRACKER_SUPER_PASSKEY")]);
//        $statusData = $statusResponse['data'] ?? [];
        $debugData = $this->getDebugData();
        $statusData = $debugData['data'] ?? [];
        $shouldAlarm = $debugData['shouldAlarm'] ?? false;
        $toolRep = new ToolRepository();
        $subjectKey = $this->getTransKey("tracker.alarm_subject");
        $contentKey = $this->getTransKey("tracker.alarm_content");
        $lastAlarmAtKey = "$cacheKey:last_alarm_at";
        $alarmCountKey = "$cacheKey:alarm_count";
        do_log(sprintf("%s, statusData: %s", $cacheKey, json_encode($statusData)));
        if ($shouldAlarm) {
            $latestStatusStr = NexusDB::cache_get($cacheKey);
            if (empty($latestStatusStr)) {
                do_log("no latest status, maybe not start up yet...");
                return;
            }
            $latestStatus = json_decode($latestStatusStr, true);
            //不正常，检查频率
            $alarmCount =  intval(NexusDB::cache_get($alarmCountKey));
            $logPrefix = "alarmCount: $alarmCount";
            if ($alarmCount > 0) {
                $lastAlarmAt = Carbon::parse(NexusDB::cache_get($lastAlarmAtKey));
                //已经发送过，按照以下间隔发送。只发送 6 次
                $sendPeriod = [
                    1 => 60,
                    2 => 60 * 5,
                    3 => 60 * 15,
                    4 => 60 * 60,
                    5 => 60 * 60 * 24
                ];
                if (!isset($sendPeriod[$alarmCount])) {
                    do_log(sprintf("$logPrefix, already send %s times, last send at: %s, will not send ...", $alarmCount, $lastAlarmAt->toDateTimeString()));
                    return;
                }
                $requirePassSeconds = $sendPeriod[$alarmCount];
                $diffInSeconds = now()->diffInSeconds($lastAlarmAt, true);
                if ($diffInSeconds < $requirePassSeconds) {
                    do_log(sprintf("$logPrefix, current diffInSeconds: %s < requirePassSeconds: %s, return ...", $diffInSeconds, $requirePassSeconds));
                    return;
                }
            }
            $contentContext = [
                "status_api" => $this->getTrackerInternalUrl("status"),
                "latest_status" => json_encode($latestStatus, JSON_PRETTY_PRINT),
                "alarm_count" => $alarmCount+1,
            ];
            $toolRep->sendAlarmEmail($subjectKey, [], $contentKey, $contentContext);
            NexusDB::cache_put($lastAlarmAtKey, now()->toDateTimeString());
            NexusDB::cache_put($alarmCountKey, $alarmCount+1);
            do_log(sprintf("$logPrefix, %s, exception, latest_status: %s", $cacheKey, $latestStatusStr), "error");
        } else {
            //正常情况下，记录状态
            NexusDB::cache_put($cacheKey, json_encode($statusData));
            NexusDB::cache_del($lastAlarmAtKey);
            NexusDB::cache_del($alarmCountKey);
        }
    }

    public function getDebugData(): array
    {
        $url = rtrim(get_setting(sprintf("%s.internal_host", self::SETTING_PREFIX)), '/');
        $html = file_get_contents("$url/debug/pprof/");
        if (empty($html)) {
            return [];
        }
        // 创建 DOMDocument 实例
        $dom = new \DOMDocument();

        // 允许 HTML5 语法
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        // 获取所有<tr>元素
        $rows = $dom->getElementsByTagName('tr');

        // 遍历每一行并提取数据
        $result = [];
        $shouldAlarm = false;
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length === 2) {
                $count = $cells->item(0)->nodeValue;
                $profile = $cells->item(1)->textContent;
                if (intval($count) > 5000) {
                    $shouldAlarm = true;
                }
                $result[$profile] = $count;
            }
        }
        return [
            'data' => $result,
            'shouldAlarm' => $shouldAlarm
        ];
    }



}
