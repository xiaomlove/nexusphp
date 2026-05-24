<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\DonateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DonateController extends Controller
{
    public function __construct(private readonly DonateService $service) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->service->isEnabled()) {
            return $this->errorResponse(
                'Sorry',
                'This site does not accept donations.',
                503,
            );
        }

        $do = (string) $request->query('do', '');
        if ($do === 'thanks') {
            return $this->thanksResponse();
        }

        $paypal = $this->service->paypalAccount();
        $alipay = $this->service->alipayAccount();
        $custom = $this->service->customMessage();

        if ($paypal === null && $alipay === null && $custom === '') {
            return $this->errorResponse(
                'Error',
                'No donation account is available.',
                503,
            );
        }

        $base = $this->service->baseUrlWithProtocol();
        $body = view('legacy.donate', [
            'paypal' => $paypal,
            'alipay' => $alipay,
            'custom' => $custom,
            'siteName' => $this->service->siteName(),
            'accountantId' => $this->service->accountantId(),
            'amounts' => $this->service->donationAmounts(),
            'returnUrl' => rtrim($base, '/').'/donate.php?do=thanks',
            'cancelUrl' => rtrim($base, '/').'/donate.php',
        ])->render();

        return new Response($this->wrap('Donate', $body));
    }

    private function thanksResponse(): Response
    {
        $accountantId = $this->service->accountantId();
        $link = '<a href="sendmessage.php?receiver='.$accountantId.'"><b>here</b></a>';
        $body = '<h2>Success</h2>'."\n"
            .'<p>Thanks for your donation. Please click '.$link
            .' to send us a message with your username so we can credit your account.</p>';

        return new Response($this->wrap('Donation success', $body));
    }

    private function errorResponse(string $title, string $message, int $status): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = '<h2>'.$titleEsc.'</h2>'."\n".'<p>'.$messageEsc.'</p>';

        return new Response($this->wrap($title, $body), $status);
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
