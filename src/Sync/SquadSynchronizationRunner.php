<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Wss\FlarumLineup\Api\ApiFootballRequestException;
use Wss\FlarumLineup\DataProvider\ApiFootballProvider;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;
use Wss\FlarumLineup\Model\Team;

final class SquadSynchronizationRunner
{
    private const API_FOOTBALL_REQUEST_DELAY_SECONDS = 8;

    private const OTHER_PROVIDER_REQUEST_DELAY_SECONDS = 1;

    private const RATE_LIMIT_RETRY_SECONDS = 60;

    private const LOCK_RETRY_SECONDS = 5;

    private SquadSynchronizer $squadSynchronizer;

    private DataProviderResolver $dataProviderResolver;

    private LoggerInterface $logger;

    public function __construct(
        SquadSynchronizer $squadSynchronizer,
        DataProviderResolver $dataProviderResolver,
        LoggerInterface $logger
    ) {
        $this->squadSynchronizer = $squadSynchronizer;
        $this->dataProviderResolver = $dataProviderResolver;
        $this->logger = $logger;
    }

    /**
     * @param callable(int, int, string): void|null $progress
     *
     * @return array{
     *     provider: string,
     *     teams: int,
     *     received: int,
     *     created: int,
     *     updated: int,
     *     deactivated: int,
     *     failed: int
     * }
     */
    public function run(
        ?callable $progress = null
    ): array {
        $providerKey = $this->dataProviderResolver
            ->resolve()
            ->providerKey();

        $requestDelaySeconds = (
            $providerKey === ApiFootballProvider::PROVIDER_KEY
        )
            ? self::API_FOOTBALL_REQUEST_DELAY_SECONDS
            : self::OTHER_PROVIDER_REQUEST_DELAY_SECONDS;

        $teams = Team::query()
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            throw new RuntimeException(
                'No active teams are available for squad synchronization.'
            );
        }

        $totalTeams = $teams->count();

        $aggregate = [
            'provider' => $providerKey,
            'teams' => 0,
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'failed' => 0,
        ];

        foreach ($teams as $index => $team) {
            $position = $index + 1;

            if ($progress !== null) {
                $progress(
                    $position,
                    $totalTeams,
                    (string) $team->name
                );
            }

            try {
                $result = $this->synchronizeWithRetry(
                    $team
                );

                ++$aggregate['teams'];
                $aggregate['received'] += $result['received'];
                $aggregate['created'] += $result['created'];
                $aggregate['updated'] += $result['updated'];
                $aggregate['deactivated'] += $result['deactivated'];
            } catch (ApiFootballRequestException $exception) {
                $this->logger->error(
                    sprintf(
                        'Squad synchronization aborted at %s: API-Football returned HTTP %d.',
                        $team->name,
                        $exception->statusCode()
                    ),
                    ['exception' => $exception]
                );

                throw $exception;
            } catch (Throwable $exception) {
                ++$aggregate['failed'];

                $this->logger->error(
                    sprintf(
                        'Squad synchronization failed for %s: %s',
                        $team->name,
                        $exception->getMessage()
                    ),
                    ['exception' => $exception]
                );
            }

            if ($position < $totalTeams) {
                sleep($requestDelaySeconds);
            }
        }

        return $aggregate;
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
    private function synchronizeWithRetry(
        Team $team
    ): array {
        try {
            return $this->squadSynchronizer
                ->synchronizeTeam($team);
        } catch (SyncAlreadyRunningException $exception) {
            sleep(self::LOCK_RETRY_SECONDS);

            return $this->squadSynchronizer
                ->synchronizeTeam($team);
        } catch (ApiFootballRequestException $exception) {
            if ($exception->statusCode() !== 429) {
                throw $exception;
            }

            $retryAfter = max(
                self::RATE_LIMIT_RETRY_SECONDS,
                $exception->retryAfterSeconds() ?? 0
            );

            $this->logger->warning(
                sprintf(
                    'API-Football rate limit reached while synchronizing %s. Retrying after %d seconds.',
                    $team->name,
                    $retryAfter
                )
            );

            sleep($retryAfter);

            return $this->squadSynchronizer
                ->synchronizeTeam($team);
        }
    }
}
