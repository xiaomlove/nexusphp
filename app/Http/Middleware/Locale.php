<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Locale
{
    public static $languageMaps = [
        'en' => 'en',
        'chs' => 'zh_CN',
        'cht' => 'zh_TW',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $language = $request->user()->language;
        $locale = self::$languageMaps[$language->site_lang_folder] ?? 'en';
        do_log("set locale: " . $locale);
        App::setLocale($locale);
        return $next($request);
    }
}
