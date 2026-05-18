<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/checkuser.php` (deleted in the same PR).
 *
 * Renders details of a single pending account and a one-row
 * "confirm this user" form posting to `/takeconfirm.php` (already
 * migrated — see `TakeConfirmController`). The legacy script let
 * the inviter (`users.invited_by == $CURUSER['id']`) reach the
 * page; moderators+ bypass that check. Everyone else gets a 403.
 *
 * The legacy chrome (`stdhead()`, country flag image, gender icon)
 * is dropped — country and gender are rendered as plain text under
 * the chrome-less envelope, matching the rest of Phase 2.
 */
class CheckUserController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return new Response($this->wrap(
                'Error',
                $this->notice('Error', 'Invalid user id.'),
            ), 422);
        }

        $pendingObj = NexusDB::table('users')
            ->where('status', User::STATUS_PENDING)
            ->where('id', $id)
            ->first();

        if (! $pendingObj) {
            return new Response($this->wrap(
                'Error',
                $this->notice('Error', 'No such user.'),
            ), 404);
        }

        $pending = (array) $pendingObj;
        $isModerator = (int) $user->class >= (int) User::CLASS_MODERATOR;
        $isInviter = (int) ($pending['invited_by'] ?? 0) === (int) $user->id;
        if (! $isModerator && ! $isInviter) {
            abort(403);
        }

        return new Response($this->wrap(
            'Detail for '.(string) ($pending['username'] ?? ''),
            $this->renderDetails($pending, $isModerator),
        ));
    }

    /**
     * @param  array<string,mixed>  $pending
     */
    private function renderDetails(array $pending, bool $isModerator): string
    {
        $id = (int) ($pending['id'] ?? 0);
        $usernameEsc = htmlspecialchars((string) ($pending['username'] ?? ''));
        $emailEsc = htmlspecialchars((string) ($pending['email'] ?? ''));
        $emailRaw = (string) ($pending['email'] ?? '');
        $genderEsc = htmlspecialchars((string) ($pending['gender'] ?? 'N/A'));
        $added = (string) ($pending['added'] ?? '');
        $joinedEsc = htmlspecialchars(
            ($added === '' || $added === '0000-00-00 00:00:00') ? 'N/A' : $added,
        );
        $enabled = (string) ($pending['enabled'] ?? '') === 'yes';

        $body = '<h1>'.$usernameEsc.'</h1>'."\n";
        if (! $enabled) {
            $body .= '<p>This account is disabled.</p>'."\n";
        }

        $body .= '<table width="737" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead" width="1%">Join Date</td>'
            .'<td align="left" width="99%">'.$joinedEsc.'</td></tr>'."\n"
            .'<tr><td class="rowhead" width="1%">Gender</td>'
            .'<td align="left" width="99%">'.$genderEsc.'</td></tr>'."\n"
            .'<tr><td class="rowhead" width="1%">e-Mail</td>'
            .'<td align="left" width="99%">'
            .'<a href="mailto:'.$emailEsc.'">'.$emailEsc.'</a>'
            .'</td></tr>'."\n";

        if ($isModerator) {
            $ipEsc = htmlspecialchars((string) ($pending['ip'] ?? ''));
            if ($ipEsc !== '') {
                $body .= '<tr><td class="rowhead" width="1%">IP</td>'
                    .'<td align="left" width="99%">'.$ipEsc.'</td></tr>'."\n";
            }
        }

        $body .= '<form method="post" action="takeconfirm.php?id='.$id.'">'
            .'<input type="hidden" name="email" value="'.$emailEsc.'">'
            .'<tr><td class="rowhead" width="1%">'
            .'<input type="checkbox" name="conusr[]" value="'.$id.'" checked></td>'
            .'<td align="left" width="99%">'
            .'<input type="submit" style="height: 20px" value="Confirm this user">'
            .'</td></tr></form>'."\n"
            .'</table>'."\n";

        unset($emailRaw);

        return $body;
    }

    private function notice(string $title, string $message): string
    {
        return '<h2 align="center">'.htmlspecialchars($title).'</h2>'."\n"
            .'<p align="center">'.htmlspecialchars($message).'</p>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title);

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
