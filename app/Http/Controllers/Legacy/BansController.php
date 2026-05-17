<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class BansController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        if ($request->isMethod('post')) {
            return $this->handleAdd($request, $user);
        }

        $remove = (int) $request->query('remove', 0);
        if ($remove > 0 && is_valid_id($remove)) {
            NexusDB::table('bans')->where('id', $remove)->delete();
            write_log(
                'Ban '.$remove.' was removed by '.(int) $user->id.' ('.$user->username.')',
                'mod',
            );

            return redirect('/bans.php');
        }

        return $this->renderListing($user);
    }

    private function handleAdd(Request $request, User $user): Response|RedirectResponse
    {
        $first = trim((string) $request->input('first', ''));
        $last = trim((string) $request->input('last', ''));
        $comment = trim((string) $request->input('comment', ''));

        if ($first === '' || $last === '' || $comment === '') {
            return $this->renderError('Missing form data.');
        }

        if (
            filter_var($first, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($last, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
        ) {
            return $this->renderError('Bad IP address.');
        }

        $firstLong = ip2long($first);
        $lastLong = ip2long($last);
        if ($firstLong === false || $lastLong === false) {
            return $this->renderError('Bad IP address.');
        }

        NexusDB::insert('bans', [
            'added' => date('Y-m-d H:i:s'),
            'addedby' => (int) $user->id,
            'first' => (int) $firstLong,
            'last' => (int) $lastLong,
            'comment' => $comment,
        ]);

        return redirect('/bans.php');
    }

    private function renderListing(User $user): Response
    {
        $banRows = NexusDB::table('bans')->orderByDesc('added')->get();

        $body = '<h1>Current Bans</h1>'."\n";

        if ($banRows->count() === 0) {
            $body .= '<p align="center"><b>Nothing found</b></p>'."\n";
        } else {
            $body .= '<table border="1" cellspacing="0" cellpadding="5">'."\n"
                .'<tr>'
                .'<td class="colhead">Added</td>'
                .'<td class="colhead" align="left">First IP</td>'
                .'<td class="colhead" align="left">Last IP</td>'
                .'<td class="colhead" align="left">By</td>'
                .'<td class="colhead" align="left">Comment</td>'
                .'<td class="colhead">Remove</td>'
                .'</tr>'."\n";

            foreach ($banRows as $row) {
                $arr = (array) $row;
                $added = htmlspecialchars((string) gettime((string) ($arr['added'] ?? '')));
                $firstIp = htmlspecialchars((string) long2ip((int) ($arr['first'] ?? 0)));
                $lastIp = htmlspecialchars((string) long2ip((int) ($arr['last'] ?? 0)));
                $by = get_username((int) ($arr['addedby'] ?? 0));
                $comment = htmlspecialchars((string) ($arr['comment'] ?? ''));
                $id = (int) ($arr['id'] ?? 0);

                $body .= '<tr>'
                    .'<td>'.$added.'</td>'
                    .'<td align="left">'.$firstIp.'</td>'
                    .'<td align="left">'.$lastIp.'</td>'
                    .'<td align="left">'.$by.'</td>'
                    .'<td align="left">'.$comment.'</td>'
                    .'<td><a href="bans.php?remove='.$id.'">Remove</a></td>'
                    .'</tr>'."\n";
            }

            $body .= '</table>'."\n";
        }

        $body .= '<h1>Add ban</h1>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<form method="post" action="bans.php">'."\n"
            .'<tr><td class="rowhead">First IP</td>'
            .'<td><input type="text" name="first" size="40"></td></tr>'."\n"
            .'<tr><td class="rowhead">Last IP</td>'
            .'<td><input type="text" name="last" size="40"></td></tr>'."\n"
            .'<tr><td class="rowhead">Comment</td>'
            .'<td><input type="text" name="comment" size="40"></td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="Okay" class="btn"></td></tr>'."\n"
            .'</form>'."\n"
            .'</table>'."\n";

        return $this->render('Bans', $body);
    }

    private function renderError(string $message): Response
    {
        return $this->render('Error', '<h2 align="center">Error</h2>'."\n"
            .'<p align="center"><font class="striking">'
            .htmlspecialchars($message).'</font></p>'."\n");
    }

    private function render(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);

        return new Response(<<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML);
    }
}
