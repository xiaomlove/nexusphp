<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Internal Blade-component showcase served at `/dev/components`.
 *
 * Purpose: a single rendered page exercising every component under
 * `resources/views/components/ui/` so the design-system surface is
 * (a) reviewable in code review without spinning up the dev server
 *     for every PR, and
 * (b) covered by E2E smoke (`tests/e2e/smoke/dev-components.spec.ts`)
 *     so an accidental break in a shared component fails CI in the
 *     same run that touched the component.
 *
 * Gated to staff (CLASS_ADMINISTRATOR and above) because it has no
 * end-user purpose and we don't want it indexed or surfaced via the
 * regular navigation. The route is also middleware'd
 * `auth.nexus:nexus-web` so guests get the legacy login bounce, not
 * a 403.
 */
class ComponentGalleryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = auth('nexus-web')->user();
        if (! $user || (int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        return view('dev.components');
    }
}
