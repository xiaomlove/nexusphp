<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\Permission\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Services\UserHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserHistoryController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserHistoryService $service,
    ) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }

        if ($this->service->isParked($viewer)) {
            abort(403, 'Account is parked.');
        }

        $uid = (int) $request->input('id', 0);
        if ($uid <= 0) {
            abort(422, "Invalid id: $uid");
        }

        $target = $this->service->findUser($uid);
        if ($target === null) {
            abort(404, "Invalid id: $uid");
        }

        if ($target->id !== $viewer->id && ! $this->canViewOther($viewer)) {
            abort(403);
        }

        $action = (string) $request->input('action', '');
        $page = max(0, (int) $request->input('page', 0));

        return match ($action) {
            UserHistoryService::ACTION_VIEWPOSTS => $this->renderPosts(
                $request,
                $viewer,
                $target,
                $page,
            ),
            UserHistoryService::ACTION_VIEWCOMMENTS => $this->renderComments(
                $request,
                $target,
                $page,
            ),
            default => abort(422, 'Unknown action.'),
        };
    }

    private function renderPosts(Request $request, User $viewer, User $target, int $page): Response
    {
        $pageData = $this->service->viewposts(
            uid: (int) $target->id,
            viewerClass: (int) $viewer->class,
            page: $page,
        );

        $body = view('legacy.userhistory', [
            'action' => UserHistoryService::ACTION_VIEWPOSTS,
            'viewer' => $viewer,
            'target' => $target,
            'pageData' => $pageData,
            'requestUri' => (string) $request->getRequestUri(),
            'service' => $this->service,
        ])->render();

        return $this->wrap('Posts history — '.$target->username, $body);
    }

    private function renderComments(Request $request, User $target, int $page): Response
    {
        $pageData = $this->service->viewcomments(
            uid: (int) $target->id,
            page: $page,
        );

        $body = view('legacy.userhistory', [
            'action' => UserHistoryService::ACTION_VIEWCOMMENTS,
            'target' => $target,
            'pageData' => $pageData,
            'requestUri' => (string) $request->getRequestUri(),
            'service' => $this->service,
        ])->render();

        return $this->wrap('Comments history — '.$target->username, $body);
    }

    private function wrap(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = <<<HTML
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

        return new Response($html);
    }

    private function canViewOther(User $viewer): bool
    {
        $permission = PermissionEnum::VIEW_USER_HISTORY->value;

        return (bool) user_can($permission, false, (int) $viewer->id);
    }
}
