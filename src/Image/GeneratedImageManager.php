<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Carbon\Carbon;
use Flarum\Foundation\Paths;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wss\FlarumLineup\Model\GeneratedImage;

final class GeneratedImageManager
{
    private const MAX_PER_MINUTE = 5;

    private const MAX_PER_DAY = 50;

    private const MINUTE_WINDOW_SECONDS = 60;

    private const DAY_WINDOW_SECONDS = 86400;

    private const MAX_OUTPUT_BYTES = 15000000;

    private Paths $paths;

    private ImageGenerationLockManager $lockManager;

    public function __construct(
        Paths $paths,
        ImageGenerationLockManager $lockManager
    ) {
        $this->paths = $paths;
        $this->lockManager = $lockManager;
    }

    /**
     * @param callable(string): void $renderer
     */
    public function generate(
        int $actorId,
        callable $renderer
    ): GeneratedImage {
        if ($actorId <= 0) {
            throw new InvalidArgumentException(
                'A valid actor ID is required.'
            );
        }

        return $this->lockManager->run(
            function () use (
                $actorId,
                $renderer
            ): GeneratedImage {
                return $this->generateUnlocked(
                    $actorId,
                    $renderer
                );
            }
        );
    }

    /**
     * @param callable(string): void $renderer
     */
    private function generateUnlocked(
        int $actorId,
        callable $renderer
    ): GeneratedImage {
        $this->assertRateLimits($actorId);

        $filename = bin2hex(
            random_bytes(20)
        ).'.png';

        $relativePath =
            'assets/wss-lineup/'.$filename;

        $outputPath =
            $this->paths->public.'/'.$relativePath;

        $image = GeneratedImage::query()->create([
            'actor_id' => $actorId,
            'post_id' => null,
            'filename' => $filename,
            'relative_path' => $relativePath,
            'status' => GeneratedImage::STATUS_PENDING,
            'size_bytes' => null,
            'expires_at' => Carbon::now()->addDay(),
            'claimed_at' => null,
        ]);

        if (!$image instanceof GeneratedImage) {
            throw new RuntimeException(
                'The generated image could not be registered.'
            );
        }

        try {
            $renderer($outputPath);

            if (!is_file($outputPath)) {
                throw new RuntimeException(
                    'The renderer did not create an image file.'
                );
            }

            $size = filesize($outputPath);

            if (
                $size === false
                || $size < 1
                || $size > self::MAX_OUTPUT_BYTES
            ) {
                throw new RuntimeException(
                    'The generated image has an invalid file size.'
                );
            }

            $image->status = GeneratedImage::STATUS_READY;
            $image->size_bytes = $size;
            $image->save();

            return $image;
        } catch (Throwable $exception) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }

            try {
                $image->status = GeneratedImage::STATUS_FAILED;
                $image->size_bytes = null;
                $image->save();
            } catch (Throwable) {
                // Preserve the original rendering exception.
            }

            throw $exception;
        }
    }

    private function assertRateLimits(int $actorId): void
    {
        $this->assertWindowLimit(
            $actorId,
            self::MAX_PER_MINUTE,
            self::MINUTE_WINDOW_SECONDS,
            'minute_limit',
            'Too many lineup images were requested recently.'
        );

        $this->assertWindowLimit(
            $actorId,
            self::MAX_PER_DAY,
            self::DAY_WINDOW_SECONDS,
            'daily_limit',
            'The daily lineup image limit has been reached.'
        );
    }

    private function assertWindowLimit(
        int $actorId,
        int $limit,
        int $windowSeconds,
        string $reason,
        string $message
    ): void {
        $now = Carbon::now();
        $windowStart = $now->copy()->subSeconds(
            $windowSeconds
        );

        $count = (int) GeneratedImage::query()
            ->where('actor_id', $actorId)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($count < $limit) {
            return;
        }

        $oldest = GeneratedImage::query()
            ->where('actor_id', $actorId)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->first();

        $retryAfter = $windowSeconds;

        if (
            $oldest instanceof GeneratedImage
            && $oldest->created_at !== null
        ) {
            $expiresAt = $oldest->created_at
                ->copy()
                ->addSeconds($windowSeconds);

            $retryAfter = max(
                1,
                $expiresAt->getTimestamp()
                - $now->getTimestamp()
            );
        }

        throw new ImageGenerationThrottledException(
            $reason,
            $retryAfter,
            $message
        );
    }
}
