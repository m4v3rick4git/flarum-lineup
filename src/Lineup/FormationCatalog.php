<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Lineup;

use InvalidArgumentException;

final class FormationCatalog
{
    /**
     * @var array<string, array<int, array{x: int, y: int}>>
     */
    private const FORMATIONS = [
        '4-2-3-1' => [
            ['x' => 50, 'y' => 90],
            ['x' => 14, 'y' => 73],
            ['x' => 38, 'y' => 77],
            ['x' => 62, 'y' => 77],
            ['x' => 86, 'y' => 73],
            ['x' => 37, 'y' => 56],
            ['x' => 63, 'y' => 56],
            ['x' => 18, 'y' => 35],
            ['x' => 50, 'y' => 39],
            ['x' => 82, 'y' => 35],
            ['x' => 50, 'y' => 15],
        ],
        '4-3-3' => [
            ['x' => 50, 'y' => 90],
            ['x' => 14, 'y' => 73],
            ['x' => 38, 'y' => 77],
            ['x' => 62, 'y' => 77],
            ['x' => 86, 'y' => 73],
            ['x' => 25, 'y' => 52],
            ['x' => 50, 'y' => 57],
            ['x' => 75, 'y' => 52],
            ['x' => 18, 'y' => 25],
            ['x' => 50, 'y' => 19],
            ['x' => 82, 'y' => 25],
        ],
        '4-4-2' => [
            ['x' => 50, 'y' => 90],
            ['x' => 14, 'y' => 73],
            ['x' => 38, 'y' => 77],
            ['x' => 62, 'y' => 77],
            ['x' => 86, 'y' => 73],
            ['x' => 14, 'y' => 49],
            ['x' => 38, 'y' => 54],
            ['x' => 62, 'y' => 54],
            ['x' => 86, 'y' => 49],
            ['x' => 38, 'y' => 23],
            ['x' => 62, 'y' => 23],
        ],
        '3-5-2' => [
            ['x' => 50, 'y' => 90],
            ['x' => 25, 'y' => 75],
            ['x' => 50, 'y' => 79],
            ['x' => 75, 'y' => 75],
            ['x' => 11, 'y' => 48],
            ['x' => 34, 'y' => 55],
            ['x' => 50, 'y' => 48],
            ['x' => 66, 'y' => 55],
            ['x' => 89, 'y' => 48],
            ['x' => 38, 'y' => 22],
            ['x' => 62, 'y' => 22],
        ],
        '3-4-3' => [
            ['x' => 50, 'y' => 90],
            ['x' => 25, 'y' => 75],
            ['x' => 50, 'y' => 79],
            ['x' => 75, 'y' => 75],
            ['x' => 14, 'y' => 51],
            ['x' => 38, 'y' => 56],
            ['x' => 62, 'y' => 56],
            ['x' => 86, 'y' => 51],
            ['x' => 18, 'y' => 24],
            ['x' => 50, 'y' => 18],
            ['x' => 82, 'y' => 24],
        ],
        '5-3-2' => [
            ['x' => 50, 'y' => 90],
            ['x' => 9, 'y' => 70],
            ['x' => 29, 'y' => 76],
            ['x' => 50, 'y' => 79],
            ['x' => 71, 'y' => 76],
            ['x' => 91, 'y' => 70],
            ['x' => 25, 'y' => 50],
            ['x' => 50, 'y' => 56],
            ['x' => 75, 'y' => 50],
            ['x' => 38, 'y' => 22],
            ['x' => 62, 'y' => 22],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::FORMATIONS);
    }

    public function exists(string $formation): bool
    {
        return isset(self::FORMATIONS[$formation]);
    }

    /**
     * @return array<int, array{x: int, y: int}>
     */
    public function slots(string $formation): array
    {
        if (!$this->exists($formation)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The formation "%s" is not supported.',
                    $formation
                )
            );
        }

        return self::FORMATIONS[$formation];
    }
}
