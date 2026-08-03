<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\Security\ApiKeyCipher;

final class ApiKeyCipherTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testItEncryptsAndDecryptsAnApiKey(): void
    {
        $cipher = new ApiKeyCipher(self::KEY);

        $encrypted = $cipher->encrypt('secret-api-key');

        $this->assertStringStartsWith('v1:', $encrypted);
        $this->assertSame('secret-api-key', $cipher->decrypt($encrypted));
    }

    public function testRepeatedEncryptionProducesDifferentPayloads(): void
    {
        $cipher = new ApiKeyCipher(self::KEY);

        $first = $cipher->encrypt('secret-api-key');
        $second = $cipher->encrypt('secret-api-key');

        $this->assertNotSame($first, $second);
        $this->assertSame('secret-api-key', $cipher->decrypt($first));
        $this->assertSame('secret-api-key', $cipher->decrypt($second));
    }

    public function testWrongKeyCannotDecryptPayload(): void
    {
        $cipher = new ApiKeyCipher(self::KEY);
        $encrypted = $cipher->encrypt('secret-api-key');

        $otherCipher = new ApiKeyCipher(str_repeat('f', 64));

        $this->expectException(RuntimeException::class);

        $otherCipher->decrypt($encrypted);
    }

    public function testInvalidEncryptionKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApiKeyCipher('invalid');
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $cipher = new ApiKeyCipher(self::KEY);

        $this->expectException(InvalidArgumentException::class);

        $cipher->encrypt('');
    }

    public function testMalformedPayloadIsRejected(): void
    {
        $cipher = new ApiKeyCipher(self::KEY);

        $this->expectException(RuntimeException::class);

        $cipher->decrypt('v1:not-valid-base64!');
    }
}
