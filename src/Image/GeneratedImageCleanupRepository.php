<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Carbon\Carbon;
use Wss\FlarumLineup\Model\GeneratedImage;

interface GeneratedImageCleanupRepository
{
    /**
     * @return array<int, GeneratedImage>
     */
    public function findExpiredBatch(
        Carbon $now,
        int $afterId,
        int $limit
    ): array;

    public function delete(
        GeneratedImage $image
    ): void;
}
