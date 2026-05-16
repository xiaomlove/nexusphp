<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/ok.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * and `docs/migration-recipe.md`. `ok.php` is the generic "we are
 * done" message page that the signup pipeline lands users on:
 *
 *   - `takesignup.php` → `/ok.php?type=adminactivate`
 *   - `takesignup.php` → `/ok.php?type=inviter`
 *   - `takesignup.php` → `/ok.php?type=signup&email=<user@host>`
 *   - `confirm_resend.php` → `/ok.php?type=signup&email=<...>`
 *   - `ConfirmController` (Phase 2) → `/ok.php?type=confirmed`
 *   - `ConfirmController` (Phase 2) → `/ok.php?type=confirm`
 *   - Admin sysop activation → `/ok.php?type=sysop`
 *
 * Original legacy flow:
 *
 *   1. `dbconn();` + `require_once get_langfile_path();` bootstrap.
 *      The script ran without `loggedinorreturn()`, so guests can
 *      hit it directly (which is essential — the `signup` /
 *      `confirm` flows redirect here while the user is still
 *      unauthenticated).
 *   2. `mkglobal('type')` or `die()` (404-ish, empty body).
 *   3. Per-`type` `stdhead(...)` + `stdmsg(...)` / `print(...)`
 *      branches that emit the localised copy from
 *      `lang/<folder>/lang_ok.php`.
 *
 * Replacement contract (this controller):
 *
 *   - Same URL (`GET /ok.php`), reachable as a guest.
 *   - Same set of `type` values; unknown / missing types → 404 so
 *     the endpoint does not double as a UA-discoverable scratch
 *     pad.
 *   - The `type=signup` branch still requires `?email=<address>`
 *     and HTML-escapes the value verbatim.
 *   - `type=sysop` / `type=confirmed` / `type=confirm` toggle
 *     auto-login vs. cookies-disabled copy based on whether the
 *     request is authenticated — exactly the legacy `isset($CURUSER)`
 *     test, just routed through Laravel's auth guard.
 *   - Localised copy is read from `lang/<folder>/lang_ok.php`,
 *     where `<folder>` comes from the `c_lang_folder` cookie and
 *     falls back to English. Missing translation keys also fall
 *     back to English so half-translated locales never blow up
 *     the page with undefined-index notices.
 *   - Drops the `stdhead()` / `stdfoot()` chrome the same way every
 *     other Phase 2 controller does (see `RulesController` /
 *     `AboutNexusController`) — the legacy chrome relies on
 *     top-level-script globals that Laravel-pipeline controllers
 *     cannot expose. The chromeless envelope keeps the `<title>`
 *     so existing browser tabs and any external link-preview tooling
 *     keep something readable.
 */
class OkController extends Controller
{
    /** `language` folder used as the final fallback. */
    private const ENGLISH_LANGUAGE_FOLDER = 'en';

