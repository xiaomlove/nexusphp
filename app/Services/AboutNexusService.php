<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

/**
 * Typed, read-only view of the data that `public/aboutnexus.php` used
 * to assemble inline.
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". The original procedural script was a
 * single `aboutnexus.php` file that mixed three concerns: pulling the
 * `language` / `stylesheets` rows out of the DB, reading version
 * constants, and emitting HTML straight to STDOUT. This service owns
 * the first two; the Blade view at `resources/views/legacy/aboutnexus.blade.php`
 * owns the third.
 *
 * The view (and the controller it backs) are intentionally chrome-aware
 * via `view('layouts.legacy', ...)` — `aboutnexus.php` is reachable
 * from the global "Powered by NexusPHP" footer link, so a guest who
 * clicks it has to land on a fully styled page, not an envelope-less
 * fragment.
 */
class AboutNexusService
{
    /** `language.id` for English in the seeded `language` table. Final fallback. */
    private const ENGLISH_LANGUAGE_FOLDER = 'en';

    /** Filesystem path containing per-locale `lang_aboutnexus.php` files. */
    private readonly string $langRoot;

    public function __construct(?string $langRoot = null)
    {
        $this->langRoot = $langRoot ?? base_path('lang');
    }

    /**
     * Snapshot of the version constants the about page renders.
     *
     * The constants themselves live in `include/constants.php`, which
     * `bootstrap/app.php` requires unconditionally, so they are
     * available at every Laravel request boundary. We hoist them here
     * (rather than referencing the bare `PROJECTNAME` constant in the
     * view) so the view stays decoupled from the legacy include
     * symbol surface — Phase 5 can swap the include for a typed
     * config bag without touching the template.
     *
     * @return array{
     *     project_name: string,
     *     project_url: string,
     *     version_number: string,
     *     release_date: string,
     *     site_name: string,
     * }
     */
    public function versionInfo(): array
    {
        return [
            'project_name' => (string) (defined('PROJECTNAME') ? PROJECTNAME : 'NexusPHP'),
            'project_url' => (string) (defined('NEXUSPHPURL') ? NEXUSPHPURL : 'https://nexusphp.org'),
            'version_number' => (string) (defined('VERSION_NUMBER') ? VERSION_NUMBER : ''),
            'release_date' => (string) (defined('RELEASE_DATE') ? RELEASE_DATE : ''),
            'site_name' => Setting::getSiteName(),
        ];
    }

    /**
     * Pick the language folder the page should localise its labels in.
     *
     * Mirrors the legacy `get_langfile_path()` precedence: prefer the
     * `c_lang_folder` cookie, fall back to English. Out-of-tree folder
     * names are rejected (the controller never honours user input as a
     * path component) and fall through to English.
     *
     * Folder names from the `language` table are alphanumeric ASCII
     * (`en`, `chs`, `cht`, `ru`, …); anything else is treated as
     * untrusted input and dropped on the floor.
     */
    public function resolveLanguageFolder(?string $cookieValue): string
    {
        $cookieValue = trim((string) ($cookieValue ?? ''));
        if ($cookieValue === '') {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $cookieValue) !== 1) {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }
        $candidate = $this->langRoot.DIRECTORY_SEPARATOR.$cookieValue.DIRECTORY_SEPARATOR.'lang_aboutnexus.php';
        if (! is_file($candidate)) {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }

        return $cookieValue;
    }

    /**
     * Load the `$lang_aboutnexus` translation array for the given
     * folder (or English if the folder lookup fails). The PHP files
     * under `lang/<folder>/lang_aboutnexus.php` are pure data — they
     * declare `$lang_aboutnexus = array(...);` and nothing else — so
     * we can safely `require` them in an isolated closure scope.
     *
     * The returned map is overlaid on top of the English defaults so
     * that incomplete translations don't blow up the view with
     * undefined-index notices.
     *
     * @return array<string,string>
     */
    public function loadTranslations(?string $folder = null): array
    {
        $folder = $folder ?? self::ENGLISH_LANGUAGE_FOLDER;

        $defaults = $this->readTranslationFile(self::ENGLISH_LANGUAGE_FOLDER);
        if ($folder === self::ENGLISH_LANGUAGE_FOLDER) {
            return $defaults;
        }

        $localised = $this->readTranslationFile($folder);

        return array_merge($defaults, $localised);
    }

    /**
     * Rows for the `<table>` of translated locales the original page
     * rendered, sorted (case-insensitively) by `trans_state` so
     * "up-to-date" wins before "outdate" etc. — exactly what the
     * legacy `ORDER BY trans_state` produced.
     *
     * The returned collection is shaped as `{id, lang_name, flagpic,
     * trans_state}` — strictly the columns the view binds to, so the
     * service can switch storage backends later without affecting the
     * template.
     *
     * @return Collection<int, array{id:int, lang_name:string, flagpic:string, trans_state:string}>
     */
    public function languages(): Collection
    {
        return NexusDB::table('language')
            ->orderBy('trans_state')
            ->get(['id', 'lang_name', 'flagpic', 'trans_state'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'lang_name' => (string) $row->lang_name,
                'flagpic' => (string) $row->flagpic,
                'trans_state' => (string) $row->trans_state,
            ])
            ->values();
    }

    /**
     * Rows for the `<table>` of available CSS stylesheets, sorted by
     * `id` (oldest first) — matches the legacy `ORDER BY id`.
     *
     * @return Collection<int, array{id:int, name:string, designer:string, comment:string}>
     */
    public function stylesheets(): Collection
    {
        return NexusDB::table('stylesheets')
            ->orderBy('id')
            ->get(['id', 'name', 'designer', 'comment'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'designer' => (string) $row->designer,
                'comment' => (string) $row->comment,
            ])
            ->values();
    }

    /**
     * Read a single per-locale `lang_aboutnexus.php` file into a flat
     * `array<string,string>`. Runs in an isolated closure so the
     * `$lang_aboutnexus` declaration cannot leak into the caller's
     * scope; returns an empty map if the file is missing or doesn't
     * declare the expected variable.
     *
     * @return array<string,string>
     */
    private function readTranslationFile(string $folder): array
    {
        $path = $this->langRoot.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'lang_aboutnexus.php';
        if (! is_file($path)) {
            return [];
        }

        $loaded = (static function (string $file): array {
            /** @var array<string,string>|null $lang_aboutnexus */
            $lang_aboutnexus = null;
            require $file;
            if (! is_array($lang_aboutnexus)) {
                return [];
            }

            return $lang_aboutnexus;
        })($path);

        $result = [];
        foreach ($loaded as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }
}
