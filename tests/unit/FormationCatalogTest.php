<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Lineup\FormationCatalog;

final class FormationCatalogTest extends TestCase
{
    public function testItProvidesSupportedFormations(): void
    {
        $catalog = new FormationCatalog();

        $this->assertSame(
            [
                '4-2-3-1',
                '4-3-3',
                '4-4-2',
                '3-5-2',
                '3-4-3',
                '5-3-2',
            ],
            $catalog->keys()
        );
    }

    /**
     * @dataProvider formationProvider
     */
    public function testEveryFormationHasElevenValidSlots(
        string $formation
    ): void {
        $catalog = new FormationCatalog();
        $slots = $catalog->slots($formation);

        $this->assertCount(11, $slots);

        foreach ($slots as $slot) {
            $this->assertArrayHasKey('x', $slot);
            $this->assertArrayHasKey('y', $slot);

            $this->assertGreaterThanOrEqual(
                0,
                $slot['x']
            );

            $this->assertLessThanOrEqual(
                100,
                $slot['x']
            );

            $this->assertGreaterThanOrEqual(
                0,
                $slot['y']
            );

            $this->assertLessThanOrEqual(
                100,
                $slot['y']
            );
        }
    }

    public function testUnknownFormationIsRejected(): void
    {
        $catalog = new FormationCatalog();

        $this->expectException(
            InvalidArgumentException::class
        );

        $catalog->slots('2-2-6');
    }

    /**
     * @return array<string, array{string}>
     */
    public function formationProvider(): array
    {
        return [
            '4-2-3-1' => ['4-2-3-1'],
            '4-3-3' => ['4-3-3'],
            '4-4-2' => ['4-4-2'],
            '3-5-2' => ['3-5-2'],
            '3-4-3' => ['3-4-3'],
            '5-3-2' => ['5-3-2'],
        ];
    }
}
