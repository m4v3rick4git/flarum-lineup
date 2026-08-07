<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

final class RemoteImageCache implements
    RemoteImageCacheService
{
    private const MAX_OUTPUT_BYTES = 15_000_000;

    private const MAX_AGE_SECONDS = 604_800;

    private string $publicPath;

    private RemoteImageSource $remoteImageSource;

    private LoggerInterface $logger;

    public function __construct(
        string $publicPath,
        RemoteImageSource $remoteImageSource,
        LoggerInterface $logger
    ) {
        $this->publicPath = rtrim(
            $publicPath,
            DIRECTORY_SEPARATOR
        );

        $this->remoteImageSource = $remoteImageSource;
        $this->logger = $logger;
    }

    public function cacheTeamLogo(
        int $apiTeamId,
        ?string $remoteUrl
    ): ?string {
        return $this->cache(
            'teams',
            $apiTeamId,
            $remoteUrl
        );
    }

    public function cachePlayerPhoto(
        int $apiPlayerId,
        ?string $remoteUrl
    ): ?string {
        return $this->cache(
            'players',
            $apiPlayerId,
            $remoteUrl
        );
    }

    private function cache(
        string $type,
        int $apiId,
        ?string $remoteUrl
    ): ?string {
        if ($apiId <= 0) {
            throw new InvalidArgumentException(
                'A valid API image ID is required.'
            );
        }

        $relativePath = sprintf(
            'assets/wss-lineup/cache/%s/%d.png',
            $type,
            $apiId
        );

        $absolutePath =
            $this->publicPath
            .DIRECTORY_SEPARATOR
            .str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );

        $hasValidFile = $this->isValidCachedFile(
            $absolutePath
        );

        if (
            $hasValidFile
            && $this->isFreshCachedFile($absolutePath)
        ) {
            return $relativePath;
        }

        if (!$hasValidFile) {
            if (
                is_link($absolutePath)
                || (
                    file_exists($absolutePath)
                    && !is_file($absolutePath)
                )
            ) {
                $this->logger->warning(
                    'WSS Lineup rejected an invalid cache path.',
                    [
                        'type' => $type,
                        'apiId' => $apiId,
                    ]
                );

                return null;
            }

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $image = $this->remoteImageSource->fetch(
            $remoteUrl
        );

        if (!$image instanceof GdImage) {
            return $hasValidFile
                ? $relativePath
                : null;
        }

        $cachedPath = $this->writeImage(
            $image,
            $absolutePath,
            $relativePath,
            $type,
            $apiId
        );

        return $cachedPath
            ?? (
                $hasValidFile
                    ? $relativePath
                    : null
            );
    }

    private function writeImage(
        GdImage $image,
        string $absolutePath,
        string $relativePath,
        string $type,
        int $apiId
    ): ?string {
        $temporaryPath = null;

        try {
            $directory = dirname($absolutePath);

            if (
                is_link($directory)
                || (
                    !is_dir($directory)
                    && !mkdir(
                        $directory,
                        0775,
                        true
                    )
                    && !is_dir($directory)
                )
            ) {
                throw new \RuntimeException(
                    'The image-cache directory could not be created.'
                );
            }

            $temporaryPath = sprintf(
                '%s.%s.tmp',
                $absolutePath,
                bin2hex(random_bytes(8))
            );

            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($image);
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);

            if (!imagepng($image, $temporaryPath, 6)) {
                throw new \RuntimeException(
                    'The cached PNG could not be written.'
                );
            }

            clearstatcache(true, $temporaryPath);

            $size = filesize($temporaryPath);

            if (
                $size === false
                || $size < 1
                || $size > self::MAX_OUTPUT_BYTES
            ) {
                throw new \RuntimeException(
                    'The cached PNG has an invalid size.'
                );
            }

            if (!@rename($temporaryPath, $absolutePath)) {
                throw new \RuntimeException(
                    'The cached PNG could not be installed.'
                );
            }

            $temporaryPath = null;

            @chmod($absolutePath, 0664);

            return $relativePath;
        } catch (Throwable $exception) {
            $this->logger->warning(
                'WSS Lineup image caching failed.',
                [
                    'type' => $type,
                    'apiId' => $apiId,
                    'exceptionClass' => $exception::class,
                ]
            );

            return null;
        } finally {
            imagedestroy($image);

            if (
                $temporaryPath !== null
                && is_file($temporaryPath)
            ) {
                @unlink($temporaryPath);
            }
        }
    }

    private function isValidCachedFile(
        string $absolutePath
    ): bool {
        if (
            is_link($absolutePath)
            || !is_file($absolutePath)
        ) {
            return false;
        }

        $size = filesize($absolutePath);

        if (
            $size === false
            || $size < 1
            || $size > self::MAX_OUTPUT_BYTES
        ) {
            return false;
        }

        $information = @getimagesize($absolutePath);

        return is_array($information)
            && ($information[2] ?? null) === IMAGETYPE_PNG;
    }
    private function isFreshCachedFile(
        string $absolutePath
    ): bool {
        $modifiedAt = filemtime($absolutePath);

        if ($modifiedAt === false) {
            return false;
        }

        return $modifiedAt >= (
            time() - self::MAX_AGE_SECONDS
        );
    }

}
