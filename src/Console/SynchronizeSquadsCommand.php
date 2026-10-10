<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Sync\SquadSynchronizationRunner;

final class SynchronizeSquadsCommand extends AbstractCommand
{
    private SquadSynchronizationRunner $runner;

    private LoggerInterface $logger;

    public function __construct(
        SquadSynchronizationRunner $runner,
        LoggerInterface $logger
    ) {
        $this->runner = $runner;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('wss-lineup:sync-squads')
            ->setDescription(
                'Synchronize active team squads from the selected data provider.'
            );
    }

    protected function fire()
    {
        try {
            $result = $this->runner->run(
                function (
                    int $position,
                    int $total,
                    string $teamName
                ): void {
                    $this->info(
                        sprintf(
                            '[%d/%d] Synchronizing %s...',
                            $position,
                            $total,
                            $teamName
                        )
                    );
                }
            );

            $summary = sprintf(
                'Squad synchronization completed. Provider: %s; teams: %d; received: %d; created: %d; updated: %d; deactivated: %d; failed: %d.',
                $result['provider'],
                $result['teams'],
                $result['received'],
                $result['created'],
                $result['updated'],
                $result['deactivated'],
                $result['failed']
            );

            $this->logger->info($summary);
            $this->info($summary);

            return $result['failed'] > 0 ? 1 : 0;
        } catch (Throwable $exception) {
            $message = sprintf(
                'Squad synchronization failed: %s',
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
