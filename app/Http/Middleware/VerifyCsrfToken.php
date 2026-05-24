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
        // Phase 2 massmail rewrite: `/massmail.php` accepts POST
        // without a CSRF token — the legacy
        // `<form method=post action=massmail.php>` self-submit had
        // no `@csrf` field. Fan-out runs in `App\Jobs\SendMassMail`.
        'massmail.php',
        // Phase 2 torrent lifecycle (this PR): takeupload.php and
        // takeedit.php POSTs accept the legacy multipart upload/edit
        // forms which had no `@csrf` field. The corresponding GET
        // form renderers (upload.php / edit.php) are GET-only.
        'takeupload.php',
        'takeedit.php',
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
        // Phase 2 (this PR — auth-flow batch part 1 of 3):
        // `/takelogin.php` accepts POST without a CSRF token —
        // the legacy `<form action="takelogin.php">` has no
        // `@csrf` field, and the existing challenge-response JS
        // in `public/js/common.js` does not include a token.
        // Adding CSRF plumbing to the login form is a separate,
        // larger change (touches OAuth callback + Passkey login
        // + the JS challenge-response wiring). The
        // `App\Http\Controllers\Legacy\TakeLoginController`
        // re-implements the legacy `failedlogins()` IP-counter
        // side effect so brute-forcers still get rate-limited.
        'takelogin.php',
        // Phase 2 (this PR — auth-flow batch part 3 of 3):
        // `/maxlogin.php` accepts POST without a CSRF token —
        // the legacy `<form action="maxlogin.php">` edit and
        // search forms have no `@csrf` field. The endpoint sits
        // behind `auth.nexus:nexus-web` and the controller
        // gate-checks `User::CLASS_SYSOP` so unauthenticated /
        // non-admin callers can't reach the write path.
        'maxlogin.php',
        'takeconfirm.php',
        // Phase 2 (this PR): `/takeinvite.php` accepts POST without a
        // CSRF token — the legacy `<form action=takeinvite.php>` in
        // `InviteController::renderNewForm()` has no `@csrf` field.
        // The form-render endpoint `/invite.php` is GET-only, so it
        // doesn't need an entry here.
        'takeinvite.php',
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
        // Phase 2 bitbucket-upload rewrite: `/bitbucket-upload.php`
        // accepts POST without a CSRF token — the legacy
        // `<form method="post" action="bitbucket-upload.php"
        // enctype="multipart/form-data">` had no `@csrf` field. The
        // form is the only caller (it self-submits), and adding
        // CSRF plumbing to legacy multipart forms is a separate
        // larger change.
        'bitbucket-upload.php',
        // Phase 2 increment-bulk rewrite: `/take-increment-bulk.php`
        // accepts POST without a CSRF token — the legacy
        // `<form method=post action=take-increment-bulk.php>` in
        // `public/increment-bulk.php` (deleted in this PR) had no
        // `@csrf` field. The form-render endpoint
        // `/increment-bulk.php` is GET-only, so it doesn't need an
        // entry here.
        'take-increment-bulk.php',
        // Phase 2 fields rewrite: `/fields.php` accepts POST without
        // a CSRF token. The legacy `<form method=post action=fields.php?action=submit>`
        // (rendered by `Nexus\Field\Field::buildFieldForm`) has no
        // `@csrf` field and the `?action=submit` handler has been a
        // dead-deprecation message since 1.10 — preserving the URL
        // means existing rendered forms still produce the same 200
        // response without 419'ing.
        'fields.php',
        // Phase 2 modrules rewrite: `/modrules.php` accepts POST
        // without a CSRF token — the legacy `<form method="post"
        // action="modrules.php?act=addsect">` and
        // `<form method="post" action="modrules.php?act=edited">`
        // forms had no `@csrf` field.
        'modrules.php',
        // Phase 2 attendance rewrite: `/attendance.php` accepts POST
        // without a CSRF token — the legacy
        // `<form method="post" action="attendance.php">` in
        // `public/attendance.php` (deleted in this PR) had no
        // `@csrf` field. The optional inline image captcha is the
        // anti-spam measure for this endpoint; see
        // `AttendanceController::verifyCaptcha`.
        'attendance.php',
        // Phase 2 batch (PR #293, linksmanage/makepoll/reports/ipsearch/staff):
        // `linksmanage.php` and `makepoll.php` accept POST without a
        // CSRF token — the legacy forms in `public/linksmanage.php`
        // (the `?action=apply`/`?action=newapply`/`?action=add`/
        // `?action=editlink` forms) and `public/makepoll.php` (the
        // self-submitting poll create/edit form) have no `@csrf`
        // field. The other three pages in the batch are GET-only
        // and don't need an entry here.
        'linksmanage.php',
        'makepoll.php',
        // Phase 2 batch B (task/downloadnotice/search/cc98bar/
        // takemessage/friends/download): two POST endpoints in the
        // batch accept POST without a CSRF token — the legacy
        // `<form method="post" action="downloadnotice.php">`
        // self-submit in `public/downloadnotice.php` and the
        // `<form action="takemessage.php">` rendered by
        // `SendMessageController` and `public/messages.php` both
        // omit the `@csrf` field. The other five endpoints in this
        // batch are GET-only and don't need an entry here.
        'downloadnotice.php',
        'takemessage.php',
        // Phase 2 complains/report rewrite: both legacy forms posted
        // without a CSRF token.
        //
        // `/complains.php` accepts POST `action=new` (anonymous
        // submission with image captcha — guests are intentionally
        // allowed because the feature exists for users whose accounts
        // have been disabled), `action=reply` (anyone), and
        // `action=answered`/`unanswered` (staff-only, gated inside
        // the controller). Adding CSRF plumbing to a guest-reachable
        // form is a separate change.
        //
        // `/report.php` accepts the seven `take<type>` POST verbs
        // (one per `reports.type` enum value); the legacy
        // confirmation form rendered inside the `stderr()` envelope
        // had no `@csrf` field.
        'complains.php',
        'report.php',
        // Phase 2 batch — `moforums.php` and `staffbox.php` accept POST
        // without a CSRF token — the legacy `<form method="post"
        // action="moforums.php">` and `<form method="post"
        // action="?action=takeanswer">` forms had no `@csrf` field.
        'moforums.php',
        'staffbox.php',
        // Phase 2 batch — `forummanage.php` (admin forum-manager
        // form-submit, addforum/editforum verbs) and `getrss.php`
        // (RSS-link builder form-submit) both accept POST without
        // a CSRF token — neither legacy form had a `@csrf` field.
        'forummanage.php',
        'getrss.php',
        // Phase 2.5 (this PR — batch C of the `public/ajax.php`
        // cleanup): the five new endpoints replace the claim /
        // hit-and-run / exam-task / benefit sub-APIs previously
        // dispatched through `public/ajax.php?action=...`. The
        // four first-party JS callers — the inline scripts in
        // `public/details.php:315`, `MyhrController:133`,
        // `TaskController:224`, `public/userdetails.php:286`, and
        // the shared `claimAction(...)` helper in
        // `public/js/nexus.js:140` — post bare
        // `application/x-www-form-urlencoded` bodies with no
        // `_token`. Bolting CSRF onto those legacy inline scripts
        // is a separate, larger change tracked in the Phase 1
        // sweep. See `routes/web.php` (the
        // `Route::prefix('claim')` block + the three single
        // `Route::post(...)` lines for hit-and-run/exam/benefit)
        // and `App\Http\Controllers\Legacy\ClaimAjaxController`.
        'claim/*',
        'hit-and-run/*',
        'exam/claim-task',
        'benefit/consume',
    ];
}
