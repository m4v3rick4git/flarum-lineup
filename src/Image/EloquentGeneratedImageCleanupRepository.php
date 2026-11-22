<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Carbon\Carbon;
use Wss\FlarumLineup\Model\GeneratedImage;

final class EloquentGeneratedImageCleanupRepository implements
    GeneratedImageCleanupRepository
{
    /**
     * @return array<int, GeneratedImage>
     */
    public function findExpiredBatch(
        Carbon $now,
        int $afterId,
        int $limit
    ): array {
        return GeneratedImage::query()
            ->where('id', '>', $afterId)
            ->where('relative_path', 'like', 'assets/wss-lineup-2/%')
            ->whereNull('post_id')
            ->whereIn(
                'status',
                [
                    GeneratedImage::STATUS_PENDING,
                    GeneratedImage::STATUS_READY,
                    GeneratedImage::STATUS_FAILED,
                ]
            )
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->all();
    }

    public function delete(
        GeneratedImage $image
    ): void {
        $image->delete();
    }
}
