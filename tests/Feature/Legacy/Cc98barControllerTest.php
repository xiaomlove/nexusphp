<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/cc98bar.php/...id<userid>.png` contract.
 *
 * Public PNG userbar generator; same shape as `/mybar.php` but with
 * a path-style URI rather than query-string parameters. All the
 * short-circuit gates (regex miss, unknown user, strong-privacy,
 * below-class) must return 204 with an empty body so the cache
 * layer can't accidentally cache an upstream-failure response.
 */
class Cc98barControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_malformed_uri_returns_204(): void
    {
        $response = $this->get('/cc98bar.php/garbage.png');
        $response->assertNoContent();
    }

    public function test_unknown_user_returns_204(): void
    {
        $response = $this->get('/cc98bar.php/id99999999.png');
        $response->assertNoContent();
    }

    public function test_strong_privacy_user_returns_204(): void
    {
        $user = $this->createLegacyUser(overrides: ['privacy' => 'strong']);

        $response = $this->get('/cc98bar.php/id'.$user->id.'.png');
        $response->assertNoContent();
    }

    public function test_below_userbar_class_user_returns_204(): void
    {
        // Default `authority.userbar` is class 1 (Power User-ish);
        // a fresh User has class CLASS_USER (= 1) so we drop the
        // user to class 0 (Peasant) to land in the below-threshold
        // branch reliably.
        $user = $this->createLegacyUser(overrides: ['class' => 0]);

        $response = $this->get('/cc98bar.php/id'.$user->id.'.png');
        $response->assertNoContent();
    }
}
