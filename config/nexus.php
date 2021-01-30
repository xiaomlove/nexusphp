<?php

return [

    'timezone' => env('TIMEZONE', 'PRC'),

    'log_file' => env('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => env('LOG_SPLIT', 'daily'),

    'use_cron_trigger_cleanup' => env('USE_CRON_TRIGGER_CLEANUP', false),

];