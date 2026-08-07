<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Console;

use Flarum\Console\AbstractCommand;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Image\GeneratedImageCleanupService;

final class CleanupGeneratedImagesCommand extends AbstractCommand
{
    private GeneratedImageCleanupService $cleanupService;

    private LoggerInterface $logger;

    public function __construct(
        GeneratedImageCleanupService $cleanupService,
        LoggerInterface $logger
    ) {
        $this->cleanupService = $cleanupService;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('wss-lineup:cleanup-images')
            ->setDescription(
                'Delete expired, unused WSS Lineup images.'
            );
    }

    protected function fire()
    {
        try {
            $result = $this->cleanupService->cleanup();
        } catch (Throwable $exception) {
            $message =
                'Generated-image cleanup failed unexpectedly.';

            $this->logger->error(
                $message,
                [
                    'exception' => $exception,
                ]
            );

            $this->error($message);

            return 1;
        }

        $summary = sprintf(
            'Generated-image cleanup completed. Scanned: %d; files deleted: %d; records deleted: %d; failed: %d.',
            $result['scanned'],
            $result['deletedFiles'],
            $result['deletedRecords'],
            $result['failed']
        );

        $this->logger->info($summary);
        $this->info($summary);

        return $result['failed'] > 0 ? 1 : 0;
    }
}
