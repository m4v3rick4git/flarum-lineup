<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Security;

use InvalidArgumentException;
use RuntimeException;

final class ApiKeyCipher
{
    private const PAYLOAD_PREFIX = 'v1:';

    private string $key;

    public function __construct(string $hexKey)
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/i', $hexKey)) {
            throw new InvalidArgumentException(
                'The lineup encryption key must contain exactly 64 hexadecimal characters.'
            );
        }

        $key = hex2bin($hexKey);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException(
                'The lineup encryption key must decode to exactly 32 bytes.'
            );
        }

        $this->key = $key;
    }

    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key);

        return self::PAYLOAD_PREFIX.base64_encode($nonce.$cipherText);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PAYLOAD_PREFIX)) {
            throw new RuntimeException('Unsupported encrypted API-key format.');
        }

        $encodedPayload = substr($payload, strlen(self::PAYLOAD_PREFIX));
        $decodedPayload = base64_decode($encodedPayload, true);

        if (
            $decodedPayload === false
            || strlen($decodedPayload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        ) {
            throw new RuntimeException('Invalid encrypted API-key payload.');
        }

        $nonce = substr(
            $decodedPayload,
            0,
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

        $cipherText = substr(
            $decodedPayload,
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

        $plainText = sodium_crypto_secretbox_open(
            $cipherText,
            $nonce,
            $this->key
        );

        if ($plainText === false) {
            throw new RuntimeException(
                'The encrypted API key could not be decrypted.'
            );
        }

        return $plainText;
    }
}
