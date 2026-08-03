<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use Wss\FlarumLineup\Security\ApiKeyCipher;

final class ApiKeyStore
{
    public const SETTING_KEY = 'wss-lineup.api_key_encrypted';

    public const SOURCE_DATABASE = 'database';
    public const SOURCE_NONE = 'none';

    private SettingsRepositoryInterface $settings;

    private ApiKeyCipher $cipher;

    public function __construct(
        SettingsRepositoryInterface $settings,
        ApiKeyCipher $cipher
    ) {
        $this->settings = $settings;
        $this->cipher = $cipher;
    }

    public function get(): ?string
    {
        $storedPayload = $this->storedPayload();

        return $storedPayload !== null
            ? $this->cipher->decrypt($storedPayload)
            : null;
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
            $this->cipher->encrypt($apiKey)
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
        return $this->storedPayload() !== null;
    }

    private function storedPayload(): ?string
    {
        $value = $this->settings->get(self::SETTING_KEY);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
