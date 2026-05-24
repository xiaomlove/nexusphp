<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;

class FieldsController extends Controller
{
    private const DEPRECATION_MESSAGE = 'This method is deprecated! This method is no longer available in 1.10, it does not save data correctly, please go to the management system!';

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

        $this->loadLangfiles();
        $field = new Field;

        $action = (string) $request->query('action', 'view');

        return match ($action) {
            'view' => $this->renderView($field),
            'add' => $this->renderAdd($field),
            'submit' => new Response(self::DEPRECATION_MESSAGE, 410),
            'edit' => $this->renderEdit($field, (int) $request->query('id', 0)),
            'del' => $this->delete((int) $request->query('id', 0)),
            default => $this->renderView($field),
        };
    }

    private function renderView(Field $field): Response
    {
        return new Response($this->wrap('Custom field management - Field', $field->buildFieldTable()));
    }

    private function renderAdd(Field $field): Response
    {
        return new Response($this->wrap('Custom field management - Add', $field->buildFieldForm()));
    }

    private function renderEdit(Field $field, int $id): Response
    {
        if ($id <= 0) {
            return new Response('Invalid id', 422);
        }
        $row = NexusDB::table('torrents_custom_fields')->where('id', $id)->first();
        if ($row === null) {
            return new Response('Invalid id', 422);
        }

        return new Response($this->wrap('Custom field management - Edit', $field->buildFieldForm((array) $row)));
    }

    private function delete(int $id): Response|RedirectResponse
    {
        if ($id <= 0) {
            return new Response('Invalid id', 422);
        }
        NexusDB::table('torrents_custom_fields')->where('id', $id)->delete();

        return redirect('/fields.php?action=view');
    }

    private function loadLangfiles(): void
    {
        global $lang_fields, $lang_catmanage, $lang_functions;
        require_once base_path(get_langfile_path('fields.php'));
        require_once base_path(get_langfile_path('catmanage.php'));
        require_once base_path(get_langfile_path('functions.php'));
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
