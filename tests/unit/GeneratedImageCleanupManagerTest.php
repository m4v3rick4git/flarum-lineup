<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wss\FlarumLineup\Image\GeneratedImageCleanupManager;
use Wss\FlarumLineup\Image\GeneratedImageCleanupRepository;
use Wss\FlarumLineup\Model\GeneratedImage;

final class GeneratedImageCleanupManagerTest extends TestCase
{
    private string $publicPath;

    protected function setUp(): void
    {
        $this->publicPath =
            sys_get_temp_dir()
            .'/wss-lineup-cleanup-'
            .bin2hex(random_bytes(8));

        mkdir(
            $this->publicPath.'/assets/wss-lineup-2',
            0775,
            true
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->publicPath
        );
    }

    public function testItDeletesExpiredFilesAndRecords(): void
    {
        $first = $this->image(
            1,
            str_repeat('a', 40).'.png'
        );

        $second = $this->image(
            2,
            str_repeat('b', 40).'.png'
        );

        file_put_contents(
            $this->publicPath
            .'/'
            .$first->relative_path,
            'png-data'
        );

        $repository = $this->createMock(
            GeneratedImageCleanupRepository::class
        );

        $repository
            ->expects($this->once())
            ->method('findExpiredBatch')
            ->willReturn([$first, $second]);

        $deletedIds = [];

        $repository
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(
                static function (GeneratedImage $image) use (&$deletedIds): void {
                    $deletedIds[] = (int) $image->id;
                }
            );

        $manager = new GeneratedImageCleanupManager(
            $this->publicPath,
            $repository,
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(
            [
                'scanned' => 2,
                'deletedFiles' => 1,
                'deletedRecords' => 2,
                'failed' => 0,
            ],
            $manager->cleanup()
        );

        $this->assertSame([1, 2], $deletedIds);

        $this->assertFileDoesNotExist(
            $this->publicPath
            .'/'
            .$first->relative_path
        );
    }

    public function testItRejectsAnInvalidRelativePath(): void
    {
        $image = $this->image(
            3,
            str_repeat('c', 40).'.png'
        );

        $image->relative_path =
            'assets/wss-lineup-2/../invalid.png';

        $repository = $this->createMock(
            GeneratedImageCleanupRepository::class
        );

        $repository
            ->expects($this->once())
            ->method('findExpiredBatch')
            ->willReturn([$image]);

        $repository
            ->expects($this->never())
            ->method('delete');

        $manager = new GeneratedImageCleanupManager(
            $this->publicPath,
            $repository,
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(
            [
                'scanned' => 1,
                'deletedFiles' => 0,
                'deletedRecords' => 0,
                'failed' => 1,
            ],
            $manager->cleanup()
        );
    }

    public function testItNeverDeletesLegacyGeneratedImages(): void
    {
        $legacy = $this->image(
            4,
            str_repeat('d', 40).'.png'
        );

        $legacy->relative_path =
            'assets/wss-lineup/'.$legacy->filename;

        $legacyFile =
            $this->publicPath.'/'.$legacy->relative_path;

        mkdir(dirname($legacyFile), 0775, true);

        $this->assertNotFalse(
            file_put_contents($legacyFile, 'legacy-image')
        );

        $repository = $this->createMock(
            GeneratedImageCleanupRepository::class
        );

        $repository
            ->expects($this->once())
            ->method('findExpiredBatch')
            ->willReturn([$legacy]);

        $repository
            ->expects($this->never())
            ->method('delete');

        $manager = new GeneratedImageCleanupManager(
            $this->publicPath,
            $repository,
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(
            [
                'scanned' => 1,
                'deletedFiles' => 0,
                'deletedRecords' => 0,
                'failed' => 1,
            ],
            $manager->cleanup()
        );

        $this->assertFileExists($legacyFile);

        $this->assertSame(
            'legacy-image',
            file_get_contents($legacyFile)
        );
    }

    private function image(
        int $id,
        string $filename
    ): GeneratedImage {
        $image = new GeneratedImage();

        $image->id = $id;
        $image->filename = $filename;
        $image->relative_path =
            'assets/wss-lineup-2/'.$filename;

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
