<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use Wss\FlarumLineup\Api\ApiFootballClient;

final class ApiFootballProvider implements DataProviderInterface
{
    public const PROVIDER_KEY = 'api-football';

    private const LEAGUE_ID_SETTING =
        'wss-lineup.league_id';

    private const SEASON_SETTING =
        'wss-lineup.season';

    private ApiFootballClient $apiFootballClient;

    private SettingsRepositoryInterface $settings;

    public function __construct(
        ApiFootballClient $apiFootballClient,
        SettingsRepositoryInterface $settings
    ) {
        $this->apiFootballClient = $apiFootballClient;
        $this->settings = $settings;
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function fetchTeams(): array
    {
        $leagueId = $this->readPositiveIntegerSetting(
            self::LEAGUE_ID_SETTING,
            'league ID'
        );

        $season = $this->readPositiveIntegerSetting(
            self::SEASON_SETTING,
            'season'
        );

        if ($season < 1900 || $season > 2100) {
            throw new InvalidArgumentException(
                'The configured season is invalid.'
            );
        }

        $teams = $this->apiFootballClient->fetchTeams(
            $leagueId,
            $season
        );

        return array_map(
            static function (array $team): array {
                return [
                    'providerTeamId' => (string) $team['apiTeamId'],
                    'name' => $team['name'],
                    'code' => $team['code'],
                    'country' => $team['country'],
                    'founded' => $team['founded'],
                    'isNational' => $team['isNational'],
                    'logoUrl' => $team['logoUrl'],
                ];
            },
            $teams
        );
    }

    public function fetchSquad(string $providerTeamId): array
    {
        $apiTeamId = filter_var(
            $providerTeamId,
            FILTER_VALIDATE_INT
        );

        if ($apiTeamId === false || $apiTeamId <= 0) {
            throw new InvalidArgumentException(
                'The provider team ID must be a positive integer.'
            );
        }

        $players = $this->apiFootballClient->fetchSquad(
            $apiTeamId
        );

        return array_map(
            static function (array $player): array {
                return [
                    'providerPlayerId' => (
                        (string) $player['apiPlayerId']
                    ),
                    'name' => $player['name'],
                    'age' => $player['age'],
                    'shirtNumber' => $player['shirtNumber'],
                    'position' => $player['position'],
                    'photoUrl' => $player['photoUrl'],
                ];
            },
            $players
        );
    }

    private function readPositiveIntegerSetting(
        string $key,
        string $label
    ): int {
        $value = $this->settings->get($key);

        if (
            !is_string($value)
            && !is_int($value)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The configured %s is missing.',
                    $label
                )
            );
        }

        $value = filter_var(
            $value,
            FILTER_VALIDATE_INT
        );

        if ($value === false || $value <= 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'The configured %s is invalid.',
                    $label
                )
            );
        }

        return $value;
    }
}
