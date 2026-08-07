<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Model\GeneratedImage;

final class GeneratedImageTest extends TestCase
{
    public function testItUsesTimestamps(): void
    {
        $image = new GeneratedImage();

        self::assertTrue(
            $image->usesTimestamps()
        );
    }
}
