<?php

namespace Nexus\Attachment;

use Nexus\Attachment\Drivers\Chevereto;
use Nexus\Attachment\Drivers\Local;
use Nexus\Attachment\Drivers\Lsky;

abstract class Storage
{
    private static array $drivers = [];

    const DRIVER_LOCAL = 'local';

    const DRIVER_CHEVERETO = 'chevereto';

    const DRIVER_LSKY = 'lsky';

    /**
     * upload to remote and return full url
     */
    abstract public function upload(string $filepath): string;

    abstract public function getBaseUrl(): string;

    abstract public function getDriverName(): string;

    public function uploadGetLocation(string $filepath, string $originalName): string
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $newFilepath = sprintf('%s/%s', dirname($filepath), trim($originalName));
            $moveResult = move_uploaded_file($filepath, $newFilepath);
            do_log(sprintf('filepath: %s, newFilepath: %s, moveResult: %s', $filepath, $newFilepath, $moveResult));
            if (! $moveResult) {
                throw new \Exception('Failed to move uploaded file.');
            }
            $url = $this->upload($newFilepath);
            @unlink($newFilepath);
        } else {
            $url = $this->upload($filepath);
            @unlink($filepath);
        }

        return $this->trimBaseUrl($url);
    }

    public function getImageUrl(string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        return sprintf('%s/%s', trim($this->getBaseUrl(), '/'), trim($location, '/'));
    }

    protected function trimBaseUrl(string $url): string
    {
        $baseUrl = trim($this->getBaseUrl(), '/').'/';
        if (str_starts_with($url, $baseUrl)) {
            return substr($url, strlen($baseUrl));
        }

        return $url;
    }

    public static function getDriver(?string $name = null): Storage
    {
        $driver = $name ?: get_setting('image_hosting.driver');
        if (isset(self::$drivers[$driver])) {
            return self::$drivers[$driver];
        }
        $result = null;
        if ($driver == self::DRIVER_CHEVERETO) {
            $result = new Chevereto;
        } elseif ($driver == self::DRIVER_LSKY) {
            $result = new Lsky;
        } elseif ($driver == self::DRIVER_LOCAL) {
            $result = new Local;
        }
        if ($result) {
            return self::$drivers[$driver] = $result;
        }
        throw new \Exception("Unsupported driver: $driver");
    }
}
