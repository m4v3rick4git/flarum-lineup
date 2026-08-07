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
        int $apiTeamId
    ): ?string {
        return $this->urlFor(
            'teams',
            $apiTeamId
        );
    }

    public function playerPhotoUrl(
        int $apiPlayerId
    ): ?string {
        return $this->urlFor(
            'players',
            $apiPlayerId
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
        int $apiId
    ): ?string {
        if ($apiId <= 0) {
            return null;
        }

        $relativePath = sprintf(
            'assets/wss-lineup/cache/%s/%d.png',
            $type,
            $apiId
        );

        if (
            !$this->isValidCachedPng(
                $this->absolutePath($relativePath)
            )
        ) {
            return null;
        }

        return $this->baseUrl.'/'.$relativePath;
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
                '#^(teams|players)/[1-9][0-9]*\.png$#',
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
