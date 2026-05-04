<?php

namespace Tests\Concerns;

use Rhilip\Bencode\Bencode;

/**
 * Builds minimal but valid `.torrent` payloads for upload Feature tests.
 * The output passes `Rhilip\Bencode\TorrentFile::load($path)->parse()` —
 * which is what `public/takeupload.php` runs to validate the file.
 */
trait GeneratesTorrentFiles
{
    /**
     * Produce a minimal single-file torrent and write it to a temp path.
     * Returns the absolute path so tests can attach it as `multipart` upload.
     *
     * `$marker` lets the caller force a unique info_hash by feeding random
     * bytes into the synthetic file content (and therefore into the SHA-1
     * pieces hash).
     */
    protected function makeTorrentFile(string $marker = ''): string
    {
        $marker = $marker !== '' ? $marker : bin2hex(random_bytes(8));
        $payload = str_pad("nexus-test-{$marker}", 32, "\0");
        $pieceLength = 16384;
        $pieces = sha1($payload, true);

        $info = [
            'length' => strlen($payload),
            'name' => "nexus-test-{$marker}.bin",
            'piece length' => $pieceLength,
            'pieces' => $pieces,
            // private must be present and falsy at upload time; takeupload.php
            // forces it to true via setPrivate(). We just need the file to parse.
            'private' => 0,
        ];

        $torrent = [
            'announce' => 'https://tracker.example.test/announce',
            'created by' => 'nexus-test-suite',
            'creation date' => time(),
            'info' => $info,
        ];

        $bencoded = Bencode::encode($torrent);
        $path = tempnam(sys_get_temp_dir(), 'nexus-torrent-').'.torrent';
        file_put_contents($path, $bencoded);

        return $path;
    }
}
