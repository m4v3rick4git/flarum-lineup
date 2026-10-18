<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wss\FlarumLineup\Image\CachedImageAccess;
use Wss\FlarumLineup\Image\LineupImageRenderer;
use Wss\FlarumLineup\Lineup\FormationCatalog;

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

    public function testItRendersHorizontalPitchStripes(): void
    {
        $cachedImageAccess = new class implements CachedImageAccess {
            public function teamLogoUrl(
                string $provider,
                string $providerTeamId
            ): ?string {
                return null;
            }

            public function playerPhotoUrl(
                string $provider,
                string $providerPlayerId
            ): ?string {
                return null;
            }

            public function load(
                ?string $url
            ): ?GdImage {
                return null;
            }
        };

        $renderer = new LineupImageRenderer(
            new FormationCatalog(),
            $cachedImageAccess
        );

        $players = [];

        for ($index = 1; $index <= 11; ++$index) {
            $players[] = [
                'name' => 'Player '.$index,
                'shirtNumber' => $index,
                'photoUrl' => null,
            ];
        }

        $outputPath = tempnam(
            sys_get_temp_dir(),
            'wss-lineup-'
        );

        self::assertNotFalse($outputPath);

        try {
            $renderer->render(
                $outputPath,
                'Test Team',
                null,
                '4-2-3-1',
                $players
            );

            $image = imagecreatefrompng(
                $outputPath
            );

            self::assertInstanceOf(
                GdImage::class,
                $image
            );

            try {
                $firstStripe = imagecolorsforindex(
                    $image,
                    imagecolorat(
                        $image,
                        100,
                        220
                    )
                );

                $secondStripe = imagecolorsforindex(
                    $image,
                    imagecolorat(
                        $image,
                        100,
                        360
                    )
                );

                $secondStripeRight = imagecolorsforindex(
                    $image,
                    imagecolorat(
                        $image,
                        1100,
                        360
                    )
                );

                self::assertSame(
                    [
                        'red' => 48,
                        'green' => 139,
                        'blue' => 65,
                        'alpha' => 0,
                    ],
                    $firstStripe
                );

                self::assertSame(
                    [
                        'red' => 39,
                        'green' => 123,
                        'blue' => 56,
                        'alpha' => 0,
                    ],
                    $secondStripe
                );

                self::assertSame(
                    $secondStripe,
                    $secondStripeRight
                );
            } finally {
                imagedestroy($image);
            }
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }
}