<?php
namespace App\Repositories;

class MeiliSearchRepository extends BaseRepository
{
    private $client;

    public function getCleint()
    {
        if (is_null($this->client)) {

        }
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
