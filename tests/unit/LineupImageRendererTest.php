<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wss\FlarumLineup\Image\LineupImageRenderer;

final class LineupImageRendererTest extends TestCase
{
    public function testItUsesBundledReadableFonts(): void
    {
        $reflection = new ReflectionClass(
            LineupImageRenderer::class
        );

        foreach (
            ['FONT_REGULAR', 'FONT_BOLD']
            as $constantName
        ) {
            $constant = $reflection->getReflectionConstant(
                $constantName
            );

            self::assertNotFalse($constant);

            $fontPath = $constant->getValue();

            self::assertStringContainsString(
                '/resources/fonts/',
                $fontPath
            );

            self::assertStringNotContainsString(
                '/usr/share/fonts/',
                $fontPath
            );

            self::assertFileExists(
                $fontPath
            );

            self::assertIsReadable(
                $fontPath
            );
        }
    }
}