    /**
     * Map of accepted `?type=...` values to a tiny render-spec
     * dictionary. Keeping the dispatch table explicit makes it
     * trivial to test each branch in isolation and rules out a
     * whole class of "did you forget to handle X" regressions
     * the original `if / elseif / elseif / ... / else die();`
     * chain was prone to.
     *
     * @var array<string,array{title:string,body:string[],
     *                          requires_email?:bool, login_aware?:bool,
     *                          read_rules_faq?:bool}>
     */
    private const TYPES = [
        'adminactivate' => [
            'title' => 'head_user_signup',
            'body' => ['std_account_activated', 'account_activated_note'],
        ],
        'inviter' => [
            'title' => 'head_user_signup',
            'body' => ['std_account_activated', 'account_activated_note_two'],
        ],
        'signup' => [
            'title' => 'head_user_signup',
            'body' => [
                'std_signup_successful',
                'std_confirmation_email_note',
                'EMAIL',
                'std_confirmation_email_note_end',
            ],
            'requires_email' => true,
        ],
        'sysop' => [
            'title' => 'head_sysop_activation',
            'body' => ['std_sysop_activation_note', 'LOGIN_HINT'],
            'login_aware' => true,
        ],
        'confirmed' => [
            'title' => 'head_already_confirmed',
            'body' => ['std_already_confirmed', 'std_already_confirmed_note'],
        ],
        'confirm' => [
            'title' => 'head_signup_confirmation',
            'body' => ['std_account_confirmed', 'LOGIN_HINT', 'READ_RULES_FAQ'],
            'login_aware' => true,
            'read_rules_faq' => true,
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $type = (string) $request->query('type', '');
        if (! array_key_exists($type, self::TYPES)) {
            abort(404);
        }

        $spec = self::TYPES[$type];

        $email = '';
        if (! empty($spec['requires_email'])) {
            $email = trim((string) $request->query('email', ''));
            if ($email === '') {
                abort(404);
            }
        }

        $labels = $this->loadTranslations($this->resolveLanguageFolder(
            $request->cookie('c_lang_folder'),
        ));

        $title = (string) ($labels[$spec['title']] ?? '');
        $loggedIn = $request->user('nexus-web') !== null;

        $bodyParts = [];
        $headingEmitted = false;
        foreach ($spec['body'] as $token) {
            // Synthetic tokens — the dispatch table uses these to
            // splice in dynamic values without having to know the
            // legacy key names at the call site.
            if ($token === 'EMAIL') {
                $bodyParts[] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

                continue;
            }
            if ($token === 'LOGIN_HINT') {
                $bodyParts[] = $loggedIn
                    ? (string) ($labels['std_auto_logged_in_note'] ?? '')
                    : (string) ($labels['std_cookies_disabled_note'] ?? '');

                continue;
            }
            if ($token === 'READ_RULES_FAQ') {
                $template = (string) ($labels['std_read_rules_faq'] ?? '');
                if ($template !== '') {
                    $bodyParts[] = sprintf($template, (string) Setting::getSiteName());
                }

                continue;
            }
            $copy = (string) ($labels[$token] ?? '');
            if ($copy === '') {
                continue;
            }
            // The legacy `stdmsg()` renders the first string as an
            // `<h2>` heading and the second one as the framed body.
            // Mirror that with a literal `<h2>` so the rendered page
            // looks reasonable even without the legacy chrome.
            if (! $headingEmitted && ! str_starts_with($copy, '<')) {
                $bodyParts[] = '<h2>'.$copy.'</h2>';
                $headingEmitted = true;
            } else {
                $bodyParts[] = $copy;
            }
        }

        $body = implode("\n", $bodyParts);
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Pick the language folder we should localise the copy in.
     * Mirrors `AboutNexusService::resolveLanguageFolder()`: prefer
     * the `c_lang_folder` cookie, fall back to English. Anything
     * non-alphanumeric or pointing at a non-existent
     * `lang_ok.php` file is rejected.
     */
    private function resolveLanguageFolder(?string $cookieValue): string
    {
        $cookieValue = trim((string) ($cookieValue ?? ''));
        if ($cookieValue === '') {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $cookieValue) !== 1) {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }
        $candidate = base_path('lang'.DIRECTORY_SEPARATOR.$cookieValue.DIRECTORY_SEPARATOR.'lang_ok.php');
        if (! is_file($candidate)) {
            return self::ENGLISH_LANGUAGE_FOLDER;
        }

        return $cookieValue;
    }

    /**
     * Load the `$lang_ok` translation array for the given folder
     * (or English if the folder lookup fails) and overlay it on top
     * of the English defaults so missing keys don't blow up the
     * page.
     *
     * @return array<string,string>
     */
    private function loadTranslations(string $folder): array
    {
        $defaults = $this->readTranslationFile(self::ENGLISH_LANGUAGE_FOLDER);
        if ($folder === self::ENGLISH_LANGUAGE_FOLDER) {
            return $defaults;
        }

        return array_merge($defaults, $this->readTranslationFile($folder));
    }

    /**
     * Read `lang/<folder>/lang_ok.php` in an isolated closure scope
     * so the `$lang_ok` declaration doesn't leak into the caller.
     *
     * @return array<string,string>
     */
    private function readTranslationFile(string $folder): array
    {
        $path = base_path('lang'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'lang_ok.php');
        if (! is_file($path)) {
            return [];
        }

        $loaded = (static function (string $file): array {
            /** @var array<string,string>|null $lang_ok */
            $lang_ok = null;
            require $file;
            if (! is_array($lang_ok)) {
                return [];
            }

            return $lang_ok;
        })($path);

        return $loaded;
    }
}
