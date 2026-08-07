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
            $this->publicPath.'/assets/wss-lineup',
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

        $repository
            ->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                [$first],
                [$second]
            );

        $manager = new GeneratedImageCleanupManager(
            $this->publicPath,
            $repository,
            $this->createMock(LoggerInterface::class)
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
            'assets/wss-lineup/../invalid.png';

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
            $this->createMock(LoggerInterface::class)
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

    private function image(
        int $id,
        string $filename
    ): GeneratedImage {
        $image = new GeneratedImage();

        $image->id = $id;
        $image->filename = $filename;
        $image->relative_path =
            'assets/wss-lineup/'.$filename;

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
