<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Api\ApiFootballRequestException;
use Wss\FlarumLineup\Model\Team;
use Wss\FlarumLineup\Sync\SquadSynchronizer;

final class SynchronizeSquadsCommand extends AbstractCommand
{
    private const REQUEST_DELAY_SECONDS = 8;
    private const RATE_LIMIT_RETRY_SECONDS = 60;

    private SquadSynchronizer $squadSynchronizer;
    private LoggerInterface $logger;

    public function __construct(
        SquadSynchronizer $squadSynchronizer,
        LoggerInterface $logger
    ) {
        $this->squadSynchronizer = $squadSynchronizer;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('wss-lineup:sync-squads')
            ->setDescription(
                'Synchronize active team squads from API-Football with rate limiting.'
            );
    }

    protected function fire()
    {
        $teams = Team::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            $message = 'No active teams are available for squad synchronization.';

            $this->logger->error($message);
            $this->error($message);

            return 1;
        }

        $totalTeams = $teams->count();

        $aggregate = [
            'teams' => 0,
            'received' => 0,
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'failed' => 0,
        ];

        $this->logger->info(
            sprintf(
                'Starting scheduled squad synchronization for %d teams.',
                $totalTeams
            )
        );

        foreach ($teams as $index => $team) {
            $position = $index + 1;

            $this->info(
                sprintf(
                    '[%d/%d] Synchronizing %s...',
                    $position,
                    $totalTeams,
                    $team->name
                )
            );

            try {
                $result = $this->synchronizeWithRetry($team);

                ++$aggregate['teams'];
                $aggregate['received'] += $result['received'];
                $aggregate['created'] += $result['created'];
                $aggregate['updated'] += $result['updated'];
                $aggregate['deactivated'] += $result['deactivated'];
            } catch (ApiFootballRequestException $exception) {
                $message = sprintf(
                    'Squad synchronization aborted at %s: API-Football returned HTTP %d.',
                    $team->name,
                    $exception->statusCode()
                );

                $this->logger->error(
                    $message,
                    ['exception' => $exception]
                );
                $this->error($message);

                return 1;
            } catch (Throwable $exception) {
                ++$aggregate['failed'];

                $message = sprintf(
                    'Squad synchronization failed for %s: %s',
                    $team->name,
                    $exception->getMessage()
                );

                $this->logger->error(
                    $message,
                    ['exception' => $exception]
                );
                $this->error($message);
            }

            if ($position < $totalTeams) {
                sleep(self::REQUEST_DELAY_SECONDS);
            }
        }

        $summary = sprintf(
            'Squad synchronization completed. Teams: %d; received: %d; created: %d; updated: %d; deactivated: %d; failed: %d.',
            $aggregate['teams'],
            $aggregate['received'],
            $aggregate['created'],
            $aggregate['updated'],
            $aggregate['deactivated'],
            $aggregate['failed']
        );

        $this->logger->info($summary);
        $this->info($summary);

        return $aggregate['failed'] > 0 ? 1 : 0;
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
    private function synchronizeWithRetry(Team $team): array
    {
        try {
            return $this->squadSynchronizer->synchronizeTeam($team);
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

            $this->info(
                sprintf(
                    'Rate limit reached. Waiting %d seconds before one retry...',
                    $retryAfter
                )
            );

            sleep($retryAfter);

            return $this->squadSynchronizer->synchronizeTeam($team);
        }
    }
}
