<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

interface GeneratedImageCleanupService
{
    /**
     * @return array{
     *     scanned: int,
     *     deletedFiles: int,
     *     deletedRecords: int,
     *     failed: int
     * }
     */
    public function cleanup(): array;
}
