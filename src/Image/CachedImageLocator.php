<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;
use InvalidArgumentException;

final class CachedImageLocator implements CachedImageAccess
{
    private const MAX_BYTES = 15_000_000;

    private const MAX_WIDTH = 2_000;

    private const MAX_HEIGHT = 2_000;

    private const MAX_PIXELS = 4_000_000;

    private string $publicPath;

    private string $baseUrl;

    public function __construct(
        string $publicPath,
        string $baseUrl
    ) {
        $this->publicPath = rtrim(
            $publicPath,
            DIRECTORY_SEPARATOR
        );

        $this->baseUrl = rtrim($baseUrl, '/');

        if (
            $this->publicPath === ''
            || $this->baseUrl === ''
        ) {
            throw new InvalidArgumentException(
                'Valid public and base paths are required.'
            );
        }
    }

    public function teamLogoUrl(
        string $provider,
        string $providerTeamId
    ): ?string {
        return $this->urlFor(
            'teams',
            $provider,
            $providerTeamId
        );
    }

    public function playerPhotoUrl(
        string $provider,
        string $providerPlayerId
    ): ?string {
        return $this->urlFor(
            'players',
            $provider,
            $providerPlayerId
        );
    }

    public function load(
        ?string $url
    ): ?GdImage {
        $relativePath = $this->relativePathFromUrl(
            $url
        );

        if ($relativePath === null) {
            return null;
        }

        $absolutePath = $this->absolutePath(
            $relativePath
        );

        if (!$this->isValidCachedPng($absolutePath)) {
            return null;
        }

        $image = @imagecreatefrompng($absolutePath);

        if (!$image instanceof GdImage) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
            || ($width * $height) > self::MAX_PIXELS
        ) {
            imagedestroy($image);

            return null;
        }

        return $image;
    }

    private function urlFor(
        string $type,
        string $provider,
        string $providerId
    ): ?string {
        $relativePath = $this->cachePath(
            $type,
            $provider,
            $providerId
        );

        if ($relativePath === null) {
            return null;
        }

        if (
            !$this->isValidCachedPng(
                $this->absolutePath($relativePath)
            )
        ) {
            return null;
        }

        return $this->baseUrl.'/'.$relativePath;
    }

    private function cachePath(
        string $type,
        string $provider,
        string $providerId
    ): ?string {
        if (
            !in_array(
                $type,
                [
                    'teams',
                    'players',
                ],
                true
            )
            || preg_match(
                '#^[a-z0-9]+(?:-[a-z0-9]+)*$#',
                $provider
            ) !== 1
            || $providerId === ''
        ) {
            return null;
        }

        return sprintf(
            'assets/wss-lineup/cache/%s/%s/%s.png',
            $type,
            $provider,
            hash('sha256', $providerId)
        );
    }

    private function relativePathFromUrl(
        ?string $url
    ): ?string {
        if ($url === null || $url === '') {
            return null;
        }

        $prefix =
            $this->baseUrl
            .'/assets/wss-lineup/cache/';

        if (!str_starts_with($url, $prefix)) {
            return null;
        }

        $suffix = substr(
            $url,
            strlen($prefix)
        );

        if (
            !is_string($suffix)
            || preg_match(
                '#^(teams|players)/'
                .'[a-z0-9]+(?:-[a-z0-9]+)*/'
                .'[a-f0-9]{64}\.png$#',
                $suffix
            ) !== 1
        ) {
            return null;
        }

        return 'assets/wss-lineup/cache/'.$suffix;
    }

    private function absolutePath(
        string $relativePath
    ): string {
        return
            $this->publicPath
            .DIRECTORY_SEPARATOR
            .str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );
    }

    private function isValidCachedPng(
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
            || $size > self::MAX_BYTES
        ) {
            return false;
        }

        $information = @getimagesize(
            $absolutePath
        );

        if (!is_array($information)) {
            return false;
        }

        $width = $information[0] ?? null;
        $height = $information[1] ?? null;
        $type = $information[2] ?? null;

        return
            is_int($width)
            && is_int($height)
            && $width >= 1
            && $height >= 1
            && $width <= self::MAX_WIDTH
            && $height <= self::MAX_HEIGHT
            && ($width * $height) <= self::MAX_PIXELS
            && $type === IMAGETYPE_PNG;
    }
}
