<?php

namespace App\Repositories;

use App\Models\Peer;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

class DashboardRepository extends BaseRepository
{
    public function getSystemInfo(): array
    {
        $result = [];
        $name = 'nexus_version';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => VERSION_NUMBER,
        ];
        $name = 'nexus_release_date';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => RELEASE_DATE,
        ];
        $name = 'laravel_version';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => \Illuminate\Foundation\Application::VERSION,
        ];
        $name = 'filament_version';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => \Composer\InstalledVersions::getPrettyVersion('filament/filament'),
        ];
        $name = 'php_version';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => PHP_VERSION,
        ];
        $name = 'mysql_version';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => DB::select(DB::raw('select version() as info'))[0]->info,
        ];
        $name = 'os';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' => PHP_OS,
        ];
        $name = 'server_software';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' =>  $_SERVER['SERVER_SOFTWARE'] ?? '',
        ];
        $name = 'load_average';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.system_info.$name"),
            'value' =>  exec('uptime'),
        ];
        return $result;
    }

    public function getStatData()
    {
        return [
            'user_class' => [
                'text' => nexus_trans('dashboard.user_class.page_title'),
                'data' => $this->statUserClass()
            ],
            'user' => [
                'text' => nexus_trans('dashboard.user.page_title'),
                'data' => $this->statUsers()
            ],
            'torrent' => [
                'text' => nexus_trans('dashboard.torrent.page_title'),
                'data' => $this->statTorrents()
            ],
            'system_info' => [
                'text' => nexus_trans('dashboard.system_info.page_title'),
                'data' => $this->getSystemInfo()
            ],
        ];
    }

    public function statUserClass()
    {
        $userClasses = User::query()
            ->groupBy('class')
            ->selectRaw('class, count(*) as counts')
            ->get()
            ->pluck('counts', 'class');
        $result = [];
        foreach (User::$classes as $class => $value) {
            if ($class >= User::CLASS_VIP) {
                break;
            }
            $result[$class] = [
                'name' => $class,
                'text' => $value['text'],
                'value' => $userClasses->has($class) ? $userClasses->get($class) : 0,
            ];
        }
        return $result;
    }

    public function statUsers()
    {
        $result = [];
        $now = Carbon::now();

        $name = 'total';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => sprintf('%s / %s', User::query()->count(), Setting::get('main.maxusers')),
        ];
        $name = 'unconfirmed';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('status', User::STATUS_PENDING)->count(),
        ];
        $name = 'visit_last_one_day';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('last_access', '>', $now->subDays(1))->count(),
        ];
        $name = 'visit_last_one_week';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('last_access', '>', $now->subDays(7))->count(),
        ];
        $name = 'visit_last_30_days';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('last_access', '>', $now->subDays(30))->count(),
        ];
        $name = 'vip';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('class', User::CLASS_VIP)->count(),
        ];
        $name = 'donated';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('donor', 'yes')->count(),
        ];
        $name = 'warned';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('warned', 'yes')->count(),
        ];
        $name = 'disabled';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.user.$name"),
            'value' => User::query()->where('enabled', 'no')->count(),
        ];

        $statGender = User::query()->groupBy('gender')->selectRaw('gender, count(*) as counts')->get()->pluck('counts','gender');
        foreach ($statGender as $gender => $value) {
            $name = "gender_$gender";
            $result[$name] = [
                'name' => $name,
                'text' => nexus_trans("dashboard.user.$name"),
                'value' => $statGender->has($gender) ? $statGender->get($gender) : 0,
            ];
        }
        return $result;
    }

    public function statTorrents()
    {
        $now = now();
        $name = 'total';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => Torrent::query()->count(),
        ];
        $name = 'dead';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => Torrent::query()->where('visible', '=', Torrent::VISIBLE_NO)->count(),
        ];

        $seeders = Peer::query()->where('seeder', 'yes')->count();
        $name = 'seeders';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => $seeders,
        ];

        $leechers = Peer::query()->where('seeder', 'no')->count();
        $name = 'leechers';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => $leechers,
        ];
        $name = 'seeders_leechers';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => $seeders + $leechers,
        ];
        $name = 'seeders_leechers_ratio';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => $leechers == 0 ? 0 : number_format(($seeders / $leechers) * 100) . '%',
        ];
        $name = 'active_web_users';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => User::query()->where('last_access', '>', $now->subSeconds(900))->count(),
        ];
        $name = 'active_tracker_users';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => Peer::query()->selectRaw('count(distinct(userid)) as counts')->first()->counts,
        ];

        $name = 'total_torrent_size';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => mksize(Torrent::query()->sum('size')),
        ];

        $total_uploaded_byte = User::query()->sum('uploaded');
        $total_downloaded_byte = User::query()->sum('downloaded');
        $total_byte = $total_uploaded_byte + $total_downloaded_byte;

        $name = 'total_uploaded';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => mksize($total_uploaded_byte),
        ];
        $name = 'total_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => mksize($total_downloaded_byte),
        ];
        $name = 'total_uploaded_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => nexus_trans("dashboard.torrent.$name"),
            'value' => mksize($total_byte),
        ];

        return $result;
    }

    public function latestUser()
    {
        return User::query()->orderBy('id', 'desc')->limit(10)->select(User::$commonFields)->get();
    }

    public function latestTorrent()
    {
        return Torrent::query()->with(['user'])->orderBy('id', 'desc')->limit(5)->get();
    }


}
