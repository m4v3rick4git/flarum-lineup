<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Model\Team;

final class TeamSynchronizer
{
    private const LEAGUE_ID_SETTING =
        'wss-lineup.league_id';

    private const SEASON_SETTING =
        'wss-lineup.season';

    private ApiFootballClient $apiFootballClient;

    private SettingsRepositoryInterface $settings;

    private ConnectionInterface $database;

    private SyncSafetyGuard $syncSafetyGuard;

    private SyncLockManager $syncLockManager;

    public function __construct(
        ApiFootballClient $apiFootballClient,
        SettingsRepositoryInterface $settings,
        ConnectionInterface $database,
        ?SyncSafetyGuard $syncSafetyGuard = null,
        ?SyncLockManager $syncLockManager = null
    ) {
        $this->apiFootballClient = $apiFootballClient;
        $this->settings = $settings;
        $this->database = $database;
        $this->syncSafetyGuard = $syncSafetyGuard
            ?? new SyncSafetyGuard();
        $this->syncLockManager = $syncLockManager
            ?? new SyncLockManager($database);
    }

    /**
     * @return array{
     *     leagueId: int,
     *     season: int,
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
     *     leagueId: int,
     *     season: int,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int
     * }
     */
    private function synchronizeUnlocked(): array
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

        $existingActiveTeams = (int) Team::query()
            ->where('is_active', true)
            ->count();

        $this->syncSafetyGuard
            ->assertTeamResponseIsComplete(
                count($teams),
                $existingActiveTeams
            );

        $now = Carbon::now();

        return $this->database->transaction(
            function () use (
                $teams,
                $leagueId,
                $season,
                $now
            ): array {
                $created = 0;
                $updated = 0;
                $apiTeamIds = [];

                foreach ($teams as $teamData) {
                    $apiTeamId = $teamData['apiTeamId'];
                    $apiTeamIds[] = $apiTeamId;

                    $team = Team::query()->firstOrNew([
                        'api_team_id' => $apiTeamId,
                    ]);

                    $wasRecentlyCreated = !$team->exists;

                    $team->fill([
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
                    ->whereNotIn('api_team_id', $apiTeamIds)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'last_synced_at' => $now,
                    ]);

                return [
                    'leagueId' => $leagueId,
                    'season' => $season,
                    'received' => count($teams),
                    'created' => $created,
                    'updated' => $updated,
                    'deactivated' => $deactivated,
                ];
            }
        );
    }

    private function readPositiveIntegerSetting(
        string $key,
        string $label
    ): int {
        $value = $this->settings->get($key);

        if (
            !is_string($value) &&
            !is_int($value)
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
