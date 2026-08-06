<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wss\FlarumLineup\Image\RemoteImageCache;
use Wss\FlarumLineup\Image\RemoteImageSource;

final class RemoteImageCacheTest extends TestCase
{
    private string $publicPath;

    protected function setUp(): void
    {
        $this->publicPath =
            sys_get_temp_dir()
            .'/wss-lineup-cache-'
            .bin2hex(random_bytes(8));

        mkdir($this->publicPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publicPath);
    }

    public function testItCachesATeamLogoAsPng(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->with(
                'https://media.api-sports.io/football/teams/571.png'
            )
            ->willReturn($this->createImage());

        $cache = $this->cache($source);

        $relativePath = $cache->cacheTeamLogo(
            571,
            'https://media.api-sports.io/football/teams/571.png'
        );

        $this->assertSame(
            'assets/wss-lineup/cache/teams/571.png',
            $relativePath
        );

        $this->assertFileExists(
            $this->publicPath.'/'.$relativePath
        );

        $information = getimagesize(
            $this->publicPath.'/'.$relativePath
        );

        $this->assertIsArray($information);
        $this->assertSame(
            IMAGETYPE_PNG,
            $information[2]
        );
    }

    public function testItPreservesTransparency(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(
                $this->createTransparentImage()
            );

        $cache = $this->cache($source);

        $relativePath = $cache->cacheTeamLogo(
            571,
            'https://media.api-sports.io/'
            .'football/teams/571.png'
        );

        $this->assertSame(
            'assets/wss-lineup/cache/teams/571.png',
            $relativePath
        );

        $cachedImage = imagecreatefrompng(
            $this->publicPath.'/'.$relativePath
        );

        $this->assertInstanceOf(
            GdImage::class,
            $cachedImage
        );

        $pixel = imagecolorat(
            $cachedImage,
            0,
            0
        );

        $alpha = ($pixel >> 24) & 0x7f;

        $this->assertSame(
            127,
            $alpha,
            'The cached PNG must preserve full transparency.'
        );

        imagedestroy($cachedImage);
    }

    public function testItCachesAPlayerPhoto(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($this->createImage());

        $cache = $this->cache($source);

        $this->assertSame(
            'assets/wss-lineup/cache/players/1001.png',
            $cache->cachePlayerPhoto(
                1001,
                'https://media.api-sports.io/football/players/1001.png'
            )
        );
    }

    public function testItReusesAnExistingValidFile(): void
    {
        $path =
            $this->publicPath
            .'/assets/wss-lineup/cache/teams/571.png';

        mkdir(dirname($path), 0775, true);

        $image = $this->createImage();
        imagepng($image, $path);
        imagedestroy($image);

        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->never())
            ->method('fetch');

        $cache = $this->cache($source);

        $this->assertSame(
            'assets/wss-lineup/cache/teams/571.png',
            $cache->cacheTeamLogo(
                571,
                'https://media.api-sports.io/football/teams/571.png'
            )
        );
    }

    public function testItRefreshesAStaleValidFile(): void
    {
        $path =
            $this->publicPath
            .'/assets/wss-lineup/cache/teams/571.png';

        mkdir(dirname($path), 0775, true);

        $oldImage = $this->createImage();
        imagepng($oldImage, $path);
        imagedestroy($oldImage);

        $oldModifiedAt = time() - 691_200;

        $this->assertTrue(
            touch($path, $oldModifiedAt)
        );

        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(
                $this->createTransparentImage()
            );

        $cache = $this->cache($source);

        $this->assertSame(
            'assets/wss-lineup/cache/teams/571.png',
            $cache->cacheTeamLogo(
                571,
                'https://media.api-sports.io/'
                .'football/teams/571.png'
            )
        );

        clearstatcache(true, $path);

        $modifiedAt = filemtime($path);

        $this->assertIsInt($modifiedAt);
        $this->assertGreaterThan(
            $oldModifiedAt,
            $modifiedAt
        );

        $cachedImage = imagecreatefrompng($path);

        $this->assertInstanceOf(
            GdImage::class,
            $cachedImage
        );

        $pixel = imagecolorat(
            $cachedImage,
            0,
            0
        );

        $this->assertSame(
            127,
            ($pixel >> 24) & 0x7f
        );

        imagedestroy($cachedImage);
    }

    public function testItKeepsAStaleFileWhenRefreshFails(): void
    {
        $path =
            $this->publicPath
            .'/assets/wss-lineup/cache/players/1001.png';

        mkdir(dirname($path), 0775, true);

        $oldImage = $this->createImage();
        imagepng($oldImage, $path);
        imagedestroy($oldImage);

        $oldModifiedAt = time() - 691_200;

        $this->assertTrue(
            touch($path, $oldModifiedAt)
        );

        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        $cache = $this->cache($source);

        $this->assertSame(
            'assets/wss-lineup/cache/players/1001.png',
            $cache->cachePlayerPhoto(
                1001,
                'https://media.api-sports.io/'
                .'football/players/1001.png'
            )
        );

        clearstatcache(true, $path);

        $this->assertFileExists($path);
        $this->assertSame(
            $oldModifiedAt,
            filemtime($path)
        );
    }

    public function testItReturnsNullWhenFetchingFails(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        $cache = $this->cache($source);

        $this->assertNull(
            $cache->cachePlayerPhoto(
                1001,
                'https://media.api-sports.io/football/players/1001.png'
            )
        );
    }

    public function testItRejectsAnInvalidApiId(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $cache = $this->cache($source);

        $this->expectException(
            InvalidArgumentException::class
        );

        $cache->cacheTeamLogo(
            0,
            null
        );
    }

    private function cache(
        RemoteImageSource $source
    ): RemoteImageCache {
        return new RemoteImageCache(
            $this->publicPath,
            $source,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createTransparentImage(): GdImage
    {
        $image = imagecreatetruecolor(4, 4);

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        imagealphablending($image, false);

        $transparent = imagecolorallocatealpha(
            $image,
            0,
            0,
            0,
            127
        );

        imagefill(
            $image,
            0,
            0,
            $transparent
        );

        imagesavealpha($image, true);

        return $image;
    }

    private function createImage(): GdImage
    {
        $image = imagecreatetruecolor(4, 4);

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        return $image;
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
