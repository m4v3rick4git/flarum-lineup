<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use InvalidArgumentException;
use RuntimeException;

/**
 * Extend by registering a new DataProviderInterface in the service provider.
 * v0.2.0 intentionally does not expose a provider-switching UI.
 */
final class DataProviderResolver
{
    public const DEFAULT_PROVIDER = BundesligaAtProvider::PROVIDER_KEY;

    /** @var array<string, DataProviderInterface> */
    private array $providers = [];

    /** @param array<int, DataProviderInterface> $providers */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $key = trim($provider->providerKey());
            if ($key === '' || isset($this->providers[$key])) {
                throw new InvalidArgumentException('Provider ID missing or registered twice.');
            }
            $this->providers[$key] = $provider;
        }
        if (!isset($this->providers[self::DEFAULT_PROVIDER])) {
            throw new InvalidArgumentException('Bundesliga.at must be available in v0.2.0.');
        }
    }

    public function resolve(?string $providerKey = null): DataProviderInterface
    {
        $key = $providerKey ?? self::DEFAULT_PROVIDER;
        if (!isset($this->providers[$key])) {
            throw new RuntimeException('The requested lineup provider is not registered.');
        }
        return $this->providers[$key];
    }

    public function selectedProviderKey(): string
    {
        return self::DEFAULT_PROVIDER;
    }
}
