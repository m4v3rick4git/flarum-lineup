<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Api\ApiKeyStore;

final class ApiKeyStoreTest extends TestCase
{
    public function testItStoresTheApiKey(): void
    {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->expects($this->once())
            ->method('set')
            ->with(
                ApiKeyStore::SETTING_KEY,
                'secret-api-key'
            );

        $store = new ApiKeyStore($settings);

        $store->store('secret-api-key');
    }

    public function testStoredKeyIsReturned(): void
    {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn('database-key');

        $store = new ApiKeyStore($settings);

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
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn(null);

        $store = new ApiKeyStore($settings);

        $this->assertNull($store->get());
        $this->assertSame(
            ApiKeyStore::SOURCE_NONE,
            $store->source()
        );
        $this->assertFalse($store->isConfigured());
        $this->assertFalse($store->hasStoredKey());
    }

    public function testWhitespaceOnlyStoredValueIsTreatedAsMissing(): void
    {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn('   ');

        $store = new ApiKeyStore($settings);

        $this->assertNull($store->get());
        $this->assertFalse($store->isConfigured());
    }

    public function testStoredKeyCanBeDeleted(): void
    {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->expects($this->once())
            ->method('delete')
            ->with(ApiKeyStore::SETTING_KEY);

        $store = new ApiKeyStore($settings);

        $store->deleteStored();
    }

    public function testEmptyKeyIsRejected(): void
    {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $store = new ApiKeyStore($settings);

        $this->expectException(
            InvalidArgumentException::class
        );

        $store->store('   ');
    }
}
