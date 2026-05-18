<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class PromotionLinkController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $key = (string) $request->query('key', '');
        $updatekey = (string) $request->query('updatekey', '');
        $viewer = $this->context->user();

        if ($key !== '') {
            $this->maybeCreditClick($key, $request, $viewer);

            return redirect($this->baseUrl());
        }

        if ($viewer === null) {
            abort(401);
        }

        if ($updatekey !== '' || ((string) ($viewer->promotion_link ?? '')) === '') {
            $promotionKey = $this->generatePromotionKey($viewer);
            NexusDB::table('users')
                ->where('id', (int) $viewer->id)
                ->update(['promotion_link' => $promotionKey]);

            return redirect('/promotionlink.php');
        }

        return new Response($this->wrap('Promotion Link', $this->renderPage($viewer)));
    }

    private function maybeCreditClick(string $key, Request $request, ?User $viewer): void
    {
        if ($viewer !== null) {
            return;
        }
        $bonusPoint = $this->bonusPoint();
        $bonusTime = $this->bonusTime();
        if ($bonusPoint <= 0) {
            return;
        }

        $promoUserId = (int) (NexusDB::table('users')->where('promotion_link', $key)->value('id') ?? 0);
        if ($promoUserId <= 0) {
            return;
        }

        $ip = $this->resolveIp($request);
        $threshold = date('Y-m-d H:i:s', time() - $bonusTime);

        $existingClicks = (int) NexusDB::table('prolinkclicks')
            ->where('userid', $promoUserId)
            ->where(function ($q) use ($threshold, $ip) {
                $q->where('added', '>', $threshold)->orWhere('ip', $ip);
            })
            ->count();
        if ($existingClicks > 0) {
            return;
        }

        if (function_exists('KPS')) {
            call_user_func('KPS', '+', $bonusPoint, $promoUserId);
        }
        NexusDB::table('prolinkclicks')->insert([
            'userid' => $promoUserId,
            'ip' => $ip,
            'added' => NexusDB::raw('NOW()'),
        ]);
    }

    private function generatePromotionKey(User $viewer): string
    {
        return md5(
            ((string) ($viewer->email ?? ''))
            .date('Y-m-d H:i:s')
            .((string) ($viewer->passhash ?? ''))
        );
    }

    private function renderPage(User $viewer): string
    {
        $yourLink = $this->baseUrl().'/promotionlink.php?key='.((string) ($viewer->promotion_link ?? ''));
        $imgUrl = $this->baseUrl().'/'.$this->promoImage();
        $siteName = $this->siteName();
        $slogan = $this->slogan();
        $linkEsc = htmlspecialchars($yourLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bonusPoint = $this->bonusPoint();
        $bonusTime = $this->bonusTime();

        $xhtmlSnippet = '<a href="'.$yourLink.'" target="_blank"><img src="'.$imgUrl.'" alt="'.$siteName.'" title="'.$siteName.' - '.$slogan.'" /></a>';
        $htmlSnippet = '<a href="'.$yourLink.'"><img src="'.$imgUrl.'" alt="'.$siteName.'" title="'.$siteName.' - '.$slogan.'"></a>';
        $bbcodeSnippet = '[url='.$yourLink.'][img]'.$imgUrl.'[/img][/url]';

        $body = '<h1>Promotion Link</h1>'."\n"
            .'<div>'
            .'<p align="left">Share this promotion link to invite people to '.htmlspecialchars($siteName, ENT_QUOTES | ENT_HTML5, 'UTF-8').'.</p>'
            .'<p align="left">When a visitor clicks your link, you will get '.$bonusPoint.' bonus points (once per '.$bonusTime.' seconds per IP).</p>'
            .'<p align="left"><b>Your promotion link is</b> <a href="'.$linkEsc.'">'.$linkEsc.'</a></p>'
            .'</div>'
            .'<table border="1" cellspacing="0" cellpadding="10" width="100%">'
            .'<tr><td class="colhead">Type</td><td class="colhead">Code</td><td class="colhead">Result</td></tr>'
            .'<tr><td class="colfollow">XHTML</td>'
            .'<td class="colfollow"><textarea cols="50" rows="4">'.htmlspecialchars($xhtmlSnippet, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</textarea></td>'
            .'<td class="colfollow" align="left">'.$xhtmlSnippet.'</td></tr>'
            .'<tr><td class="colfollow">HTML</td>'
            .'<td class="colfollow"><textarea cols="50" rows="4">'.htmlspecialchars($htmlSnippet, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</textarea></td>'
            .'<td class="colfollow">'.$htmlSnippet.'</td></tr>'
            .'<tr><td class="colfollow">BBCode</td>'
            .'<td class="colfollow"><textarea cols="50" rows="4">'.htmlspecialchars($bbcodeSnippet, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</textarea></td>'
            .'<td class="colfollow"><a href="'.$linkEsc.'"><img src="'.$imgUrl.'" /></a></td></tr>';

        if ($this->canUserBar($viewer)) {
            $userBarSnippet = '[url='.$yourLink.'][img]'.$this->baseUrl().'/mybar.php?userid='.((int) $viewer->id).'.png[/img][/url]';
            $body .= '<tr><td class="colfollow">UserBar (BBCode)</td>'
                .'<td class="colfollow"><textarea cols="50" rows="4">'.htmlspecialchars($userBarSnippet, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</textarea></td>'
                .'<td class="colfollow"><a href="'.$linkEsc.'"><img src="'.$this->baseUrl().'/mybar.php?userid='.((int) $viewer->id).'.png" /></a></td></tr>';
        }

        $body .= '</table>';

        return $body;
    }

    private function canUserBar(User $viewer): bool
    {
        if (! function_exists('user_can')) {
            return false;
        }

        return (bool) call_user_func('user_can', 'userbar', false, (int) $viewer->id);
    }

    private function bonusPoint(): float
    {
        if (isset($GLOBALS['prolinkpoint_bonus'])) {
            return (float) $GLOBALS['prolinkpoint_bonus'];
        }

        return 0.0;
    }

    private function bonusTime(): int
    {
        if (isset($GLOBALS['prolinktime_bonus'])) {
            return (int) $GLOBALS['prolinktime_bonus'];
        }

        return 0;
    }

    private function baseUrl(): string
    {
        $prefix = function_exists('get_protocol_prefix') ? (string) call_user_func('get_protocol_prefix') : 'https://';
        $base = isset($GLOBALS['BASEURL']) ? (string) $GLOBALS['BASEURL'] : (string) config('app.url');

        return $prefix.$base;
    }

    private function siteName(): string
    {
        return isset($GLOBALS['SITENAME']) ? (string) $GLOBALS['SITENAME'] : (string) config('app.name');
    }

    private function slogan(): string
    {
        return isset($GLOBALS['SLOGAN']) ? (string) $GLOBALS['SLOGAN'] : '';
    }

    private function promoImage(): string
    {
        return isset($GLOBALS['prolinkimg']) ? (string) $GLOBALS['prolinkimg'] : 'pic/promo.gif';
    }

    private function resolveIp(Request $request): string
    {
        if (function_exists('getip')) {
            return (string) call_user_func('getip');
        }

        return (string) ($request->ip() ?? '');
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
