<?php

namespace App\Console\Commands;

use App\Events\TorrentUpdated;
use App\Filament\Resources\System\AgentAllowResource;
use App\Http\Resources\TagResource;
use App\Models\AgentAllow;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\Peer;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\AgentAllowRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\ExamRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\PluginRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\SearchRepository;
use App\Repositories\TagRepository;
use App\Repositories\ToolRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imdb\Cache;
use League\Flysystem\StorageAttributes;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;
use NexusPlugin\Menu\Filament\MenuItemResource\Pages\ManageMenuItems;
use NexusPlugin\Menu\MenuRepository;
use NexusPlugin\Menu\Models\MenuItem;
use NexusPlugin\Permission\Models\Permission;
use NexusPlugin\Permission\Models\Role;
use NexusPlugin\PostLike\PostLikeRepository;
use NexusPlugin\StickyPromotion\Models\StickyPromotion;
use NexusPlugin\StickyPromotion\Models\StickyPromotionParticipator;
use NexusPlugin\Work\Models\RoleWork;
use NexusPlugin\Work\WorkRepository;
use PhpIP\IP;
use PhpIP\IPBlock;
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
        $rep = new MeiliSearchRepository();
//        $r = $rep->doImportFromDatabase();
//        dd($r);
//        $r = $rep->import();

        $r = $rep->search([
            'search' => '200',
            'spstate' => 0,
            'incldead' => 0,
            'mode' => 4,
//            'cat401' => 1,
            'sort' => '4',
            'type' => 'desc',
            'search_mode' => 0,
            'inclbookmarked' => 0,
            'approval_status' => 1,
//            'size_end' => 20,
//            'added_end' => '2023-02-11',
        ], 1, 'incldead=0&spstate=1&inclbookmarked=0&approval_status=1&size_begin=&size_end=&seeders_begin=&seeders_end=&leechers_begin=&leechers_end=&times_completed_begin=&times_completed_end=&added_begin=&added_end=&search=200&search_area=0&search_mode=0');
        dd($r);
    }

}
