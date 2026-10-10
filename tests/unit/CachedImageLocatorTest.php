<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\CachedImageLocator;

final class CachedImageLocatorTest extends TestCase
{
    private const TEAM_ID_HASH =
        'f292c8c5c2fe9fd30ef1c632e6936edabe42f087e3cb50ceef0324b729383d82';

    private const PLAYER_ID_HASH =
        'fe675fe7aaee830b6fed09b64e034f84dcbdaeb429d9cccd4ebb90e15af8dd71';

    private string $publicPath;

    protected function setUp(): void
    {
        $this->publicPath =
            sys_get_temp_dir()
            .'/wss-lineup-locator-'
            .bin2hex(random_bytes(8));

        mkdir($this->publicPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publicPath);
    }

    public function testItReturnsALocalTeamLogoUrl(): void
    {
        $relativePath =
            'assets/wss-lineup/cache/teams/api-football/'
            .self::TEAM_ID_HASH
            .'.png';

        $this->createPng($relativePath);

        $locator = $this->locator();

        $this->assertSame(
            'https://forum.example.test/'.$relativePath,
            $locator->teamLogoUrl(
                'api-football',
                '571'
            )
        );
    }

    public function testItReturnsALocalPlayerPhotoUrl(): void
    {
        $relativePath =
            'assets/wss-lineup/cache/players/api-football/'
            .self::PLAYER_ID_HASH
            .'.png';

        $this->createPng($relativePath);

        $this->assertSame(
            'https://forum.example.test/'.$relativePath,
            $this->locator()->playerPhotoUrl(
                'api-football',
                '1001'
            )
        );
    }

    public function testItSupportsNonNumericProviderIds(): void
    {
        $providerTeamId = 'SK-RBS';

        $relativePath =
            'assets/wss-lineup/cache/teams/bundesliga-at/'
            .hash('sha256', $providerTeamId)
            .'.png';

        $this->createPng($relativePath);

        $this->assertSame(
            'https://forum.example.test/'.$relativePath,
            $this->locator()->teamLogoUrl(
                'bundesliga-at',
                $providerTeamId
            )
        );
    }

    public function testItReturnsNullForMissingFiles(): void
    {
        $locator = $this->locator();

        $this->assertNull(
            $locator->teamLogoUrl(
                'api-football',
                '571'
            )
        );

        $this->assertNull(
            $locator->playerPhotoUrl(
                'api-football',
                '1001'
            )
        );
    }

    public function testItLoadsOnlyItsOwnCachedImages(): void
    {
        $relativePath =
            'assets/wss-lineup/cache/players/api-football/'
            .self::PLAYER_ID_HASH
            .'.png';

        $this->createPng($relativePath);

        $locator = $this->locator();

        $image = $locator->load(
            'https://forum.example.test/'
            .$relativePath
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        imagedestroy($image);

        $this->assertNull(
            $locator->load(
                'https://media.api-sports.io/'
                .'football/players/1001.png'
            )
        );

        $this->assertNull(
            $locator->load(
                'https://forum.example.test/'
                .'assets/wss-lineup/cache/'
                .'players/api-football/../'
                .'teams/'
                .self::TEAM_ID_HASH
                .'.png'
            )
        );
    }

    public function testItRejectsInvalidConstructorValues(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new CachedImageLocator(
            '',
            'https://forum.example.test'
        );
    }

    private function locator(): CachedImageLocator
    {
        return new CachedImageLocator(
            $this->publicPath,
            'https://forum.example.test'
        );
    }

    private function createPng(
        string $relativePath
    ): void {
        $absolutePath =
            $this->publicPath
            .'/'
            .$relativePath;

        mkdir(
            dirname($absolutePath),
            0775,
            true
        );

        $image = imagecreatetruecolor(8, 8);

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        imagepng($image, $absolutePath);
        imagedestroy($image);
    }

    private function removeDirectory(
        string $directory
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
