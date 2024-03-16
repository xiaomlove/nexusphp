<?php

namespace App\Models;

use Laravel\Passport\Client;

class OauthClient extends Client
{
    public function skipsAuthorization(): bool
    {
        return (bool)$this->skips_authorization;
    }
}
