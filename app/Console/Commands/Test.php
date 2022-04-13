<?php

namespace App\Console\Commands;

use App\Events\TorrentUpdated;
use App\Http\Resources\TagResource;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Medal;
use App\Models\Peer;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\ExamRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\SearchRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use JeroenG\Explorer\Domain\Syntax\Matching;
use JeroenG\Explorer\Infrastructure\Scout\ElasticEngine;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;
use Rhilip\Bencode\Bencode;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Just for test';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
//        $searchRep = new SearchRepository();
//        $r = $searchRep->deleteIndex();
//        $r = $searchRep->createIndex();
//        $r = $searchRep->import();
//        dd($r);
//
//        $arr = [
//            'cat' => 'category',
//            'source' => 'source',
//            'medium' => 'medium',
//            'codec' => 'codec',
//            'audiocodec' => 'audiocodec',
//            'standard' => 'standard',
//            'processing' => 'processing',
//            'team' => 'team',
//        ];
        $queryString = 'cat401=1&cat404=1&source2=1&medium2=1&medium3=1&codec3=1&audiocodec3=1&standard2=1&standard3=1&processing2=1&team3=1&team4=1&incldead=1&spstate=0&inclbookmarked=0&search=&search_area=0&search_mode=0';
        $userSetting = '[cat401][cat404][sou1][med1][cod1][sta2][sta3][pro2][tea2][aud2][incldead=0][spstate=3][inclbookmarked=2]';
//        foreach ($arr as $queryField => $value) {
////            $pattern = sprintf("/\[%s([\d]+)\]/", substr($queryField, 0, 3));
//            $pattern = "/{$queryField}([\d]+)=/";
//            if (preg_match_all($pattern, $queryString, $matches)) {
//                dump($matches);
//                echo '----------------------' . PHP_EOL;
//            }
//        }
//        $r = preg_match("/\[incldead=([\d]+)\]/", $userSetting, $matches);
//        dump($matches);

        $params = [
            'tag_id' => 1,
//            'incldead' => 0,
//            'spstate' => 0,
//            'inclbookmarked' => 0,
//            'search' => '5034',
//            'search_area' => 4,
//            'search_mode' => 0,
        ];
        $queryString = "cat401=1&cat404=1&cat405=1&cat402=1&cat403=1&cat406=1&cat407=1&cat409=1&cat408=1&incldead=0&spstate=0&inclbookmarked=0&search=5034838&search_area=4&search_mode=0";
//        $r = $searchRep->listTorrentFromEs($params, 1, '');

//        $r = $searchRep->updateTorrent(1);
//        $r = $searchRep->updateUser(1);
//        $r = $searchRep->addTorrent(1);
//        $r = $searchRep->deleteBookmark(1);
//        $r = $searchRep->addBookmark(1);

//        $rep = new AttendanceRepository();
//        $uid = 1;
//        $attendance = $rep->getAttendance($uid);
//        $r = $rep->migrateAttendanceLogs($uid);
//        $r = $rep->getContinuousDays($attendance);
//        $r = $rep->getContinuousPoints(11);

        $url = 'https://www.imdb.com/title/tt4574334/?ref_=vp_vi_tt';
        $imdb = new Imdb();
        $rating = $imdb->getRating($url);
        dd($rating);
    }



}
