<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/donate.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` bootstrap (implicit
 *      `loggedinorreturn()` via `bittorrent.php`).
 *   2. Checks `$enabledonation` setting — if not 'yes', renders
 *      an error via `stderr()`.
 *   3. `?do=thanks` branch: renders a success message with a link
 *      to message the accountant.
 *   4. Default branch: renders PayPal and/or Alipay donation forms
 *      using `$PAYPALACCOUNT`, `$ALIPAYACCOUNT`, `$ACCOUNTANTID`
 *      globals from `include/config.php`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - `donation` setting != 'yes' → 200 with "donations not accepted"
 *     message.
 *   - `?do=thanks` → 200 with success message.
 *   - Default → 200 with chrome-less HTML envelope showing available
 *     donation methods (PayPal and/or Alipay).
 *   - URL stays `/donate.php` so the PayPal `return` URL
 *     (`/donate.php?do=thanks`) keeps working.
 */
class DonateController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $enableDonation = Setting::get('main.donation');
        if ($enableDonation !== 'yes') {
            return $this->render('Donation', '<h2 align="center">Sorry</h2>'
                .'<p align="center">We do not accept donations at this time.</p>');
        }

        $do = (string) $request->query('do', '');
        if ($do === 'thanks') {
            $accountantId = (int) Setting::get('main.ACCOUNTANTID');

            return $this->render('Donation Success', '<h2 align="center">Success!</h2>'
                .'<p align="center">Thank you for your donation! Please '
                .'<a href="sendmessage.php?receiver='.$accountantId.'"><b>click here</b></a>'
                .' to send us a message with your transaction details so we can credit your account.</p>');
        }

        return $this->renderDonationForm();
    }

    private function renderDonationForm(): Response
    {
        $siteName = htmlspecialchars((string) Setting::getSiteName());
        $baseUrl = htmlspecialchars((string) Setting::get('basic.BASEURL'));
        $paypal = trim((string) Setting::get('main.PAYPALACCOUNT'));
        $alipay = trim((string) Setting::get('main.ALIPAYACCOUNT'));
        $custom = trim((string) Setting::getByName('misc.donation_custom'));
        $accountantId = (int) Setting::get('main.ACCOUNTANTID');

        $showPaypal = ($paypal !== '' && filter_var($paypal, FILTER_VALIDATE_EMAIL) !== false);
        $showAlipay = ($alipay !== '' && filter_var($alipay, FILTER_VALIDATE_EMAIL) !== false);

        if (! $showPaypal && ! $showAlipay && $custom === '') {
            return $this->render('Donation', '<h2 align="center">Error</h2>'
                .'<p align="center">No donation account is currently available.</p>');
        }

        $body = '<h2>Donate</h2>'."\n"
            .'<table width="100%">'."\n"
            .'<tr><td colspan="2" class="text" align="left">Your donation helps keep this site running. Thank you for your generosity!</td></tr>'."\n";

        if ($custom !== '') {
            $body .= '<tr><td class="text" align="left" colspan="2">'.format_comment($custom).'</td></tr>'."\n";
        }

        $body .= '<tr>'."\n";

        if ($showPaypal) {
            $paypalEsc = htmlspecialchars($paypal);
            $amounts = [1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300];
            $options = '<option value="" selected>Choose donation amount</option>'
                .'<option value="">Other amount</option>';
            foreach ($amounts as $amount) {
                $formatted = number_format($amount, 2);
                $options .= '<option value="'.$formatted.'">US$'.$formatted.' Donation</option>';
            }

            $body .= '<td class="text" align="left" valign="top">'
                .'<b>Donate with PayPal</b><br /><br />'
                .'Send your donation via PayPal to our account.'
                .'<form action="https://www.paypal.com/cgi-bin/webscr" method="post">'
                .'<input type="hidden" name="cmd" value="_xclick">'
                .'<input type="hidden" name="business" value="'.$paypalEsc.'">'
                .'<input type="hidden" name="item_name" value="Donation to '.$siteName.'">'
                .'<p align="center"><br />Select donation amount:<br />'
                .'<select name="amount">'.$options.'</select>'
                .'<input type="hidden" name="shipping" value="0">'
                .'<input type="hidden" name="currency_code" value="USD">'
                .'<input type="hidden" name="return" value="https://'.$baseUrl.'/donate.php?do=thanks">'
                .'<input type="hidden" name="cancel_return" value="https://'.$baseUrl.'/donate.php">'
                .'<br /></p>'
                .'<p align="center"><input type="submit" value="Donate via PayPal" class="btn">'
                .'<br /><br /></p></form></td>'."\n";
        }

        if ($showAlipay) {
            $alipayEsc = htmlspecialchars($alipay);
            $body .= '<td class="text" align="left" valign="top">'
                .'<b>Donate with Alipay</b><br /><br />'
                .'Please send your donation to: <b>'.$alipayEsc.'</b>'
                .'<br /><br /><br /><br /><br />'
                .'<p align="center"><input type="submit" value="Donate via Alipay" class="btn">'
                .'<br /><br /></p></td>'."\n";
        }

        $body .= '</tr>'."\n"
            .'<tr><td class="text" colspan="2" align="left">After your donation, please '
            .'<a href="sendmessage.php?receiver='.$accountantId.'"><font class="striking"><b>send us a message</b></font></a>'
            .' with your transaction details so we can credit your account.</td></tr>'."\n"
            .'</table>'."\n";

        return $this->render('Donation', $body);
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
