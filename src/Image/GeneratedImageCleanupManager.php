<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Model\GeneratedImage;

final class GeneratedImageCleanupManager implements
    GeneratedImageCleanupService
{
    private const BATCH_SIZE = 100;

    private const FILENAME_PATTERN =
        '/\A[a-f0-9]{40}\.png\z/D';

    private string $publicPath;

    private GeneratedImageCleanupRepository $repository;

    private LoggerInterface $logger;

    public function __construct(
        string $publicPath,
        GeneratedImageCleanupRepository $repository,
        LoggerInterface $logger
    ) {
        $this->publicPath = rtrim(
            $publicPath,
            DIRECTORY_SEPARATOR
        );

        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * @return array{
     *     scanned: int,
     *     deletedFiles: int,
     *     deletedRecords: int,
     *     failed: int
     * }
     */
    public function cleanup(): array
    {
        $result = [
            'scanned' => 0,
            'deletedFiles' => 0,
            'deletedRecords' => 0,
            'failed' => 0,
        ];

        $now = Carbon::now();
        $afterId = 0;

        do {
            $images = $this->repository
                ->findExpiredBatch(
                    $now,
                    $afterId,
                    self::BATCH_SIZE
                );

            foreach ($images as $image) {
                $afterId = max(
                    $afterId,
                    (int) $image->id
                );

                ++$result['scanned'];

                $this->cleanupImage(
                    $image,
                    $result
                );
            }
        } while (count($images) === self::BATCH_SIZE);

        return $result;
    }

    /**
     * @param array{
     *     scanned: int,
     *     deletedFiles: int,
     *     deletedRecords: int,
     *     failed: int
     * } $result
     */
    private function cleanupImage(
        GeneratedImage $image,
        array &$result
    ): void {
        $filename = (string) $image->filename;
        $relativePath = (string) $image->relative_path;

        if (
            preg_match(
                self::FILENAME_PATTERN,
                $filename
            ) !== 1
            || $relativePath
                !== 'assets/wss-lineup/'.$filename
        ) {
            ++$result['failed'];

            $this->logger->warning(
                'WSS Lineup cleanup rejected an invalid image path.',
                [
                    'imageId' => (int) $image->id,
                    'filename' => $filename,
                    'relativePath' => $relativePath,
                ]
            );

            return;
        }

        $absolutePath =
            $this->publicPath
            .DIRECTORY_SEPARATOR
            .str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );

        try {
            if (
                file_exists($absolutePath)
                || is_link($absolutePath)
            ) {
                if (
                    !is_file($absolutePath)
                    && !is_link($absolutePath)
                ) {
                    throw new \RuntimeException(
                        'The generated image path is not a file.'
                    );
                }

                if (!@unlink($absolutePath)) {
                    throw new \RuntimeException(
                        'The generated image file could not be deleted.'
                    );
                }

                ++$result['deletedFiles'];
            }

            $this->repository->delete($image);

            ++$result['deletedRecords'];
        } catch (Throwable $exception) {
            ++$result['failed'];

            $this->logger->warning(
                'WSS Lineup generated-image cleanup failed.',
                [
                    'imageId' => (int) $image->id,
                    'exceptionClass' => $exception::class,
                ]
            );
        }
    }
}
