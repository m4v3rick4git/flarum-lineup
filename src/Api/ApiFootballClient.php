<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class ApiFootballClient
{
    private ClientInterface $httpClient;

    private ApiKeyStore $apiKeyStore;

    public function __construct(
        ClientInterface $httpClient,
        ApiKeyStore $apiKeyStore
    ) {
        $this->httpClient = $httpClient;
        $this->apiKeyStore = $apiKeyStore;
    }

    /**
     * @return array{
     *     active: bool,
     *     plan: string|null,
     *     requestsCurrent: int|null,
     *     requestsLimitDay: int|null
     * }
     */
    public function testConnection(): array
    {
        $data = $this->request('status');

        $payload = $data['response'] ?? null;

        if (!is_array($payload)) {
            throw new RuntimeException(
                'API-Football returned no status information.'
            );
        }

        $subscription = is_array(
            $payload['subscription'] ?? null
        )
            ? $payload['subscription']
            : [];

        $requests = is_array($payload['requests'] ?? null)
            ? $payload['requests']
            : [];

        return [
            'active' => (bool) ($subscription['active'] ?? false),
            'plan' => $this->nullableString(
                $subscription['plan'] ?? null
            ),
            'requestsCurrent' => $this->nullableInteger(
                $requests['current'] ?? null
            ),
            'requestsLimitDay' => $this->nullableInteger(
                $requests['limit_day'] ?? null
            ),
        ];
    }

    /**
     * @return array<int, array{
     *     apiTeamId: int,
     *     name: string,
     *     code: string|null,
     *     country: string|null,
     *     founded: int|null,
     *     isNational: bool,
     *     logoUrl: string|null
     * }>
     */
    public function fetchTeams(
        int $leagueId,
        int $season
    ): array {
        if ($leagueId <= 0) {
            throw new InvalidArgumentException(
                'The league ID must be greater than zero.'
            );
        }

        if ($season < 1900 || $season > 2100) {
            throw new InvalidArgumentException(
                'The season must be a valid four-digit year.'
            );
        }

        $data = $this->request(
            'teams',
            [
                'league' => $leagueId,
                'season' => $season,
            ]
        );

        $response = $data['response'] ?? null;

        if (!is_array($response)) {
            throw new RuntimeException(
                'API-Football returned no team list.'
            );
        }

        $teams = [];

        foreach ($response as $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    'API-Football returned an invalid team entry.'
                );
            }

            $team = $item['team'] ?? null;

            if (!is_array($team)) {
                throw new RuntimeException(
                    'API-Football returned an invalid team object.'
                );
            }

            $apiTeamId = $this->nullableInteger(
                $team['id'] ?? null
            );

            $name = $this->nullableString(
                $team['name'] ?? null
            );

            if ($apiTeamId === null || $apiTeamId <= 0) {
                throw new RuntimeException(
                    'API-Football returned a team without a valid ID.'
                );
            }

            if ($name === null) {
                throw new RuntimeException(
                    'API-Football returned a team without a name.'
                );
            }

            $teams[] = [
                'apiTeamId' => $apiTeamId,
                'name' => $name,
                'code' => $this->nullableString(
                    $team['code'] ?? null
                ),
                'country' => $this->nullableString(
                    $team['country'] ?? null
                ),
                'founded' => $this->nullableInteger(
                    $team['founded'] ?? null
                ),
                'isNational' => (bool) (
                    $team['national'] ?? false
                ),
                'logoUrl' => $this->nullableString(
                    $team['logo'] ?? null
                ),
            ];
        }

        return $teams;
    }

    /**
     * @return array<int, array{
     *     apiPlayerId: int,
     *     name: string,
     *     age: int|null,
     *     shirtNumber: int|null,
     *     position: string|null,
     *     photoUrl: string|null
     * }>
     */
    public function fetchSquad(int $apiTeamId): array
    {
        if ($apiTeamId <= 0) {
            throw new InvalidArgumentException(
                'The team ID must be greater than zero.'
            );
        }

        $data = $this->request(
            'players/squads',
            [
                'team' => $apiTeamId,
            ]
        );

        $response = $data['response'] ?? null;

        if (!is_array($response)) {
            throw new RuntimeException(
                'API-Football returned no squad information.'
            );
        }

        $players = [];

        foreach ($response as $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    'API-Football returned an invalid squad entry.'
                );
            }

            $squad = $item['players'] ?? null;

            if (!is_array($squad)) {
                throw new RuntimeException(
                    'API-Football returned an invalid player list.'
                );
            }

            foreach ($squad as $player) {
                if (!is_array($player)) {
                    throw new RuntimeException(
                        'API-Football returned an invalid player entry.'
                    );
                }

                $apiPlayerId = $this->nullableInteger(
                    $player['id'] ?? null
                );

                $name = $this->nullableString(
                    $player['name'] ?? null
                );

                if ($apiPlayerId === null || $apiPlayerId <= 0) {
                    throw new RuntimeException(
                        'API-Football returned a player without a valid ID.'
                    );
                }

                if ($name === null) {
                    throw new RuntimeException(
                        'API-Football returned a player without a name.'
                    );
                }

                $players[$apiPlayerId] = [
                    'apiPlayerId' => $apiPlayerId,
                    'name' => $name,
                    'age' => $this->nullableInteger(
                        $player['age'] ?? null
                    ),
                    'shirtNumber' => $this->nullableInteger(
                        $player['number'] ?? null
                    ),
                    'position' => $this->nullableString(
                        $player['position'] ?? null
                    ),
                    'photoUrl' => $this->nullableString(
                        $player['photo'] ?? null
                    ),
                ];
            }
        }

        if ($players === []) {
            throw new RuntimeException(
                'API-Football returned an empty squad.'
            );
        }

        return array_values($players);
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return array<string, mixed>
     */
    private function request(
        string $endpoint,
        array $query = []
    ): array {
        $apiKey = $this->apiKeyStore->get();

        if ($apiKey === null || trim($apiKey) === '') {
            throw new RuntimeException(
                'The API-Football API key is not configured.'
            );
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $endpoint,
                [
                    'headers' => [
                        'x-apisports-key' => $apiKey,
                    ],
                    'query' => $query,
                ]
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The API-Football request failed.',
                0,
                $exception
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $retryAfter = trim(
                $response->getHeaderLine('Retry-After')
            );

            $retryAfterSeconds = ctype_digit($retryAfter)
                ? max(1, (int) $retryAfter)
                : null;

            throw new ApiFootballRequestException(
                $statusCode,
                $retryAfterSeconds
            );
        }

        try {
            $data = json_decode(
                (string) $response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'API-Football returned invalid JSON.',
                0,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new RuntimeException(
                'API-Football returned an invalid response.'
            );
        }

        $errors = $data['errors'] ?? [];

        if (
            (is_array($errors) && $errors !== []) ||
            (!is_array($errors) && $errors !== null && $errors !== '')
        ) {
            $errorDetail = is_array($errors)
                ? json_encode(
                    $errors,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
                : (string) $errors;

            if (!is_string($errorDetail) || $errorDetail === '') {
                $errorDetail = 'Unknown API error';
            }

            throw new RuntimeException(
                'API-Football rejected the request: '
                .mb_substr($errorDetail, 0, 500)
            );
        }

        return $data;
    }

    private function nullableString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private function nullableInteger($value): ?int
    {
        return is_numeric($value)
            ? (int) $value
            : null;
    }
}
