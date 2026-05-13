<?php

namespace Nexus\Attachment\Drivers;

use Nexus\Attachment\Storage;

class Local extends Storage
{
    public function upload(string $filepath): string
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getBaseUrl(): string
    {
        return sprintf('%s/%s', getSchemeAndHttpHost(), trim(get_setting('attachment.httpdirectory'), '/'));
    }

    public function getDriverName(): string
    {
        return static::DRIVER_LOCAL;
    }
}
