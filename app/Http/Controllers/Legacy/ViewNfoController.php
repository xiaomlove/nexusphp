<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Support\Codec;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class ViewNfoController extends Controller
{
    private const ALLOWED_VIEWS = ['magic', 'latin-1', 'fonthack'];

    private const DEFAULT_VIEW = 'magic';

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403);
        }
        if (! user_can('viewnfo', false, (int) $user->id)) {
            abort(403);
        }

        $id = (int) $request->query('id', 0);
        if (! is_valid_id($id)) {
            abort(403);
        }

        if ((string) Setting::getByName('main.enablenfo') !== 'yes') {
            abort(403);
        }

        $row = NexusDB::table('torrents')
            ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
            ->where('torrents.id', $id)
            ->selectRaw('torrents.name, torrent_extras.nfo')
            ->first();
        if ($row === null) {
            return $this->render('View NFO File', '<p>Puke</p>');
        }
        $data = (array) $row;
        $name = (string) ($data['name'] ?? '');
        $nfoBytes = (string) ($data['nfo'] ?? '');

        $view = (string) $request->query('view', self::DEFAULT_VIEW);
        if (! in_array($view, self::ALLOWED_VIEWS, true)) {
            $view = self::DEFAULT_VIEW;
        }

        $rendered = Codec::ibm437ToEntities($nfoBytes, $view);

        $body = $this->renderBody($id, $name, $view, $rendered);

        return $this->render('View NFO File', $body);
    }

    private function renderBody(int $id, string $name, string $view, string $rendered): string
    {
        $nameEsc = htmlspecialchars($name);
        $magicUrl = 'viewnfo.php?id='.$id.'&amp;view=magic';
        $latinUrl = 'viewnfo.php?id='.$id.'&amp;view=latin-1';

        $pre = $view === 'fonthack'
            ? '<pre style="font-size:10pt; font-family: \'MS LineDraw\', \'Terminal\', monospace; white-space: break-spaces;">'
            : '<pre style="font-size:10pt; font-family: \'Courier New\', monospace; white-space: break-spaces;">';

        return 'NFO for <a href="details.php?id='.$id.'">'.$nameEsc.'</a>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="10" align="center">'."\n"
            .'<tr>'
            .'<td align="center" width="50%"><a href="'.$magicUrl.'" title="DOS view"><b>DOS view</b></a></td>'
            .'<td align="center" width="50%"><a href="'.$latinUrl.'" title="Windows view"><b>Windows view</b></a></td>'
            .'</tr>'."\n"
            .'<tr>'
            .'<td colspan="2">'
            .'<table border="1" cellspacing="0" cellpadding="5"><tr><td class="text">'
            .$pre
            .format_urls($rendered)
            .'</pre>'
            .'</td></tr></table>'
            .'</td>'
            .'</tr>'."\n"
            .'</table>'."\n";
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
