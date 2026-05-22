<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/modrules.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_ADMINISTRATOR` gate — only
 *      Administrator+ (class >= 14) can manage rules.
 *   3. CRUD operations on the `rules` table via `?act=` parameter:
 *      - (no act) — list all rules grouped by language.
 *      - `newsect` — render "Add Rules" form.
 *      - `addsect` — POST handler: insert new rule row.
 *      - `edit` — render "Edit Rules" form for `?id=<n>`.
 *      - `edited` — POST handler: update rule row.
 *      - `del` — delete rule row (with `?sure=1` confirmation).
 *   4. After mutations, clears the `rules` cache key and redirects
 *      to `modrules.php` (the listing).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` →
 *     `abort(403)`.
 *   - Administrator+ → dispatches to the appropriate action handler
 *     based on `?act=` query parameter.
 *   - All mutations clear the `rules` cache key (used by
 *     `RulesController`) and redirect to `/modrules.php`.
 *   - Chrome-less HTML envelope, same precedent as `BansController`.
 *   - POST endpoints are CSRF-exempt (the legacy forms had no
 *     `@csrf` field).
 */
class ModrulesController extends Controller
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

        $act = (string) $request->query('act', '');

        return match ($act) {
            'newsect' => $this->renderAddForm(),
            'addsect' => $this->handleAdd($request),
            'edit' => $this->renderEditForm($request),
            'edited' => $this->handleEdit($request),
            'del' => $this->handleDelete($request),
            default => $this->renderListing(),
        };
    }

    private function renderAddForm(): Response
    {
        $langOptions = $this->buildLanguageOptions(null);

        $body = '<h1 align="center">Add Rules</h1>'."\n"
            .'<form method="post" action="modrules.php?act=addsect">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="10" align="center">'."\n"
            .'<tr><td>Title:</td><td align="left"><input style="width: 400px;" type="text" name="title" /></td></tr>'."\n"
            .'<tr><td style="vertical-align: top;">Rules:</td><td><textarea cols="90" rows="20" name="text"></textarea></td></tr>'."\n"
            .'<tr><td>Language:</td><td align="center"><select name="language">'.$langOptions.'</select></td></tr>'."\n"
            .'<tr><td colspan="2" align="center"><input type="submit" value="Add" style="width: 60px;" /></td></tr>'."\n"
            .'</table></form>'."\n";

        return $this->render('Add section', $body);
    }

    private function handleAdd(Request $request): RedirectResponse
    {
        $title = (string) $request->input('title', '');
        $text = (string) $request->input('text', '');
        $language = (int) $request->input('language', 0);

        NexusDB::insert('rules', [
            'title' => $title,
            'text' => $text,
            'lang_id' => $language,
        ]);

        $this->clearRulesCache();

        return redirect('/modrules.php');
    }

    private function renderEditForm(Request $request): Response
    {
        $id = (int) $request->query('id', 0);

        $resObj = NexusDB::table('rules')->where('id', $id)->first();
        if ($resObj === null) {
            return $this->render('Error', '<p align="center">Rule not found.</p>');
        }
        $res = (array) $resObj;

        $langOptions = $this->buildLanguageOptions((int) ($res['lang_id'] ?? 0));
        $titleEsc = htmlspecialchars((string) ($res['title'] ?? ''));
        $textEsc = htmlspecialchars((string) ($res['text'] ?? ''));

        $body = '<h1 align="center">Edit Rules</h1>'."\n"
            .'<form method="post" action="modrules.php?act=edited">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="10" align="center">'."\n"
            .'<tr><td>Title:</td><td align="left"><input style="width: 400px;" type="text" name="title" value="'.$titleEsc.'" /></td></tr>'."\n"
            .'<tr><td style="vertical-align: top;">Rules:</td><td><textarea cols="90" rows="20" name="text">'.$textEsc.'</textarea></td></tr>'."\n"
            .'<tr><td>Language:</td><td align="center"><select name="language">'.$langOptions.'</select></td></tr>'."\n"
            .'<tr><td colspan="2" align="center"><input type="hidden" value="'.$id.'" name="id" /><input type="submit" value="Save" style="width: 60px;" /></td></tr>'."\n"
            .'</table></form>'."\n";

        return $this->render('Edit rules', $body);
    }

    private function handleEdit(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $title = (string) $request->input('title', '');
        $text = (string) $request->input('text', '');
        $language = (int) $request->input('language', 0);

        NexusDB::table('rules')->where('id', $id)->update([
            'title' => $title,
            'text' => $text,
            'lang_id' => $language,
        ]);

        $this->clearRulesCache();

        return redirect('/modrules.php');
    }

    private function handleDelete(Request $request): Response|RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $sure = (int) $request->query('sure', 0);

        if (! $sure) {
            $body = '<h2 align="center">Delete Rule</h2>'."\n"
                .'<p align="center">You are about to delete a rule. Click '
                .'<a class="altlink" href="modrules.php?act=del&id='.$id.'&sure=1">here</a>'
                .' if you are sure.</p>'."\n";

            return $this->render('Delete Rule', $body);
        }

        NexusDB::table('rules')->where('id', $id)->delete();
        $this->clearRulesCache();

        return redirect('/modrules.php');
    }

    private function renderListing(): Response
    {
        $ruleRows = NexusDB::select(
            'SELECT rules.*, lang_name FROM rules LEFT JOIN language ON rules.lang_id = language.id ORDER BY lang_name, id',
        );

        $body = '<h1 align="center">Rules Management</h1>'."\n"
            .'<p align="center"><a href="modrules.php?act=newsect">Add Section</a></p>'."\n";

        foreach ($ruleRows as $row) {
            $arr = (array) $row;
            $id = (int) ($arr['id'] ?? 0);
            $title = htmlspecialchars((string) ($arr['title'] ?? ''));
            $langName = htmlspecialchars((string) ($arr['lang_name'] ?? ''));
            $text = format_comment((string) ($arr['text'] ?? ''));

            $body .= '<table width="940" border="1" cellspacing="0" cellpadding="5">'."\n"
                .'<tr><td class="colhead">'.$title.' - '.$langName.'</td></tr>'."\n"
                .'<tr><td align="left">'.$text.'</td></tr>'."\n"
                .'<tr><td align="left"><a href="modrules.php?act=edit&id='.$id.'">Edit</a>'
                .'&nbsp;&nbsp;<a href="modrules.php?act=del&id='.$id.'">Delete</a></td></tr>'."\n"
                .'</table><br />'."\n";
        }

        return $this->render('Rules Management', $body);
    }

    /**
     * Build `<option>` tags for the language selector.
     *
     * The legacy script used `langlist("rule_lang")` for the add form
     * and `langlist("site_lang")` for the edit form. Both functions
     * query the `language` table with different flag columns. We use
     * `site_lang = 1` which covers both use cases (rule languages are
     * a subset of site languages).
     */
    private function buildLanguageOptions(?int $selectedId): string
    {
        $langs = NexusDB::table('language')
            ->where('site_lang', 1)
            ->orderBy('lang_name')
            ->get(['id', 'lang_name']);

        $options = '';
        foreach ($langs as $lang) {
            $arr = (array) $lang;
            $id = (int) ($arr['id'] ?? 0);
            $name = htmlspecialchars((string) ($arr['lang_name'] ?? ''));
            $selected = ($selectedId !== null && $id === $selectedId) ? ' selected' : '';
            $options .= '<option value="'.$id.'"'.$selected.'>'.$name.'</option>'."\n";
        }

        return $options;
    }

    private function clearRulesCache(): void
    {
        Cache::forget('rules');
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
