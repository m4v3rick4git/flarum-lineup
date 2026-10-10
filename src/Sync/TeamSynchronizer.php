<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;
use Wss\FlarumLineup\Image\RemoteImageCacheService;
use Wss\FlarumLineup\Model\Team;

final class TeamSynchronizer
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
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    public function synchronize(): array
    {
        return $this->syncLockManager->run(
            fn (): array => $this->synchronizeUnlocked()
        );
    }

    /**
     * @return array{
     *     provider: string,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    private function synchronizeUnlocked(): array
    {
        $provider = $this->dataProviderResolver->resolve();
        $providerKey = $provider->providerKey();
        $teams = $provider->fetchTeams();

        foreach ($teams as &$teamData) {
            $providerTeamId = trim(
                (string) $teamData['providerTeamId']
            );

            if ($providerTeamId === '') {
                throw new InvalidArgumentException(
                    'A data provider returned an empty team ID.'
                );
            }

            $teamData['providerTeamId'] = $providerTeamId;
        }

        unset($teamData);

        $existingActiveTeams = (int) Team::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->count();

        $this->syncSafetyGuard
            ->assertTeamResponseIsComplete(
                count($teams),
                $existingActiveTeams
            );

        if ($this->remoteImageCache !== null) {
            foreach ($teams as $teamData) {
                $this->remoteImageCache->cacheTeamLogo(
                    $providerKey,
                    $teamData['providerTeamId'],
                    $teamData['logoUrl']
                );
            }
        }

        $now = Carbon::now();

        return $this->database->transaction(
            function () use (
                $teams,
                $providerKey,
                $now
            ): array {
                $created = 0;
                $updated = 0;
                $providerTeamIds = [];

                foreach ($teams as $teamData) {
                    $providerTeamId = $teamData['providerTeamId'];
                    $providerTeamIds[] = $providerTeamId;

                    $team = Team::query()->firstOrNew([
                        'provider' => $providerKey,
                        'provider_team_id' => $providerTeamId,
                    ]);

                    $wasRecentlyCreated = !$team->exists;

                    $team->fill([
                        'provider' => $providerKey,
                        'provider_team_id' => $providerTeamId,
                        'name' => $teamData['name'],
                        'code' => $teamData['code'],
                        'country' => $teamData['country'],
                        'founded' => $teamData['founded'],
                        'is_national' => $teamData['isNational'],
                        'logo_url' => $teamData['logoUrl'],
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);

                    $team->save();

                    if ($wasRecentlyCreated) {
                        ++$created;
                    } else {
                        ++$updated;
                    }
                }

                $deactivated = Team::query()
                    ->where('provider', $providerKey)
                    ->whereNotIn('provider_team_id', $providerTeamIds)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'last_synced_at' => $now,
                    ]);

                return [
                    'provider' => $providerKey,
                    'received' => count($teams),
                    'created' => $created,
                    'updated' => $updated,
                    'deactivated' => $deactivated,
                ];
            }
        );
    }
}