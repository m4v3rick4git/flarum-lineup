<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api;

use RuntimeException;

final class ApiPayloadValidator
{
    private const MAX_UNSIGNED_INTEGER = 4294967295;

    private const IMAGE_HOST = 'media.api-sports.io';

    /**
     * @param array<string, mixed> $team
     *
     * @return array{
     *     apiTeamId: int,
     *     name: string,
     *     code: string|null,
     *     country: string|null,
     *     founded: int|null,
     *     isNational: bool,
     *     logoUrl: string|null
     * }
     */
    public function team(array $team): array
    {
        $apiTeamId = $this->requiredInteger(
            $team['id'] ?? null,
            1,
            self::MAX_UNSIGNED_INTEGER,
            'team ID'
        );

        $isNational = $team['national'] ?? false;

        if (!is_bool($isNational)) {
            throw new RuntimeException(
                'API-Football returned an invalid national-team flag.'
            );
        }

        return [
            'apiTeamId' => $apiTeamId,
            'name' => $this->requiredString(
                $team['name'] ?? null,
                150,
                'team name'
            ),
            'code' => $this->nullableString(
                $team['code'] ?? null,
                20,
                'team code'
            ),
            'country' => $this->nullableString(
                $team['country'] ?? null,
                100,
                'team country'
            ),
            'founded' => $this->nullableInteger(
                $team['founded'] ?? null,
                1800,
                ((int) date('Y')) + 1,
                'team founding year'
            ),
            'isNational' => $isNational,
            'logoUrl' => $this->nullableImageUrl(
                $team['logo'] ?? null,
                'teams',
                $apiTeamId,
                'team logo URL'
            ),
        ];
    }

    /**
     * @param array<string, mixed> $player
     *
     * @return array{
     *     apiPlayerId: int,
     *     name: string,
     *     age: int|null,
     *     shirtNumber: int|null,
     *     position: string|null,
     *     photoUrl: string|null
     * }
     */
    public function player(array $player): array
    {
        $apiPlayerId = $this->requiredInteger(
            $player['id'] ?? null,
            1,
            self::MAX_UNSIGNED_INTEGER,
            'player ID'
        );

        return [
            'apiPlayerId' => $apiPlayerId,
            'name' => $this->requiredString(
                $player['name'] ?? null,
                150,
                'player name'
            ),
            'age' => $this->nullableInteger(
                $player['age'] ?? null,
                14,
                65,
                'player age'
            ),
            'shirtNumber' => $this->nullableInteger(
                $player['number'] ?? null,
                1,
                999,
                'shirt number'
            ),
            'position' => $this->nullableString(
                $player['position'] ?? null,
                50,
                'player position'
            ),
            'photoUrl' => $this->nullableImageUrl(
                $player['photo'] ?? null,
                'players',
                $apiPlayerId,
                'player photo URL'
            ),
        ];
    }

    /**
     * @param mixed $value
     */
    private function requiredString(
        $value,
        int $maximumLength,
        string $label
    ): string {
        $value = $this->nullableString(
            $value,
            $maximumLength,
            $label
        );

        if ($value === null) {
            throw $this->invalid($label);
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function nullableString(
        $value,
        int $maximumLength,
        string $label
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            !is_string($value)
            || !mb_check_encoding($value, 'UTF-8')
        ) {
            throw $this->invalid($label);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value, 'UTF-8') > $maximumLength
            || preg_match('/\p{Cc}/u', $value) === 1
        ) {
            throw $this->invalid($label);
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function requiredInteger(
        $value,
        int $minimum,
        int $maximum,
        string $label
    ): int {
        $value = $this->nullableInteger(
            $value,
            $minimum,
            $maximum,
            $label
        );

        if ($value === null) {
            throw $this->invalid($label);
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function nullableInteger(
        $value,
        int $minimum,
        int $maximum,
        string $label
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT);

        if (
            $value === false
            || $value < $minimum
            || $value > $maximum
        ) {
            throw $this->invalid($label);
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function nullableImageUrl(
        $value,
        string $directory,
        int $identifier,
        string $label
    ): ?string {
        $url = $this->nullableString(
            $value,
            500,
            $label
        );

        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? ''))
                !== 'https'
            || strtolower((string) ($parts['host'] ?? ''))
                !== self::IMAGE_HOST
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
            || (string) ($parts['path'] ?? '')
                !== sprintf(
                    '/football/%s/%d.png',
                    $directory,
                    $identifier
                )
        ) {
            throw $this->invalid($label);
        }

        return $url;
    }

    private function invalid(string $label): RuntimeException
    {
        return new RuntimeException(
            sprintf(
                'API-Football returned an invalid %s.',
                $label
            )
        );
    }
}
