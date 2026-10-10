<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class BundesligaAtProvider implements
    DataProviderInterface
{
    public const PROVIDER_KEY = 'bundesliga-at';

    public const ROUTE_CACHE_SETTING =
        'wss-lineup.bundesliga_at_team_routes';

    private BundesligaAtSource $source;

    private BundesligaAtParser $parser;

    private SettingsRepositoryInterface $settings;

    public function __construct(
        BundesligaAtSource $source,
        BundesligaAtParser $parser,
        SettingsRepositoryInterface $settings
    ) {
        $this->source = $source;
        $this->parser = $parser;
        $this->settings = $settings;
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function fetchTeams(): array
    {
        $parsedTeams = $this->parser
            ->parseTeams(
                $this->source->fetchClubsHtml()
            );

        $routes = [];
        $teams = [];

        foreach ($parsedTeams as $team) {
            $providerTeamId = trim(
                $team['providerTeamId']
            );

            if (
                preg_match(
                    '/^[1-9][0-9]*$/D',
                    $providerTeamId
                ) !== 1
            ) {
                throw new RuntimeException(
                    'Bundesliga.at returned an invalid team ID.'
                );
            }

            if (isset($routes[$providerTeamId])) {
                throw new RuntimeException(
                    'Bundesliga.at returned a duplicate team ID.'
                );
            }

            $routes[$providerTeamId] =
                $team['squadPath'];

            $teams[] = [
                'providerTeamId' => $providerTeamId,
                'name' => $team['name'],
                'code' => $team['code'],
                'country' => $team['country'],
                'founded' => $team['founded'],
                'isNational' => $team['isNational'],
                'logoUrl' => $team['logoUrl'],
            ];
        }

        if ($teams === []) {
            throw new RuntimeException(
                'Bundesliga.at returned an empty team list.'
            );
        }

        $this->storeRoutes($routes);

        return $teams;
    }

    public function fetchSquad(
        string $providerTeamId
    ): array {
        $providerTeamId = trim(
            $providerTeamId
        );

        if (
            preg_match(
                '/^[1-9][0-9]*$/D',
                $providerTeamId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The Bundesliga.at team ID must be a positive integer.'
            );
        }

        $squadPath = $this->resolveSquadPath(
            $providerTeamId
        );

        $players = $this->parser
            ->parseSquad(
                $this->source->fetchSquadHtml(
                    $squadPath
                )
            );

        if ($players === []) {
            throw new RuntimeException(
                'Bundesliga.at returned an empty squad.'
            );
        }

        return $players;
    }

    private function resolveSquadPath(
        string $providerTeamId
    ): string {
        $routes = $this->readRoutes();

        if (
            isset($routes[$providerTeamId])
            && $this->isValidSquadPath(
                $routes[$providerTeamId]
            )
        ) {
            return $routes[$providerTeamId];
        }

        $this->fetchTeams();

        $routes = $this->readRoutes();

        if (
            !isset($routes[$providerTeamId])
            || !$this->isValidSquadPath(
                $routes[$providerTeamId]
            )
        ) {
            throw new InvalidArgumentException(
                'The Bundesliga.at team could not be resolved.'
            );
        }

        return $routes[$providerTeamId];
    }

    /**
     * @param array<string, string> $routes
     */
    private function storeRoutes(
        array $routes
    ): void {
        try {
            $encoded = json_encode(
                $routes,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The Bundesliga.at team routes could not be stored.',
                0,
                $exception
            );
        }

        $this->settings->set(
            self::ROUTE_CACHE_SETTING,
            $encoded
        );
    }

    /**
     * @return array<string, string>
     */
    private function readRoutes(): array
    {
        $value = $this->settings->get(
            self::ROUTE_CACHE_SETTING
        );

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            return [];
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $routes = [];

        foreach ($decoded as $teamId => $path) {
            if (
                !is_string($teamId)
                && !is_int($teamId)
            ) {
                continue;
            }

            if (!is_string($path)) {
                continue;
            }

            $teamId = (string) $teamId;

            if (
                preg_match(
                    '/^[1-9][0-9]*$/D',
                    $teamId
                ) !== 1
                || !$this->isValidSquadPath(
                    $path
                )
            ) {
                continue;
            }

            $routes[$teamId] = $path;
        }

        return $routes;
    }

    private function isValidSquadPath(
        string $path
    ): bool {
        return preg_match(
            '#^/de/team/[a-z0-9-]+/[1-9][0-9]*/kader$#D',
            $path
        ) === 1;
    }
}
