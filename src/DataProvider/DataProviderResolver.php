<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

final class DataProviderResolver
{
    public const SETTING_KEY = 'wss-lineup.data_provider';

    public const DEFAULT_PROVIDER = 'api-football';

    private SettingsRepositoryInterface $settings;

    /**
     * @var array<string, DataProviderInterface>
     */
    private array $providers = [];

    /**
     * @param array<int, DataProviderInterface> $providers
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        array $providers
    ) {
        $this->settings = $settings;

        foreach ($providers as $provider) {
            $key = trim($provider->providerKey());

            if ($key === '') {
                throw new InvalidArgumentException(
                    'A data provider must have a non-empty key.'
                );
            }

            if (isset($this->providers[$key])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The data provider "%s" is registered more than once.',
                        $key
                    )
                );
            }

            $this->providers[$key] = $provider;
        }
    }

    public function resolve(): DataProviderInterface
    {
        $key = $this->selectedProviderKey();

        if (!isset($this->providers[$key])) {
            throw new RuntimeException(
                sprintf(
                    'The configured data provider "%s" is not available.',
                    $key
                )
            );
        }

        return $this->providers[$key];
    }

    public function selectedProviderKey(): string
    {
        $value = $this->settings->get(self::SETTING_KEY);

        if (!is_string($value)) {
            return self::DEFAULT_PROVIDER;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : self::DEFAULT_PROVIDER;
    }
}
