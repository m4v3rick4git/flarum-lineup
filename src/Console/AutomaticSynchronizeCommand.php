<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Wss\FlarumLineup\Scheduling\SyncScheduleManager;
use Wss\FlarumLineup\Sync\SquadSynchronizationRunner;
use Wss\FlarumLineup\Sync\SyncAlreadyRunningException;
use Wss\FlarumLineup\Sync\TeamSynchronizer;

final class AutomaticSynchronizeCommand extends AbstractCommand
{
    private SyncScheduleManager $scheduleManager;

    private TeamSynchronizer $teamSynchronizer;

    private SquadSynchronizationRunner $squadRunner;

    private LoggerInterface $logger;

    public function __construct(
        SyncScheduleManager $scheduleManager,
        TeamSynchronizer $teamSynchronizer,
        SquadSynchronizationRunner $squadRunner,
        LoggerInterface $logger
    ) {
        $this->scheduleManager = $scheduleManager;
        $this->teamSynchronizer = $teamSynchronizer;
        $this->squadRunner = $squadRunner;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('wss-lineup:auto-sync')
            ->setDescription(
                'Run due automatic WSS Lineup synchronizations.'
            );
    }

    protected function fire()
    {
        $failed = 0;

        if (
            $this->runTarget(
                SyncScheduleManager::TARGET_TEAMS
            ) === false
        ) {
            ++$failed;
        }

        if (
            $this->runTarget(
                SyncScheduleManager::TARGET_SQUADS
            ) === false
        ) {
            ++$failed;
        }

        return $failed > 0 ? 1 : 0;
    }

    private function runTarget(
        string $target
    ): ?bool {
        $slot = $this->scheduleManager
            ->claimDue($target);

        if ($slot === null) {
            return null;
        }

        try {
            if (
                $target
                === SyncScheduleManager::TARGET_TEAMS
            ) {
                $result = $this->teamSynchronizer
                    ->synchronize();

                $message = sprintf(
                    'Automatic team synchronization completed for slot %s. Provider: %s; received: %d; created: %d; updated: %d; deactivated: %d.',
                    $slot,
                    $result['provider'],
                    $result['received'],
                    $result['created'],
                    $result['updated'],
                    $result['deactivated']
                );
            } else {
                $result = $this->squadRunner->run();

                if ($result['failed'] > 0) {
                    throw new RuntimeException(
                        sprintf(
                            '%d squad synchronization(s) failed.',
                            $result['failed']
                        )
                    );
                }

                $message = sprintf(
                    'Automatic squad synchronization completed for slot %s. Provider: %s; teams: %d; received: %d; created: %d; updated: %d; deactivated: %d.',
                    $slot,
                    $result['provider'],
                    $result['teams'],
                    $result['received'],
                    $result['created'],
                    $result['updated'],
                    $result['deactivated']
                );
            }

            $this->scheduleManager
                ->recordSuccess($target);

            $this->logger->info($message);
            $this->info($message);

            return true;
        } catch (SyncAlreadyRunningException $exception) {
            $message = sprintf(
                'Automatic %s synchronization for slot %s was skipped because another WSS Lineup synchronization is already running.',
                $target,
                $slot
            );

            $this->scheduleManager
                ->recordSkipped($target);

            $this->logger->info($message);
            $this->info($message);

            return true;
        } catch (Throwable $exception) {
            $message = sprintf(
                'Automatic %s synchronization for slot %s failed: %s',
                $target,
                $slot,
                $exception->getMessage()
            );

            $this->scheduleManager
                ->recordFailure(
                    $target,
                    $exception->getMessage()
                );

            $this->logger->error(
                $message,
                ['exception' => $exception]
            );
            $this->error($message);

            return false;
        }
    }
}
