<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/allowedemails.php` (deleted in the same PR).
 *
 * Phase 2 batch #6 of the legacy migration — mirror of
 * `BannedEmailsController` for the `allowedemails` table (the
 * registration whitelist).
 *
 * Original legacy flow + replacement contract are identical to
 * `BannedEmailsController` except the table name and the helper
 * text — see that controller's docblock for the canonical
 * description.
 */
class AllowedEmailsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $action = (string) $request->input(
            'action',
            $request->query('action', 'showlist'),
        );

        if ($action === 'savelist' && $request->isMethod('POST')) {
            $value = trim(htmlspecialchars((string) $request->input('value', '')));
            NexusDB::table('allowedemails')->update(['value' => $value]);

            return new Response($this->wrap('Save List', '<p>Saved.</p>'));
        }

        return new Response($this->wrap('Show List', $this->renderForm()));
    }

    private function renderForm(): string
    {
        $row = NexusDB::table('allowedemails')->first();
        $current = htmlspecialchars(
            (string) (($row ? (array) $row : ['value' => ''])['value'] ?? '')
        );

        return '<table border="1" cellspacing="0" cellpadding="5" width="737">'."\n"
            .'<form method="post" action="allowedemails.php">'."\n"
            .'<input type="hidden" name="action" value="savelist">'."\n"
            .'<tr><td>Enter a list of allowed email addresses (separated by spaces):<br />'
            .'To allow a specific address enter "email@domain.com", to allow an entire domain enter "@domain.com"</td>'."\n"
            .'<td><textarea name="value" rows="5" cols="40">'.$current.'</textarea>'."\n"
            .'<input type="submit" value="save"></td></tr>'."\n"
            .'</form>'."\n"
            .'</table>'."\n";
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
