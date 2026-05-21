<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/fields.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. Administrator+ tool
 * for managing rows in the `torrents_custom_fields` table — list,
 * add, edit, delete.
 *
 * Original legacy flow (`public/fields.php`, 55 LOC):
 *   1. `dbconn();` + `require_once(get_langfile_path());` for
 *      `lang_fields` and `lang_catmanage`, then `loggedinorreturn();`.
 *   2. `get_user_class() < UC_ADMINISTRATOR` → `permissiondenied();`.
 *   3. `?action=view`  (default) — `Field::buildFieldTable()`.
 *   4. `?action=add`   — `Field::buildFieldForm()` (empty).
 *   5. `?action=edit&id=N` — load row, `Field::buildFieldForm($row)`.
 *   6. `?action=del&id=N`  — DELETE row, `nexus_redirect('?action=view')`.
 *   7. `?action=submit` — was the form POST handler; since 1.10 the
 *      legacy script `exit()`s with a hard-coded deprecation message
 *      ("This method is no longer available in 1.10, ... please go
 *      to the management system!") and the unreachable `Field::save`
 *      branch below it was never touched again.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `/login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` →
 *     `abort(403)` (legacy `permissiondenied()` rendered HTTP 200
 *     `stderr()`; tightened, same as every other Phase 2 controller).
 *   - `?action=view` (default) — chrome-less listing of all rows,
 *     newest-by-priority first. The legacy `pager(10, ...)` is
 *     dropped: this admin page in practice has < 20 rows, so a
 *     full listing keeps the controller small and matches the
 *     real-world behaviour. Phase 5 (when chrome comes back) can
 *     re-introduce paging via `LegacyChrome` if ever needed.
 *   - `?action=add` — chrome-less HTML form, empty state. POSTs to
 *     `fields.php?action=submit` (the legacy deprecation pathway).
 *   - `?action=edit&id=N` — chrome-less HTML form pre-filled from
 *     the row. Missing/unknown id → "Invalid id" notice (200).
 *   - `?action=del&id=N`  — DELETE row, 302 to `?action=view`.
 *     Mirrors the legacy `nexus_redirect('fields.php?action=view')`.
 *     Verb stays GET because the legacy `confirm_delete()` JS in
 *     `public/js/common.js:10` does `self.location.href='?action=del...'`.
 *   - `?action=submit` — returns the legacy deprecation message
 *     verbatim, HTTP 200 plain-text envelope. The legacy
 *     `Field::save()` write path stays intact in `nexus/Field/Field.php`
 *     (other code paths still need it) but the public route never
 *     reached it on the happy path; we preserve that.
 *
 * UI strings: hardcoded English, same trade-off as
 * `PollOverviewController` / `AddUserController`. The
 * `lang/<locale>/lang_fields.php` files stay in tree because
 * `Field::buildFieldForm()` / `buildFieldTable()` / `buildFieldCheckbox()`
 * are still loaded as globals by other legacy pages
 * (`public/edit.php`, `public/takeupload.php`, `public/catmanage.php`,
 * `public/details.php`); a Phase 5 pass that decouples `Field` from
 * the lang globals can drop both the dictionaries and the unused
 * `buildField*` methods together.
 *
 * The `/fields.php` URL is preserved exactly so the
 * `AdminpanelTableSeeder.url='fields.php'` menu entry, the
 * `nexus/Install/Update.php::runExtraQueries()` `addMenu('fields.php')`
 * block, and any admin bookmarks keep working without template
 * changes. A matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
class FieldsController extends Controller
{
    private const KNOWN_TYPES = ['text', 'textarea', 'radio', 'checkbox', 'select', 'image'];

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

        $action = (string) $request->query('action', 'view');

        return match ($action) {
            'add' => $this->renderAdd(),
            'edit' => $this->renderEdit($request),
            'del' => $this->handleDelete($request),
            'submit' => new Response(self::DEPRECATION_MESSAGE),
            default => $this->renderList(),
        };
    }

    private function renderList(): Response
    {
        $rows = NexusDB::table('torrents_custom_fields')
            ->orderByDesc('priority')
            ->get();

        $body = '<h1 align="center">Custom field management</h1>'."\n"
            .'<div style="margin-bottom: 8px;">'
            .'<span id="add"><a href="?action=add" class="big"><b>Add</b></a></span>'
            .'</div>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5" width="100%">'."\n"
            .'<thead><tr>'
            .'<td class="colhead">ID</td>'
            .'<td class="colhead">Name</td>'
            .'<td class="colhead">Display label</td>'
            .'<td class="colhead">Type</td>'
            .'<td class="colhead">Required</td>'
            .'<td class="colhead">Display on a single row</td>'
            .'<td class="colhead">Priority</td>'
            .'<td class="colhead">Action</td>'
            .'</tr></thead>'."\n"
            .'<tbody>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $body .= '<tr>'
                .'<td class="colfollow">'.(int) ($arr['id'] ?? 0).'</td>'
                .'<td class="colfollow">'.htmlspecialchars((string) ($arr['name'] ?? '')).'</td>'
                .'<td class="colfollow">'.htmlspecialchars((string) ($arr['label'] ?? '')).'</td>'
                .'<td class="colfollow">'.htmlspecialchars((string) ($arr['type'] ?? '')).'</td>'
                .'<td class="colfollow">'.((int) ($arr['required'] ?? 0) === 1 ? 'Yes' : 'No').'</td>'
                .'<td class="colfollow">'.((int) ($arr['is_single_row'] ?? 0) === 1 ? 'Yes' : 'No').'</td>'
                .'<td class="colfollow">'.(int) ($arr['priority'] ?? 0).'</td>'
                .'<td class="colfollow">'
                .'<a href="javascript:confirm_delete(\''.(int) ($arr['id'] ?? 0).'\', \'Sure to delete?\', \'\');">Delete</a>'
                .' | <a href="?action=edit&amp;id='.(int) ($arr['id'] ?? 0).'">Edit</a>'
                .'</td>'
                .'</tr>'."\n";
        }
        $body .= '</tbody></table>'."\n";

        return new Response($this->wrap('Custom field management', $body));
    }

    private function renderAdd(): Response
    {
        return new Response($this->wrap(
            'Custom field management - Add',
            $this->buildForm([]),
        ));
    }

    private function renderEdit(Request $request): Response
    {
        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return new Response(
                $this->wrap('Error', '<p align="center">Invalid id</p>'),
                422,
            );
        }
        $row = NexusDB::table('torrents_custom_fields')->where('id', $id)->first();
        if ($row === null) {
            return new Response(
                $this->wrap('Error', '<p align="center">Invalid id</p>'),
                404,
            );
        }

        return new Response($this->wrap(
            'Custom field management - Edit',
            $this->buildForm((array) $row),
        ));
    }

    private function handleDelete(Request $request): RedirectResponse|Response
    {
        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return new Response(
                $this->wrap('Error', '<p align="center">Invalid id</p>'),
                422,
            );
        }
        NexusDB::table('torrents_custom_fields')->where('id', $id)->delete();

        return new RedirectResponse('/fields.php?action=view');
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function buildForm(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $name = htmlspecialchars((string) ($row['name'] ?? ''));
        $label = htmlspecialchars((string) ($row['label'] ?? ''));
        $help = htmlspecialchars((string) ($row['help'] ?? ''));
        $options = htmlspecialchars((string) ($row['options'] ?? ''));
        $display = htmlspecialchars((string) ($row['display'] ?? ''));
        $priority = (int) ($row['priority'] ?? 0);
        $currentType = (string) ($row['type'] ?? '');
        $currentRequired = (string) ($row['required'] ?? '');
        $currentIsSingleRow = (string) ($row['is_single_row'] ?? '');

        $typeRadios = '';
        foreach (self::KNOWN_TYPES as $type) {
            $checked = $currentType === $type ? ' checked' : '';
            $typeRadios .= '<label style="margin-right: 4px;">'
                .'<input type="radio" name="type" value="'.$type.'"'.$checked.' />'
                .htmlspecialchars($type)
                .'</label>';
        }

        $requiredRadios = $this->yesNoRadios('required', $currentRequired);
        $isSingleRowRadios = $this->yesNoRadios('is_single_row', $currentIsSingleRow);

        return '<div>'."\n"
            .'<h1 align="center"><a class="faqlink" href="?action=view">Field</a></h1>'."\n"
            .'<form method="post" action="fields.php?action=submit">'."\n"
            .'<div>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="10" width="100%">'."\n"
            .'<input type="hidden" name="id" value="'.$id.'"/>'
            .$this->row('Name<font color="red">*</font>', '<input type="text" name="name" value="'.$name.'" style="width: 300px" />&nbsp;&nbsp;Only allow digit, alphabet, underline')
            .$this->row('Display label<font color="red">*</font>', '<input type="text" name="label" value="'.$label.'" style="width: 300px" />')
            .$this->row('Type<font color="red">*</font>', $typeRadios)
            .$this->row('Required<font color="red">*</font>', $requiredRadios)
            .$this->row('Help text', '<textarea name="help" rows="4" cols="80">'.$help.'</textarea>')
            .$this->row('Options', '<textarea name="options" rows="6" cols="80">'.$options.'</textarea><br/>Required when type is radio, checkbox, select. One line, one option, format: value|display text')
            .$this->row('Display on a single row<font color="red">*</font>', $isSingleRowRadios)
            .$this->row('Priority<font color="red">*</font>', '<input type="number" name="priority" value="'.$priority.'" style="width: 300px" />')
            .$this->row('Custom display', '<textarea name="display" rows="4" cols="80">'.$display.'</textarea>')
            .'</table>'."\n"
            .'</div>'."\n"
            .'<div style="text-align: center; margin-top: 10px;">'
            .'<input type="submit" value="Submit" />'
            .'</div>'."\n"
            .'</form>'."\n"
            .'</div>'."\n";
    }

    private function row(string $label, string $value): string
    {
        return '<tr><td class="rowhead" align="right" valign="top" width="20%">'.$label.'</td>'
            .'<td class="rowfollow" align="left" valign="top">'.$value.'</td></tr>'."\n";
    }

    private function yesNoRadios(string $name, string $current): string
    {
        $no = $current === '0' ? ' checked' : '';
        $yes = $current === '1' ? ' checked' : '';

        return '<label style="margin-right: 4px;">'
            .'<input type="radio" name="'.$name.'" value="0"'.$no.' />No'
            .'</label>'
            .'<label style="margin-right: 4px;">'
            .'<input type="radio" name="'.$name.'" value="1"'.$yes.' />Yes'
            .'</label>';
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
