<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use App\Models\User;
use App\Repositories\SearchRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Phase 2 replacement for `public/fastdelete.php` (deleted in the
 * same PR). Two-step GET-delete: `?id=<n>` renders a confirmation
 * notice; `?id=<n>&sure=1` performs the delete and 302s to
 * `/torrents.php`. URL/method unchanged so the action link in
 * `include/functions.php:3971` keeps working.
 */
class FastDeleteController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('torrentmanage', false) || ! user_can('torrent-delete', false)) {
            abort(403);
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return $this->renderError('missing form data');
        }

        $row = NexusDB::table('torrents')
            ->where('id', $id)
            ->select(['id', 'name', 'owner', 'anonymous'])
            ->first();
        if ($row === null) {
            return new Response('', 200);
        }
        $row = (array) $row;

        if ($request->query('sure') !== '1') {
            return $this->renderConfirmation($id);
        }

        $searchRep = new SearchRepository;
        if ($searchRep->deleteTorrent($id) === false) {
            return $this->renderError('Delete es fail.');
        }
        deletetorrent($id);

        $uploadtorrentBonus = $GLOBALS['uploadtorrent_bonus'] ?? 0;
        KPS('-', $uploadtorrentBonus, (int) $row['owner']);

        $name = (string) $row['name'];
        $ownerId = (int) $row['owner'];
        $isAnonymousSelf = $row['anonymous'] === 'yes' && (int) $user->id === $ownerId;
        if ($isAnonymousSelf) {
            write_log("Torrent {$id} ({$name}) was deleted by its anonymous uploader", 'normal');
        } else {
            write_log("Torrent {$id} ({$name}) was deleted by {$user->username}", 'normal');
        }

        if ((int) $user->id !== $ownerId && User::query()->where('id', $ownerId)->exists()) {
            $locale = get_user_locale($ownerId);
            $subject = nexus_trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = nexus_trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$name
                .nexus_trans('torrent.msg_was_deleted_by', ['admin' => $user->username], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $ownerId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => date('Y-m-d H:i:s'),
            ]);
        }

        return new RedirectResponse('/torrents.php');
    }

    private function renderError(string $message): Response
    {
        $body = '<h2 align="center">'.htmlspecialchars('Delete failed!').'</h2>'."\n"
            .'<p align="center">'.htmlspecialchars($message).'</p>'."\n";

        return $this->renderEnvelope('Delete failed', $body);
    }

    private function renderConfirmation(int $id): Response
    {
        $link = 'fastdelete.php?id='.$id.'&amp;sure=1';
        $body = '<h2 align="center">'.htmlspecialchars('Delete torrent').'</h2>'."\n"
            .'<p align="center">'.htmlspecialchars('Sanity check: You are about to delete a torrent. Click')
            .' <a class="altlink" href="'.$link.'">'.htmlspecialchars('here').'</a> '
            .htmlspecialchars('if you are sure.').'</p>'."\n";

        return $this->renderEnvelope('Delete torrent', $body);
    }

    private function renderEnvelope(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);
        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
