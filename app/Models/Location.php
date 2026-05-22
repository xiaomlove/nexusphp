<?php

namespace App\Models;

/**
 * Eloquent model for the `locations` table — the SYSOP-managed
 * lookup that maps an IP-address range to a country / sub-region
 * label and a per-region speed profile (theory + practical
 * up/down). Read by the legacy `tracker_filter` lua plus a
 * handful of staff-only IP-history pages.
 *
 * Schema (see `database/migrations/2021_06_08_113437_create_locations_table.php`):
 *   - `id`            int unsigned auto-increment
 *   - `name`          varchar(50)  nullable
 *   - `location_main` varchar(200) default ''
 *   - `location_sub`  varchar(200) default ''
 *   - `flagpic`       varchar(50)  nullable
 *   - `start_ip`      varchar(20)  default '' (dotted IPv4)
 *   - `end_ip`        varchar(20)  default '' (dotted IPv4)
 *   - `theory_upspeed`     int unsigned default 10
 *   - `practical_upspeed`  int unsigned default 10
 *   - `theory_downspeed`   int unsigned default 10
 *   - `practical_downspeed` int unsigned default 10
 *   - `hit`           int unsigned default 0 (read-only counter,
 *                     not surfaced in the admin form)
 *
 * The migrated Filament admin (`App\Filament\Resources\System\
 * LocationResource`, replaces `public/location.php`) is the only
 * write path, so we don't need a `creating` boot hook for default
 * values — the Filament form enforces them on create.
 */
class Location extends NexusModel
{
    protected $table = 'locations';

    protected $fillable = [
        'name',
        'location_main',
        'location_sub',
        'flagpic',
        'start_ip',
        'end_ip',
        'theory_upspeed',
        'practical_upspeed',
        'theory_downspeed',
        'practical_downspeed',
    ];

    protected $casts = [
        'theory_upspeed' => 'integer',
        'practical_upspeed' => 'integer',
        'theory_downspeed' => 'integer',
        'practical_downspeed' => 'integer',
        'hit' => 'integer',
    ];
}
