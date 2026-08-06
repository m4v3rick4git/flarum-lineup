<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\CachedImageLocator;

final class CachedImageLocatorTest extends TestCase
{
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
        $this->createPng(
            'assets/wss-lineup/cache/teams/571.png'
        );

        $locator = $this->locator();

        $this->assertSame(
            'https://forum.example.test/'
            .'assets/wss-lineup/cache/teams/571.png',
            $locator->teamLogoUrl(571)
        );
    }

    public function testItReturnsALocalPlayerPhotoUrl(): void
    {
        $this->createPng(
            'assets/wss-lineup/cache/players/1001.png'
        );

        $this->assertSame(
            'https://forum.example.test/'
            .'assets/wss-lineup/cache/players/1001.png',
            $this->locator()->playerPhotoUrl(1001)
        );
    }

    public function testItReturnsNullForMissingFiles(): void
    {
        $locator = $this->locator();

        $this->assertNull(
            $locator->teamLogoUrl(571)
        );

        $this->assertNull(
            $locator->playerPhotoUrl(1001)
        );
    }

    public function testItLoadsOnlyItsOwnCachedImages(): void
    {
        $this->createPng(
            'assets/wss-lineup/cache/players/1001.png'
        );

        $locator = $this->locator();

        $image = $locator->load(
            'https://forum.example.test/'
            .'assets/wss-lineup/cache/players/1001.png'
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
                .'players/../teams/571.png'
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
