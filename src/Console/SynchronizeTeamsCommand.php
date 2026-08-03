<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Sync\TeamSynchronizer;

final class SynchronizeTeamsCommand extends AbstractCommand
{
    private TeamSynchronizer $teamSynchronizer;
    private LoggerInterface $logger;

    public function __construct(
        TeamSynchronizer $teamSynchronizer,
        LoggerInterface $logger
    ) {
        $this->teamSynchronizer = $teamSynchronizer;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('wss-lineup:sync-teams')
            ->setDescription(
                'Synchronize configured league teams from API-Football.'
            );
    }

    protected function fire()
    {
        try {
            $result = $this->teamSynchronizer->synchronize();

            $summary = sprintf(
                'Team synchronization completed. League: %d; season: %d; received: %d; created: %d; updated: %d; deactivated: %d.',
                $result['leagueId'],
                $result['season'],
                $result['received'],
                $result['created'],
                $result['updated'],
                $result['deactivated']
            );

            $this->logger->info($summary);
            $this->info($summary);

            return 0;
        } catch (Throwable $exception) {
            $message = sprintf(
                'Team synchronization failed: %s',
                $exception->getMessage()
            );

            $this->logger->error(
                $message,
                ['exception' => $exception]
            );
            $this->error($message);

            return 1;
        }
    }
}
