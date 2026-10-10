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
    private const PROVIDER = 'api-football';

    private const TEAM_ID = '571';

    private const PLAYER_ID = '1001';

    private const TEAM_ID_HASH =
        'f292c8c5c2fe9fd30ef1c632e6936edabe42f087e3cb50ceef0324b729383d82';

    private const PLAYER_ID_HASH =
        'fe675fe7aaee830b6fed09b64e034f84dcbdaeb429d9cccd4ebb90e15af8dd71';

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
            self::PROVIDER,
            self::TEAM_ID,
            'https://media.api-sports.io/football/teams/571.png'
        );

        $this->assertSame(
            'assets/wss-lineup/cache/teams/api-football/'
            .self::TEAM_ID_HASH
            .'.png',
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
            self::PROVIDER,
            self::TEAM_ID,
            'https://media.api-sports.io/'
            .'football/teams/571.png'
        );

        $this->assertSame(
            'assets/wss-lineup/cache/teams/api-football/'
            .self::TEAM_ID_HASH
            .'.png',
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
            'assets/wss-lineup/cache/players/api-football/'
            .self::PLAYER_ID_HASH
            .'.png',
            $cache->cachePlayerPhoto(
                self::PROVIDER,
                self::PLAYER_ID,
                'https://media.api-sports.io/'
                .'football/players/1001.png'
            )
        );
    }

    public function testItSupportsNonNumericProviderIds(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($this->createImage());

        $providerTeamId = 'SK-RBS';

        $this->assertSame(
            'assets/wss-lineup/cache/teams/bundesliga-at/'
            .hash('sha256', $providerTeamId)
            .'.png',
            $this->cache($source)->cacheTeamLogo(
                'bundesliga-at',
                $providerTeamId,
                'https://example.test/team-logo.png'
            )
        );
    }

    public function testItReusesAnExistingValidFile(): void
    {
        $relativePath =
            'assets/wss-lineup/cache/teams/api-football/'
            .self::TEAM_ID_HASH
            .'.png';

        $path =
            $this->publicPath
            .'/'
            .$relativePath;

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
            $relativePath,
            $cache->cacheTeamLogo(
                self::PROVIDER,
                self::TEAM_ID,
                'https://media.api-sports.io/'
                .'football/teams/571.png'
            )
        );
    }

    public function testItRefreshesAStaleValidFile(): void
    {
        $relativePath =
            'assets/wss-lineup/cache/teams/api-football/'
            .self::TEAM_ID_HASH
            .'.png';

        $path =
            $this->publicPath
            .'/'
            .$relativePath;

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
            $relativePath,
            $cache->cacheTeamLogo(
                self::PROVIDER,
                self::TEAM_ID,
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
        $relativePath =
            'assets/wss-lineup/cache/players/api-football/'
            .self::PLAYER_ID_HASH
            .'.png';

        $path =
            $this->publicPath
            .'/'
            .$relativePath;

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
            $relativePath,
            $cache->cachePlayerPhoto(
                self::PROVIDER,
                self::PLAYER_ID,
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
                self::PROVIDER,
                self::PLAYER_ID,
                'https://media.api-sports.io/'
                .'football/players/1001.png'
            )
        );
    }

    public function testItRejectsAnInvalidProviderIdentity(): void
    {
        $source = $this->createMock(
            RemoteImageSource::class
        );

        $cache = $this->cache($source);

        $this->expectException(
            InvalidArgumentException::class
        );

        $cache->cacheTeamLogo(
            '../api-football',
            self::TEAM_ID,
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
