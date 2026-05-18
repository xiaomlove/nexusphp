<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class StaffPanelController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        $class = (int) $user->class;
        if ($class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $body = '<h1 align="center">Administration</h1>'."\n";

        if ($class >= (int) User::CLASS_SYSOP) {
            $body .= $this->renderSection('For SysOp Only', 'sysoppanel');
        }
        if ($class >= (int) User::CLASS_ADMINISTRATOR) {
            $body .= $this->renderSection('For Administrator Only', 'adminpanel');
        }
        if ($class >= (int) User::CLASS_MODERATOR) {
            $body .= $this->renderSection('For Moderator Only', 'modpanel');
        }

        return $this->render('Administration', $body);
    }

    private function renderSection(string $title, string $table): string
    {
        $rows = NexusDB::table($table)->orderBy('id')->get(['name', 'url', 'info']);

        $html = '<h1 align="center">..:: '.htmlspecialchars($title).' ::..</h1>'."\n"
            .'<br /><br />'
            .'<table width="80%" border="1" cellspacing="0" cellpadding="5" align="center">'."\n"
            .'<tr>'
            .'<td class="colhead" align="left">Option Name</td>'
            .'<td class="colhead" align="left">Info</td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $name = htmlspecialchars((string) ($arr['name'] ?? ''));
            $url = htmlspecialchars((string) ($arr['url'] ?? ''));
            $info = htmlspecialchars((string) ($arr['info'] ?? ''));

            $html .= '<tr>'
                .'<td class="rowfollow" align="left"><strong><a href="'.$url.'">'.$name.'</a></strong></td>'
                .'<td class="rowfollow" align="left">'.$info.'</td>'
                .'</tr>'."\n";
        }

        $html .= '</table>'."\n".'<br /><br />';

        return $html;
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
