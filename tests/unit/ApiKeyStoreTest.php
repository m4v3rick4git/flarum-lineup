<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Api\ApiKeyStore;
use Wss\FlarumLineup\Security\ApiKeyCipher;

final class ApiKeyStoreTest extends TestCase
{
    private const ENCRYPTION_KEY =
        '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testItStoresTheApiKeyEncrypted(): void
    {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(SettingsRepositoryInterface::class);

        $storedPayload = null;

        $settings
            ->expects($this->once())
            ->method('set')
            ->with(
                ApiKeyStore::SETTING_KEY,
                $this->callback(
                    function (string $payload) use (&$storedPayload): bool {
                        $storedPayload = $payload;

                        return true;
                    }
                )
            );

        $store = new ApiKeyStore($settings, $cipher);

        $store->store('secret-api-key');

        $this->assertIsString($storedPayload);
        $this->assertNotSame('secret-api-key', $storedPayload);
        $this->assertSame(
            'secret-api-key',
            $cipher->decrypt($storedPayload)
        );
    }

    public function testStoredKeyIsReturned(): void
    {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn($cipher->encrypt('database-key'));

        $store = new ApiKeyStore($settings, $cipher);

        $this->assertSame('database-key', $store->get());
        $this->assertSame(
            ApiKeyStore::SOURCE_DATABASE,
            $store->source()
        );
        $this->assertTrue($store->isConfigured());
        $this->assertTrue($store->hasStoredKey());
    }

    public function testNoKeyReturnsNull(): void
    {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(SettingsRepositoryInterface::class);

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn(null);

        $store = new ApiKeyStore($settings, $cipher);

        $this->assertNull($store->get());
        $this->assertSame(ApiKeyStore::SOURCE_NONE, $store->source());
        $this->assertFalse($store->isConfigured());
    }

    public function testStoredKeyCanBeDeleted(): void
    {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(SettingsRepositoryInterface::class);

        $settings
            ->expects($this->once())
            ->method('delete')
            ->with(ApiKeyStore::SETTING_KEY);

        $store = new ApiKeyStore($settings, $cipher);

        $store->deleteStored();
    }

    public function testEmptyKeyIsRejected(): void
    {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $store = new ApiKeyStore($settings, $cipher);

        $this->expectException(InvalidArgumentException::class);

        $store->store('   ');
    }
}
