<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;

final class ApiKeyStore
{
    public const SETTING_KEY = 'wss-lineup.api_key';

    public const SOURCE_DATABASE = 'database';
    public const SOURCE_NONE = 'none';

    private SettingsRepositoryInterface $settings;

    public function __construct(
        SettingsRepositoryInterface $settings
    ) {
        $this->settings = $settings;
    }

    public function get(): ?string
    {
        return $this->storedValue();
    }

    public function store(string $apiKey): void
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'The API key must not be empty.'
            );
        }

        $this->settings->set(
            self::SETTING_KEY,
            $apiKey
        );
    }

    public function deleteStored(): void
    {
        $this->settings->delete(self::SETTING_KEY);
    }

    public function source(): string
    {
        return $this->hasStoredKey()
            ? self::SOURCE_DATABASE
            : self::SOURCE_NONE;
    }

    public function isConfigured(): bool
    {
        return $this->hasStoredKey();
    }

    public function hasStoredKey(): bool
    {
        return $this->storedValue() !== null;
    }

    private function storedValue(): ?string
    {
        $value = $this->settings->get(self::SETTING_KEY);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
