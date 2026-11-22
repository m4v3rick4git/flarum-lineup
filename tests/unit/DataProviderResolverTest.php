<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\DataProvider\BundesligaAtProvider;
use Wss\FlarumLineup\DataProvider\DataProviderInterface;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;

final class DataProviderResolverTest extends TestCase
{
    public function testBundesligaAtIsTheDefaultProvider(): void
    {
        $bundesliga = $this->createProvider('bundesliga-at');

        $resolver = new DataProviderResolver([$bundesliga]);

        $this->assertSame(
            BundesligaAtProvider::PROVIDER_KEY,
            $resolver->selectedProviderKey()
        );

        $this->assertSame($bundesliga, $resolver->resolve());
    }

    public function testAdditionalProviderCanBeResolvedExplicitly(): void
    {
        $bundesliga = $this->createProvider('bundesliga-at');
        $additional = $this->createProvider('additional-provider');

        $resolver = new DataProviderResolver([
            $bundesliga,
            $additional,
        ]);

        $this->assertSame($bundesliga, $resolver->resolve());

        $this->assertSame(
            $additional,
            $resolver->resolve('additional-provider')
        );

        $this->assertSame(
            'bundesliga-at',
            $resolver->selectedProviderKey()
        );
    }

    public function testUnknownProviderIsRejected(): void
    {
        $resolver = new DataProviderResolver([
            $this->createProvider('bundesliga-at'),
        ]);

        $this->expectException(RuntimeException::class);

        $resolver->resolve('unknown-provider');
    }

    public function testDuplicateProviderKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataProviderResolver([
            $this->createProvider('bundesliga-at'),
            $this->createProvider('bundesliga-at'),
        ]);
    }

    public function testMissingBundesligaProviderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataProviderResolver([
            $this->createProvider('additional-provider'),
        ]);
    }

    public function testEmptyProviderKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataProviderResolver([
            $this->createProvider('bundesliga-at'),
            $this->createProvider('   '),
        ]);
    }

    private function createProvider(
        string $providerKey
    ): DataProviderInterface {
        $provider = $this->createStub(
            DataProviderInterface::class
        );

        $provider
            ->method('providerKey')
            ->willReturn($providerKey);

        return $provider;
    }
}
