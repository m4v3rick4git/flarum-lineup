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

    private const BUNDESLIGA_AT_PROVIDER = 'bundesliga-at';

    private const MAX_BUNDESLIGA_PLAYER_SIZE = 512;

    private const BUNDESLIGA_PLAYER_VERTICAL_BIAS = 0.10;

    private const PLAYER_PLACEHOLDER_SIZE = 512;

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
        string $provider,
        string $providerTeamId,
        ?string $remoteUrl,
        bool $forceRefresh = false
    ): ?string {
        return $this->cache(
            'teams',
            $provider,
            $providerTeamId,
            $remoteUrl,
            $forceRefresh
        );
    }

    public function cachePlayerPhoto(
        string $provider,
        string $providerPlayerId,
        ?string $remoteUrl,
        bool $forceRefresh = false
    ): ?string {
        return $this->cache(
            'players',
            $provider,
            $providerPlayerId,
            $remoteUrl,
            $forceRefresh
        );
    }

    private function cache(
        string $type,
        string $provider,
        string $providerId,
        ?string $remoteUrl,
        bool $forceRefresh
    ): ?string {
        if (
            preg_match(
                '#^[a-z0-9]+(?:-[a-z0-9]+)*$#',
                $provider
            ) !== 1
            || $providerId === ''
        ) {
            throw new InvalidArgumentException(
                'A valid provider image identity is required.'
            );
        }

        $relativePath = sprintf(
            'assets/wss-lineup/cache/%s/%s/%s.png',
            $type,
            $provider,
            hash('sha256', $providerId)
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
            !$forceRefresh
            && $hasValidFile
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
                        'provider' => $provider,
                        'providerId' => $providerId,
                    ]
                );

                return null;
            }

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $isBundesligaAtPlayer =
            $type === 'players'
            && $provider === self::BUNDESLIGA_AT_PROVIDER;

        $usesLocalPlayerPlaceholder =
            $isBundesligaAtPlayer
            && $this->shouldUseLocalPlayerPlaceholder(
                $remoteUrl
            );

        if ($usesLocalPlayerPlaceholder) {
            $image = $this->createLocalPlayerPlaceholder();
        } else {
            $image = $this->remoteImageSource->fetch(
                $remoteUrl
            );

            /*
             * Bundesliga.at can expose a player-image URL even
             * when no usable portrait exists behind it.
             *
             * Keep the source URL in the database so that a
             * future refresh can pick up a newly available image,
             * but fall back to our own neutral placeholder for
             * the local cache.
             */
            if (
                $isBundesligaAtPlayer
                && !$image instanceof GdImage
            ) {
                $this->logger->info(
                    (
                        'WSS Lineup uses the local player '
                        .'placeholder because the Bundesliga.at '
                        .'player image could not be fetched.'
                    ),
                    [
                        'provider' => $provider,
                        'providerId' => $providerId,
                    ]
                );

                $image =
                    $this->createLocalPlayerPlaceholder();
            }
        }

        if (!$image instanceof GdImage) {
            return $hasValidFile
                ? $relativePath
                : null;
        }

        if ($isBundesligaAtPlayer) {
            $image = $this->normalizeBundesligaAtPlayerPhoto(
                $image
            );
        }

        $cachedPath = $this->writeImage(
            $image,
            $absolutePath,
            $relativePath,
            $type,
            $provider,
            $providerId
        );

        return $cachedPath
            ?? (
                $hasValidFile
                    ? $relativePath
                    : null
            );
    }

    private function shouldUseLocalPlayerPlaceholder(
        ?string $remoteUrl
    ): bool {
        if (
            $remoteUrl === null
            || trim($remoteUrl) === ''
        ) {
            return true;
        }

        $decoded = html_entity_decode(
            trim($remoteUrl),
            ENT_QUOTES | ENT_HTML5
        );

        return str_contains(
            urldecode($decoded),
            '/icons/portrait_placeholder.png'
        );
    }

    private function createLocalPlayerPlaceholder(): GdImage
    {
        $size = self::PLAYER_PLACEHOLDER_SIZE;

        $image = imagecreatetruecolor(
            $size,
            $size
        );

        if (!$image instanceof GdImage) {
            throw new \RuntimeException(
                'The local player placeholder could not be created.'
            );
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $background = imagecolorallocate(
            $image,
            238,
            241,
            244
        );

        $inner = imagecolorallocate(
            $image,
            218,
            223,
            229
        );

        $silhouette = imagecolorallocate(
            $image,
            92,
            103,
            114
        );

        imagefill(
            $image,
            0,
            0,
            $background
        );

        if (function_exists('imageantialias')) {
            @imageantialias(
                $image,
                true
            );
        }

        imagefilledellipse(
            $image,
            (int) round($size * 0.5),
            (int) round($size * 0.5),
            (int) round($size * 0.86),
            (int) round($size * 0.86),
            $inner
        );

        imagefilledellipse(
            $image,
            (int) round($size * 0.5),
            (int) round($size * 0.34),
            (int) round($size * 0.28),
            (int) round($size * 0.28),
            $silhouette
        );

        imagefilledellipse(
            $image,
            (int) round($size * 0.5),
            (int) round($size * 0.78),
            (int) round($size * 0.62),
            (int) round($size * 0.54),
            $silhouette
        );

        imagefilledrectangle(
            $image,
            (int) round($size * 0.19),
            (int) round($size * 0.78),
            (int) round($size * 0.81),
            $size - 1,
            $silhouette
        );

        return $image;
    }

    private function normalizeBundesligaAtPlayerPhoto(
        GdImage $image
    ): GdImage {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 1 || $height < 1) {
            return $image;
        }

        $cropSize = min($width, $height);

        $sourceX = $width > $cropSize
            ? (int) floor(($width - $cropSize) / 2)
            : 0;

        $sourceY = 0;

        if ($height > $cropSize) {
            $sourceY = (int) round(
                ($height - $cropSize)
                * self::BUNDESLIGA_PLAYER_VERTICAL_BIAS
            );
        }

        $targetSize = min(
            self::MAX_BUNDESLIGA_PLAYER_SIZE,
            $cropSize
        );

        $normalized = imagecreatetruecolor(
            $targetSize,
            $targetSize
        );

        if (!$normalized instanceof GdImage) {
            return $image;
        }

        imagealphablending($normalized, false);
        imagesavealpha($normalized, true);

        $transparent = imagecolorallocatealpha(
            $normalized,
            0,
            0,
            0,
            127
        );

        imagefill(
            $normalized,
            0,
            0,
            $transparent
        );

        if (
            !imagecopyresampled(
                $normalized,
                $image,
                0,
                0,
                $sourceX,
                $sourceY,
                $targetSize,
                $targetSize,
                $cropSize,
                $cropSize
            )
        ) {
            imagedestroy($normalized);

            return $image;
        }

        imagedestroy($image);

        return $normalized;
    }

    private function writeImage(
        GdImage $image,
        string $absolutePath,
        string $relativePath,
        string $type,
        string $provider,
        string $providerId
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
                    'provider' => $provider,
                    'providerId' => $providerId,
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
