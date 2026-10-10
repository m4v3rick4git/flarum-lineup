<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\CompositeRemoteImageSource;
use Wss\FlarumLineup\Image\RemoteImageSource;

final class CompositeRemoteImageSourceTest extends TestCase
{
    public function testItUsesTheFirstSourceThatReturnsAnImage(): void
    {
        $first = $this->createMock(
            RemoteImageSource::class
        );

        $first
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        $image = imagecreatetruecolor(
            10,
            10
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $second = $this->createMock(
            RemoteImageSource::class
        );

        $second
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($image);

        $composite = new CompositeRemoteImageSource(
            [
                $first,
                $second,
            ]
        );

        $result = $composite->fetch(
            'https://example.test/image.png'
        );

        $this->assertSame(
            $image,
            $result
        );

        imagedestroy($image);
    }
}
