<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;
use Wss\FlarumLineup\DataProvider\DataProviderInterface;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;
use Wss\FlarumLineup\Image\RemoteImageCacheService;
use Wss\FlarumLineup\Model\Player;
use Wss\FlarumLineup\Model\Team;

final class SquadSynchronizer
{
    private DataProviderResolver $dataProviderResolver;

    private ConnectionInterface $database;

    private SyncSafetyGuard $syncSafetyGuard;

    private SyncLockManager $syncLockManager;

    private ?RemoteImageCacheService $remoteImageCache;

    public function __construct(
        DataProviderResolver $dataProviderResolver,
        ConnectionInterface $database,
        ?SyncSafetyGuard $syncSafetyGuard = null,
        ?SyncLockManager $syncLockManager = null,
        ?RemoteImageCacheService $remoteImageCache = null
    ) {
        $this->dataProviderResolver = $dataProviderResolver;
        $this->database = $database;
        $this->syncSafetyGuard = $syncSafetyGuard
            ?? new SyncSafetyGuard();
        $this->syncLockManager = $syncLockManager
            ?? new SyncLockManager($database);
        $this->remoteImageCache = $remoteImageCache;
    }

    /**
     * @return array{
     *     provider: string,
     *     teams: int,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    public function synchronizeAll(): array
    {
        return $this->syncLockManager->run(
            fn (): array => $this->synchronizeAllUnlocked()
        );
    }

    /**
     * @return array{
     *     provider: string,
     *     teams: int,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    private function synchronizeAllUnlocked(): array
    {
        $provider = $this->dataProviderResolver->resolve();
        $providerKey = $provider->providerKey();

        $teams = Team::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            throw new RuntimeException(
                'No active teams are available for the selected data provider.'
            );
        }

        $result = [
            'provider' => $providerKey,
            'teams' => 0,
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        foreach ($teams as $team) {
            $teamResult = $this->synchronizeTeamWithProvider(
                $team,
                $provider,
                $providerKey
            );

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
     *     provider: string,
     *     providerTeamId: string,
     *     teamName: string,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    public function synchronizeTeam(Team $team): array
    {
        return $this->syncLockManager->run(
            fn (): array => $this->synchronizeTeamUnlocked($team)
        );
    }

    /**
     * @return array{
     *     teamId: int,
     *     provider: string,
     *     providerTeamId: string,
     *     teamName: string,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    private function synchronizeTeamUnlocked(Team $team): array
    {
        $provider = $this->dataProviderResolver->resolve();

        return $this->synchronizeTeamWithProvider(
            $team,
            $provider,
            $provider->providerKey()
        );
    }

    /**
     * @return array{
     *     teamId: int,
     *     provider: string,
     *     providerTeamId: string,
     *     teamName: string,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    private function synchronizeTeamWithProvider(
        Team $team,
        DataProviderInterface $provider,
        string $providerKey
    ): array {
        $providerTeamId = trim((string) $team->provider_team_id);

        if (
            !$team->exists
            || (string) $team->provider !== $providerKey
            || $providerTeamId === ''
        ) {
            throw new InvalidArgumentException(
                'A persisted team belonging to the selected data provider is required.'
            );
        }

        $players = $provider->fetchSquad($providerTeamId);

        foreach ($players as &$playerData) {
            $providerPlayerId = trim(
                (string) $playerData['providerPlayerId']
            );

            if ($providerPlayerId === '') {
                throw new InvalidArgumentException(
                    'A data provider returned an empty player ID.'
                );
            }

            $playerData['providerPlayerId'] = $providerPlayerId;
        }

        unset($playerData);

        $existingActivePlayers = (int) Player::query()
            ->where('team_id', $team->id)
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->count();

        $this->syncSafetyGuard
            ->assertSquadResponseIsComplete(
                count($players),
                $existingActivePlayers
            );

        if ($this->remoteImageCache !== null) {
            foreach ($players as $playerData) {
                $this->remoteImageCache->cachePlayerPhoto(
                    $providerKey,
                    $playerData['providerPlayerId'],
                    $playerData['photoUrl']
                );
            }
        }

        $now = Carbon::now();

        return $this->database->transaction(
            function () use (
                $team,
                $players,
                $providerKey,
                $providerTeamId,
                $now
            ): array {
                $created = 0;
                $updated = 0;
                $providerPlayerIds = [];

                foreach ($players as $playerData) {
                    $providerPlayerId = $playerData['providerPlayerId'];
                    $providerPlayerIds[] = $providerPlayerId;

                    $player = Player::query()->firstOrNew([
                        'provider' => $providerKey,
                        'provider_player_id' => $providerPlayerId,
                    ]);

                    $wasRecentlyCreated = !$player->exists;

                    $player->fill([
                        'team_id' => $team->id,
                        'provider' => $providerKey,
                        'provider_player_id' => $providerPlayerId,
                        'name' => $playerData['name'],
                        'age' => $playerData['age'],
                        'shirt_number' => $playerData['shirtNumber'],
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
                    ->where('provider', $providerKey)
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
                    'provider' => $providerKey,
                    'providerTeamId' => $providerTeamId,
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