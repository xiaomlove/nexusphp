<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Throwable;

class ResetController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request, UserRepository $userRepository): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $userRepository);
        }

        return $this->renderForm();
    }

    private function handlePost(Request $request, UserRepository $userRepository): Response
    {
        $username = trim((string) $request->input('username', ''));
        $newPassword = trim((string) $request->input('newpassword', ''));
        $newPasswordAgain = trim((string) $request->input('newpasswordagain', ''));

        if ($username === '' || $newPassword === '' || $newPasswordAgain === '') {
            return $this->renderError("Don't leave any fields blank.");
        }
        if ($newPassword !== $newPasswordAgain) {
            return $this->renderError("The passwords didn't match! Must've typoed. Try again.");
        }
        if (strlen($newPassword) < 6) {
            return $this->renderError('Sorry, password is too short (min is 6 chars)');
        }

        $targetObj = NexusDB::table('users')
            ->where('username', $username)
            ->first();
        $target = $targetObj ? (array) $targetObj : null;
        if (empty($target)) {
            return $this->renderError("Sorry, that username doesn't exist.");
        }

        $operatorClass = (int) ($this->context->user()->class ?? 0);
        if ($operatorClass <= (int) ($target['class'] ?? 0)) {
            return $this->renderError(
                "Sorry, you don't have enough permission to reset this user's password.",
            );
        }

        try {
            $userRepository->resetPassword(
                (int) $target['id'],
                $newPassword,
                $newPasswordAgain,
            );
        } catch (Throwable $e) {
            return $this->renderError($e->getMessage());
        }

        $safeUsername = htmlspecialchars((string) $target['username']);

        return $this->envelope(
            'Success',
            '<h1>Success</h1>'
            ."<p>The password of account <b>{$safeUsername}</b> is reset, please inform user of this change.</p>",
        );
    }

    private function renderForm(): Response
    {
        $body = <<<'HTML'
<h1>Reset User's Lost Password</h1>
<table border="1" cellspacing="0" cellpadding="5">
<form method="post">
<tr><td class="colhead" align="center" colspan="2">Reset User's Lost Password</td></tr>
<tr><td class="rowhead" align="right">User Name:</td><td class="rowfollow"><input size="40" name="username"></td></tr>
<tr><td class="rowhead" align="right">New Password:</td><td class="rowfollow"><input type="password" size="40" name="newpassword"><br /><font class="small">Minimum is 6 characters</font></td></tr>
<tr><td class="rowhead" align="right">Confirm New Password:</td><td class="rowfollow"><input type="password" size="40" name="newpasswordagain"></td></tr>
<tr><td class="toolbox" colspan="2" align="center"><input type="submit" class="btn" value="Reset"></td></tr>
</form>
</table>
HTML;

        return $this->envelope("Reset User's Lost Password", $body);
    }

    private function renderError(string $message): Response
    {
        return $this->envelope(
            'Error',
            '<h1>Error</h1><p>'.htmlspecialchars($message, ENT_NOQUOTES).'</p>',
        );
    }

    private function envelope(string $title, string $body): Response
    {
        $safeTitle = htmlspecialchars($title);
        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$safeTitle}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
