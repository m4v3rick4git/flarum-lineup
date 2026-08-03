<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Model\Player;
use Wss\FlarumLineup\Model\Team;

final class SquadSynchronizer
{
    private const PROVIDER = 'api-football';

    private ApiFootballClient $apiFootballClient;

    private ConnectionInterface $database;

    public function __construct(
        ApiFootballClient $apiFootballClient,
        ConnectionInterface $database
    ) {
        $this->apiFootballClient = $apiFootballClient;
        $this->database = $database;
    }

    /**
     * @return array{
     *     teams: int,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    public function synchronizeAll(): array
    {
        $teams = Team::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            throw new RuntimeException(
                'No active teams are available for squad synchronization.'
            );
        }

        $result = [
            'teams' => 0,
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        foreach ($teams as $team) {
            $teamResult = $this->synchronizeTeam($team);

            ++$result['teams'];
            $result['received'] += $teamResult['received'];
            $result['created'] += $teamResult['created'];
            $result['updated'] += $teamResult['updated'];
            $result['deactivated'] += $teamResult['deactivated'];
        }

        return $result;
    }

    /**
     * @return array{
     *     teamId: int,
     *     apiTeamId: int,
     *     teamName: string,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    public function synchronizeTeam(Team $team): array
    {
        if (
            !$team->exists
            || (int) $team->api_team_id <= 0
        ) {
            throw new InvalidArgumentException(
                'A persisted team with a valid API team ID is required.'
            );
        }

        $players = $this->apiFootballClient->fetchSquad(
            (int) $team->api_team_id
        );

        if ($players === []) {
            throw new RuntimeException(
                sprintf(
                    'API-Football returned an empty squad for team %d.',
                    $team->api_team_id
                )
            );
        }

        $now = Carbon::now();

        return $this->database->transaction(
            function () use (
                $team,
                $players,
                $now
            ): array {
                $created = 0;
                $updated = 0;
                $providerPlayerIds = [];

                foreach ($players as $playerData) {
                    $providerPlayerId = (string) (
                        $playerData['apiPlayerId']
                    );

                    $providerPlayerIds[] = $providerPlayerId;

                    $player = Player::query()->firstOrNew([
                        'provider' => self::PROVIDER,
                        'provider_player_id' => $providerPlayerId,
                    ]);

                    $wasRecentlyCreated = !$player->exists;

                    $player->fill([
                        'team_id' => $team->id,
                        'name' => $playerData['name'],
                        'age' => $playerData['age'],
                        'shirt_number' => (
                            $playerData['shirtNumber']
                        ),
                        'position' => $playerData['position'],
                        'photo_url' => $playerData['photoUrl'],
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);

                    $player->save();

                    if ($wasRecentlyCreated) {
                        ++$created;
                    } else {
                        ++$updated;
                    }
                }

                $deactivated = Player::query()
                    ->where('team_id', $team->id)
                    ->where('provider', self::PROVIDER)
                    ->whereNotIn(
                        'provider_player_id',
                        $providerPlayerIds
                    )
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'last_synced_at' => $now,
                    ]);

                return [
                    'teamId' => (int) $team->id,
                    'apiTeamId' => (int) $team->api_team_id,
                    'teamName' => (string) $team->name,
                    'received' => count($players),
                    'created' => $created,
                    'updated' => $updated,
                    'deactivated' => $deactivated,
                ];
            }
        );
    }
}
