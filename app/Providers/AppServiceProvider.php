<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexus\Nexus;
use Filament\Facades\Filament;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
//        JsonResource::withoutWrapping();
        DB::connection(config('database.default'))->enableQueryLog();

        Filament::serving(function () {
            Filament::registerNavigationGroups([
                'User',
                'Torrent',
                'System',
            ]);
        });

    }
}
