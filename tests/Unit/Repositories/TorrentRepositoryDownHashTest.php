<?php

namespace Tests\Unit\Repositories;

use App\Repositories\TorrentRepository;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers the security-critical `download.php?downhash=` path.
 *
 * Historical context: GHSA-style issue #281 in upstream xiaomlove/nexusphp
 * showed that a guessable short hash in the downhash URL allowed any user's
 * passkey to be brute-forced. The current implementation switched to a JWT
 * keyed by `md5(passkey . date('Ymd') . user_id)`. These tests pin down the
 * round-trip behaviour and the negative (cross-user / tampered / empty) paths
 * so future refactors can't silently weaken it.
 */
class TorrentRepositoryDownHashTest extends TestCase
{
    private TorrentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TorrentRepository;
    }

    private function user(int $id, string $passkey): array
    {
        return ['id' => $id, 'passkey' => $passkey];
    }

    public function test_encrypt_then_decrypt_returns_original_torrent_id(): void
    {
        $user = $this->user(42, str_repeat('a', 32));
        $torrentId = 123;

        $hash = $this->repo->encryptDownHash($torrentId, $user);
        $decoded = $this->repo->decryptDownHash($hash, $user);

        $this->assertIsArray($decoded);
        $this->assertSame($torrentId, $decoded[0]);
    }

    public function test_hash_produced_for_one_user_cannot_be_decrypted_by_another_user(): void
    {
        $alice = $this->user(1, str_repeat('a', 32));
        $bob = $this->user(2, str_repeat('b', 32));

        $hash = $this->repo->encryptDownHash(99, $alice);

        $this->assertSame('', $this->repo->decryptDownHash($hash, $bob));
    }

    public function test_hash_with_same_user_id_but_different_passkey_fails(): void
    {
        $original = $this->user(7, str_repeat('a', 32));
        $rotated = $this->user(7, str_repeat('z', 32));

        $hash = $this->repo->encryptDownHash(555, $original);

        $this->assertSame(
            '',
            $this->repo->decryptDownHash($hash, $rotated),
            'Rotating the passkey must invalidate previously generated download hashes',
        );
    }

    public function test_tampered_jwt_fails_to_decrypt(): void
    {
        $user = $this->user(11, str_repeat('a', 32));
        $hash = $this->repo->encryptDownHash(7, $user);

        // Flip a character somewhere in the middle of the JWT signature.
        $tampered = substr($hash, 0, -2).(($hash[strlen($hash) - 2] === 'a') ? 'bb' : 'aa');

        $this->assertSame('', $this->repo->decryptDownHash($tampered, $user));
    }

    public function test_garbage_input_fails_decrypt_safely(): void
    {
        $user = $this->user(11, str_repeat('a', 32));

        $this->assertSame('', $this->repo->decryptDownHash('not-a-jwt', $user));
        $this->assertSame('', $this->repo->decryptDownHash('', $user));
    }

    public function test_user_without_passkey_cannot_generate_hash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->encryptDownHash(1, ['id' => 1, 'passkey' => '']);
    }

    public function test_two_consecutive_encrypts_for_same_user_decode_to_same_torrent_id(): void
    {
        $user = $this->user(42, str_repeat('a', 32));

        $hash1 = $this->repo->encryptDownHash(123, $user);
        $hash2 = $this->repo->encryptDownHash(123, $user);

        // The JWT payload contains an `exp` timestamp, so two consecutive calls in the
        // same second can produce identical strings — but they must always decode to
        // the same torrent id.
        $this->assertSame(123, $this->repo->decryptDownHash($hash1, $user)[0]);
        $this->assertSame(123, $this->repo->decryptDownHash($hash2, $user)[0]);
    }
}
