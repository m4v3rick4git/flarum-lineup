<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\DataProvider\DataProviderInterface;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;

final class DataProviderResolverTest extends TestCase
{
    public function testApiFootballIsTheDefaultProvider(): void
    {
        $settings = $this->createSettings(null);
        $apiFootball = $this->createProvider('api-football');

        $resolver = new DataProviderResolver(
            $settings,
            [$apiFootball]
        );

        $this->assertSame(
            'api-football',
            $resolver->selectedProviderKey()
        );

        $this->assertSame(
            $apiFootball,
            $resolver->resolve()
        );
    }

    public function testConfiguredProviderIsResolved(): void
    {
        $settings = $this->createSettings('bundesliga-at');

        $apiFootball = $this->createProvider(
            'api-football'
        );

        $bundesligaAt = $this->createProvider(
            'bundesliga-at'
        );

        $resolver = new DataProviderResolver(
            $settings,
            [
                $apiFootball,
                $bundesligaAt,
            ]
        );

        $this->assertSame(
            'bundesliga-at',
            $resolver->selectedProviderKey()
        );

        $this->assertSame(
            $bundesligaAt,
            $resolver->resolve()
        );
    }

    public function testUnknownProviderIsRejected(): void
    {
        $resolver = new DataProviderResolver(
            $this->createSettings('unknown'),
            [
                $this->createProvider('api-football'),
            ]
        );

        $this->expectException(
            RuntimeException::class
        );

        $resolver->resolve();
    }

    public function testDuplicateProviderKeyIsRejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DataProviderResolver(
            $this->createSettings(null),
            [
                $this->createProvider('api-football'),
                $this->createProvider('api-football'),
            ]
        );
    }

    private function createSettings(
        ?string $providerKey
    ): SettingsRepositoryInterface {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(DataProviderResolver::SETTING_KEY)
            ->willReturn($providerKey);

        return $settings;
    }

    private function createProvider(
        string $providerKey
    ): DataProviderInterface {
        $provider = $this->createMock(
            DataProviderInterface::class
        );

        $provider
            ->method('providerKey')
            ->willReturn($providerKey);

        return $provider;
    }
}
