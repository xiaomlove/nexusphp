<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = 'tg-webhook';

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX.'/*',
        'web/token/*',
        // Phase 2 legacy migration: legacy XHR / form helpers post to
        // these URLs without sending a CSRF token (see
        // `public/js/common.js#saythanks`, the comment-edit "Preview"
        // button, and the staff Reports admin form). Adding CSRF
        // plumbing to every legacy form helper is a separate, larger
        // change; until then the migrated controllers keep the same
        // auth-required-but-CSRF-exempt posture they had as legacy
        // PHP-FPM scripts.
        'thanks.php',
        'preview.php',
        'takecontact.php',
        'takestaffmess.php',
        'takeupdate.php',
        // Phase 2 batch #2: `/logout.php` is `Route::any(...)` so POST
        // callers (some legacy XHRs) reach it without a CSRF token.
        // The other batch #2 endpoints are GET-only and not listed.
        'logout.php',
        // Phase 2 batch #4: `/clearcache.php` accepts POST without a
        // CSRF token (the legacy moderator form has no `@csrf` field).
        // `/smilies.php` and `/allagents.php` are GET-only.
        'clearcache.php',
        // Phase 2 batch #5: `/donated.php` accepts POST without a
        // CSRF token (the legacy sysop form has no `@csrf` field).
        // `/contactstaff.php` and `/takeflush.php` are GET-only.
        'donated.php',
        // Phase 2 batch #6: `/bannedemails.php`, `/allowedemails.php`,
        // and `/nowarn.php` accept POST without a CSRF token (their
        // legacy admin/moderator forms have no `@csrf` field).
        'bannedemails.php',
        'allowedemails.php',
        'nowarn.php',
        // Phase 2 batch #7: `/freeleech.php`, `/deletedisabled.php`,
        // `/delacctadmin.php` accept POST without a CSRF token.
        'freeleech.php',
        'deletedisabled.php',
        'delacctadmin.php',
        // Phase 2 batch #9: `/mailtest.php` and `/adduser.php` accept
        // POST without a CSRF token (their legacy sysop/admin forms
        // have no `@csrf` field). `/donorlist.php` is GET-only.
        'mailtest.php',
        'adduser.php',
        // Phase 3 magic.php rewrite: `/magic.php` accepts POST
        // without a CSRF token — the legacy XHR helper
        // `saveMagicValue` in `public/js/common.js` posts a bare
        // `application/x-www-form-urlencoded` payload, same as
        // `saythanks`. Bolting CSRF onto the existing JS helpers is
        // a separate, larger change tracked in the Phase 1 sweep.
        'magic.php',
        // Phase 2 batch #11: `/takeconfirm.php` accepts POST without
        // a CSRF token (the legacy `<form method=post>` in
        // `public/invite.php:173` / `public/checkuser.php:59` has
        // no `@csrf` field). `/user-ban-log.php` and
        // `/takereseed.php` are GET-only.
        'takeconfirm.php',
        // Phase 2 testip rewrite: `/testip.php` accepts POST without
        // a CSRF token — the legacy `<form method=post action=testip.php>`
        // in `public/testip.php` (deleted in this PR) had no `@csrf`
        // field, and links from `public/usersearch.php` /
        // ModpanelTableSeeder use `?ip=<addr>` GET. Accepting both
        // verbs keeps existing UI flows working without a template
        // change.
        'testip.php',
        'reset.php',
        'bans.php',
        // Phase 2 self-enable rewrite: `/self-enable.php` accepts POST
        // without a CSRF token — the legacy `<form method="post">` in
        // `public/self-enable.php` (deleted in this PR) had no `@csrf`
        // field. The page is the redirect target of
        // `include/functions.php:3169` for `enabled='no'` users, so
        // disabled accounts must be able to submit without going
        // through a token-mint round trip first.
        'self-enable.php',
        'cheaterbox.php',
        // Phase 2: `/massmail.php` accepts POST without a CSRF token —
        // the legacy `<form method=post action=massmail.php>` had no
        // `@csrf` field.
        'massmail.php',
    ];
}
