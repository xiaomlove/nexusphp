<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/cc98bar.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Variant of the userbar PNG generator (`/mybar.php`, migrated in
 * #292) that takes its parameters from a path-style URI rather than
 * the query string. Forum signatures embed it as
 * `<img src="/cc98bar.php/nn0nr255ng128id42.png">`. The trailing
 * `.png` literal makes the URL look like an image to image-fetching
 * crawlers and to the browser's Save-As menu.
 *
 * Original legacy flow (`public/cc98bar.php`, 168 LOC):
 *   1. `dbconn()` only — no `loggedinorreturn()`. Reachable as a
 *      guest because forum signatures get rendered in pages/RSS
 *      feeds that may be crawled without a session.
 *   2. A single giant `preg_match` against `$_SERVER['REQUEST_URI']`
 *      that extracts up to 24 named-but-positional parameters from
 *      the path:
 *        - `nn` (no-name flag), `nr/ng/nb/ns/nx/ny` (name colour /
 *          size / x / y), `nu` (no-up flag), `ur/ug/ub/us/ux/uy`
 *          (uploaded colour / size / x / y), `nd` (no-down flag),
 *          `dr/dg/db/ds/dx/dy` (downloaded colour / size / x / y),
 *          `bg` (background image id), `id` (user id).
 *      A URL not matching the regex `exit`s with a literal
 *      "Error! Invalid URL format." string.
 *   3. `$Cache->get_value('userbar_<requestUri>')` lookup.
 *   4. SELECT username/uploaded/downloaded/class/privacy. Hidden
 *      when the user does not exist, has `privacy='strong'`, or has
 *      `class < $userbar_class`.
 *   5. GD pipeline: `imagecreatefrompng('pic/userbar/<bg>.png')`,
 *      then up to three `imagestring()` calls (name / up / down,
 *      each gated on its `nN/nu/nd` suppress flag).
 *   6. `imagepng()` to stdout under `header('Content-type: image/png')`.
 *
 * Replacement contract (mirrors `MyBarController`):
 *   - Public route — same as the legacy script.
 *   - URL preserved (`/cc98bar.php/<24-flag-string>id<userid>.png`)
 *     so legacy forum signatures keep rendering. The legacy regex
 *     is reused bit-for-bit; a malformed path returns 204 instead
 *     of the legacy "Error!" 200 string (the response is consumed
 *     by an `<img>` tag — neither shape is ever shown to a human,
 *     but 204 spares the cache layer from caching it).
 *   - All four short-circuit gates (regex miss, unknown user,
 *     strong-privacy user, below-`authority.userbar` user) return
 *     a 204 with an empty body.
 *   - Cache key matches the legacy `userbar_<requestUri>` shape so
 *     existing cached PNGs survive the migration.
 *
 * Sweep findings:
 *   - No `lang/<locale>/lang_cc98bar.php` files (the script never
 *     emitted localised text).
 *   - No external href references in the codebase (the URL is
 *     embedded in user-authored signatures; live signatures keep
 *     working because the URL is preserved).
 *   - No menu seeders / `Update.php` references.
 */
class Cc98barController extends Controller
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * The legacy regex, preserved bit-for-bit. Match groups are
     * captured by position (every other group is a guard for the
     * preceding letter prefix), so the indices we read below
     * (`\\2`, `\\4`, …) match the legacy `preg_replace` calls.
     */
    private const URI_REGEX = '/.*cc98bar\.php\/(nn([0,1]{1}))?(nr([0-9]+))?(ng([0-9]+))?(nb([0-9]+))?(ns([1-5]{1}))?(nx([0-9]+))?(ny([0-9]+))?(nu([0,1]{1}))?(ur([0-9]+))?(ug([0-9]+))?(ub([0-9]+))?(us([1-5]{1}))?(ux([0-9]+))?(uy([0-9]+))?(nd([0,1]{1}))?(dr([0-9]+))?(dg([0-9]+))?(db([0-9]+))?(ds([1-5]{1}))?(dx([0-9]+))?(dy([0-9]+))?(bg([0-9]+))?id([0-9]+)\.png$/i';

    public function __invoke(Request $request): Response
    {
        $requestUri = (string) $request->server('REQUEST_URI', '');
        if (! preg_match(self::URI_REGEX, $requestUri, $matches)) {
            return $this->emptyResponse();
        }

        // Match group 45 carries the user id (the only required
        // group in the regex). Earlier groups carry optional flags;
        // when absent the captured value is an empty string.
        $userId = (int) ($matches[45] ?? 0);
        if ($userId <= 0) {
            return $this->emptyResponse();
        }

        $cacheKey = 'userbar_'.$requestUri;
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $this->pngResponse($cached);
        }

        $row = NexusDB::table('users')
            ->where('id', $userId)
            ->select(['username', 'uploaded', 'downloaded', 'class', 'privacy'])
            ->first();
        $row = $row ? (array) $row : null;

        if ($row === null) {
            return $this->emptyResponse();
        }
        if (($row['privacy'] ?? '') === 'strong') {
            return $this->emptyResponse();
        }

        $userbarClass = (int) get_setting('authority.userbar');
        if ((int) $row['class'] < $userbarClass) {
            return $this->emptyResponse();
        }

        $bgPic = (int) ($matches[44] ?? 0);
        $bgPath = self::backgroundPath($bgPic);
        if ($bgPath === null) {
            return $this->emptyResponse();
        }

        $payload = $this->renderPng(
            backgroundPath: $bgPath,
            username: (string) $row['username'],
            uploaded: mksize((int) $row['uploaded']),
            downloaded: mksize((int) $row['downloaded']),
            matches: $matches,
        );
        if ($payload === null) {
            return $this->emptyResponse();
        }

        $this->cachePut($cacheKey, $payload);

        return $this->pngResponse($payload);
    }

    /**
     * Defence-in-depth path resolver — same shape as
     * `MyBarController::backgroundPath`. `intval()` already
     * neutralises non-integer payloads, but `realpath()` ensures
     * the final path stays inside `public/pic/userbar/`.
     */
    private static function backgroundPath(int $bgPic): ?string
    {
        if ($bgPic < 0) {
            return null;
        }
        $candidate = public_path('pic/userbar/'.$bgPic.'.png');
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }
        $expectedRoot = realpath(public_path('pic/userbar')) ?: '';
        if ($expectedRoot === '' || ! str_starts_with($real, $expectedRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /**
     * @param  array<int,string>  $matches
     */
    private function renderPng(
        string $backgroundPath,
        string $username,
        string $uploaded,
        string $downloaded,
        array $matches,
    ): ?string {
        $img = @imagecreatefrompng($backgroundPath);
        if ($img === false) {
            return null;
        }
        imagealphablending($img, false);

        // Match groups (legacy positions): 2 = nn, 4 = nr, 6 = ng,
        // 8 = nb, 10 = ns, 12 = nx, 14 = ny, 16 = nu, 18 = ur,
        // 20 = ug, 22 = ub, 24 = us, 26 = ux, 28 = uy, 30 = nd,
        // 32 = dr, 34 = dg, 36 = db, 38 = ds, 40 = dx, 42 = dy.
        // The empty-string default ('off' flag) keeps the legacy
        // "render unless flag explicitly set" semantics.
        if (($matches[2] ?? '') !== '1') {
            $this->stamp(
                $img, $username, $matches,
                redIdx: 4, defaultRed: 255,
                greenIdx: 6, defaultGreen: 255,
                blueIdx: 8, defaultBlue: 255,
                sizeIdx: 10, defaultSize: 3,
                xIdx: 12, defaultX: 10,
                yIdx: 14, defaultY: 3,
            );
        }
        if (($matches[16] ?? '') !== '1') {
            $this->stamp(
                $img, $uploaded, $matches,
                redIdx: 18, defaultRed: 0,
                greenIdx: 20, defaultGreen: 255,
                blueIdx: 22, defaultBlue: 0,
                sizeIdx: 24, defaultSize: 3,
                xIdx: 26, defaultX: 100,
                yIdx: 28, defaultY: 3,
            );
        }
        if (($matches[30] ?? '') !== '1') {
            $this->stamp(
                $img, $downloaded, $matches,
                redIdx: 32, defaultRed: 255,
                greenIdx: 34, defaultGreen: 0,
                blueIdx: 36, defaultBlue: 0,
                sizeIdx: 38, defaultSize: 3,
                xIdx: 40, defaultX: 180,
                yIdx: 42, defaultY: 3,
            );
        }

        imagesavealpha($img, true);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes !== '' ? $bytes : null;
    }

    /**
     * @param  resource|\GdImage  $img
     * @param  array<int,string>  $matches
     */
    private function stamp(
        $img,
        string $text,
        array $matches,
        int $redIdx,
        int $defaultRed,
        int $greenIdx,
        int $defaultGreen,
        int $blueIdx,
        int $defaultBlue,
        int $sizeIdx,
        int $defaultSize,
        int $xIdx,
        int $defaultX,
        int $yIdx,
        int $defaultY,
    ): void {
        $red = self::intInRange($matches, $redIdx, $defaultRed, 0, 255);
        $green = self::intInRange($matches, $greenIdx, $defaultGreen, 0, 255);
        $blue = self::intInRange($matches, $blueIdx, $defaultBlue, 0, 255);
        $size = self::intInRange($matches, $sizeIdx, $defaultSize, 1, 5);
        $x = self::intInRange($matches, $xIdx, $defaultX, 0, 350);
        $y = self::intInRange($matches, $yIdx, $defaultY, 0, 19);

        $colour = imagecolorallocate($img, $red, $green, $blue);
        imagestring($img, $size, $x, $y, $text, $colour);
    }

    /**
     * @param  array<int,string>  $matches
     */
    private static function intInRange(array $matches, int $index, int $default, int $min, int $max): int
    {
        $raw = $matches[$index] ?? '';
        if ($raw === '' || ! is_numeric($raw)) {
            return $default;
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }

    private function emptyResponse(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function pngResponse(string $payload): Response
    {
        return new Response($payload, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
        ]);
    }

    private function cacheGet(string $key): ?string
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'get_value')) {
            $value = $cache->get_value($key);

            return is_string($value) && $value !== '' ? $value : null;
        }
        $value = NexusDB::cache_get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function cachePut(string $key, string $value): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'cache_value')) {
            $cache->cache_value($key, $value, self::CACHE_TTL_SECONDS);

            return;
        }
        NexusDB::cache_put($key, $value, self::CACHE_TTL_SECONDS);
    }
}
