<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class Locale
{
    public static array $languageMaps = [
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
        $user = $request->user();
        if ($user) {
            $locale = $user->locale;
            do_log("locale from user: {$user->id}, set locale: $locale");
        } else {
            $locale = self::getLocaleFromCookie() ?? 'en';
            do_log("locale from cookie, set locale: $locale");
        }
        App::setLocale($locale);
        Carbon::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        if ($response instanceof Response || $response instanceof JsonResponse) {
            $response->header('Request-Id', nexus()->getRequestId())->header('Running-In-Octane', RUNNING_IN_OCTANE ? 1 : 0);
        }
        return $response;
    }

    public static function getLocaleFromCookie()
    {
        if (IN_NEXUS) {
            $lang = IN_TRACKER ? null : get_langfolder_cookie();
            $log = "IN_NEXUS, get_langfolder_cookie() or IN_TRACKER use null: $lang";
        } else {
            $lang = Cookie::get('c_lang_folder');
            $log = "Cookie::get(): $lang";
        }
        do_log($log);
        return self::$languageMaps[$lang] ?? null;
    }

}
