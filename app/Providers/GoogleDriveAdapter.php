<?php

namespace App\Providers;

class GoogleDriveAdapter extends \Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter
{
    public function getService()
    {
        return $this->service;
    }
}
