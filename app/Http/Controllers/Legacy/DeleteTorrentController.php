<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use App\Models\User;
use App\Repositories\SearchRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/delete.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. `user_can('torrent-delete', true)` — permission gate.
 *   3. Validates torrent exists, checks ownership or `torrentmanage`
 *      permission.
 *   4. Validates reason type (1-5) and builds reason string.
 *   5. Deletes from Elasticsearch, calls `deletetorrent($id)`.
 *   6. Logs the action, deducts karma from uploader.
 *   7. Sends PM to torrent owner if deleted by someone else.
 *   8. Renders a "Torrent Deleted" confirmation page.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Missing `torrent-delete` permission → `abort(403)`.
 *   - POST-only (the legacy form in `details.php` posts here).
 *   - Validates torrent existence, ownership, reason type.
 *   - Performs the same deletion sequence as legacy.
 *   - Returns chrome-less HTML confirmation page with back link.
 *   - CSRF-exempt (legacy form has no `@csrf` field).
 */
class DeleteTorrentController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        if (! user_can('torrent-delete')) {
            abort(403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->renderError('Missing form data.');
        }

        $row = NexusDB::table('torrents')
            ->where('id', $id)
            ->select(['name', 'owner', 'seeders', 'anonymous'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        $torrent = (array) $row;

        // Check ownership or torrentmanage permission
        if ((int) $user->id !== (int) $torrent['owner'] && ! user_can('torrentmanage')) {
            return $this->renderError('You are not the owner of this torrent.');
        }

        $rt = (int) $request->input('reasontype', 0);
        if ($rt < 1 || $rt > 5) {
            return $this->renderError("Invalid reason type: {$rt}.");
        }

        $reason = (array) $request->input('reason', []);
        $reasonstr = $this->buildReasonString($rt, $reason);
        if ($reasonstr === null) {
            return $this->renderError('Please describe what rule was violated or enter a reason.');
        }

        // Delete from Elasticsearch
        $searchRep = new SearchRepository;
        $deleteEsResult = $searchRep->deleteTorrent($id);
        if ($deleteEsResult === false) {
            return $this->renderError('Delete es fail.');
        }

        // Delete torrent
        deletetorrent($id);

        // Log
        $siteName = get_setting('basic.SITENAME');
        if ($torrent['anonymous'] === 'yes' && (int) $user->id === (int) $torrent['owner']) {
            write_log("Torrent {$id} ({$torrent['name']}) was deleted by its anonymous uploader ({$reasonstr})", 'normal');
        } else {
            write_log("Torrent {$id} ({$torrent['name']}) was deleted by {$user->username} ({$reasonstr})", 'normal');
        }

        // Remove karma
        $uploadtorrentBonus = get_setting('bonus.per_uploaded_torrent') ?: 0;
        KPS('-', $uploadtorrentBonus, $torrent['owner']);

        // Send PM to torrent owner
        if ((int) $user->id !== (int) $torrent['owner'] && User::where('id', (int) $torrent['owner'])->exists()) {
            $locale = get_user_locale((int) $torrent['owner']);
            $subject = nexus_trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = nexus_trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$torrent['name']
                .nexus_trans('torrent.msg_was_deleted_by', [], $locale)
                .'[url=userdetails.php?id='.$user->id.']'.$user->username.'[/url]'
                .nexus_trans('torrent.msg_reason_is', [], $locale)
                .$reasonstr;
            Message::add([
                'sender' => 0,
                'receiver' => (int) $torrent['owner'],
                'subject' => $subject,
                'msg' => $msg,
                'added' => date('Y-m-d H:i:s'),
            ]);
        }

        // Render success page
        $returnTo = $request->input('returnto', '');
        if ($returnTo !== '') {
            $ret = '<a href="'.htmlspecialchars($returnTo).'">Go back</a>';
        } else {
            $ret = '<a href="index.php">Back to index</a>';
        }

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Torrent Deleted</title>
</head>
<body>
<h1>Torrent Deleted</h1>
<p>{$ret}</p>
</body>
</html>
HTML;

        return new Response($html);
    }

    private function buildReasonString(int $rt, array $reason): ?string
    {
        return match ($rt) {
            1 => 'Dead: 0 seeders, 0 leechers = 0 peers total',
            2 => 'Dupe'.(! empty($reason[0]) ? (': '.trim($reason[0])) : '!'),
            3 => 'Nuked'.(! empty($reason[1]) ? (': '.trim($reason[1])) : '!'),
            4 => ! empty($reason[2])
                ? get_setting('basic.SITENAME').' rules broken: '.trim($reason[2])
                : null,
            5 => ! empty($reason[3])
                ? trim($reason[3])
                : null,
            default => null,
        };
    }

    private function renderError(string $message): Response
    {
        $messageEsc = htmlspecialchars($message);

        return new Response(<<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Delete Failed</title>
</head>
<body>
<h2>Delete Failed</h2>
<p>{$messageEsc}</p>
</body>
</html>
HTML);
    }
}
