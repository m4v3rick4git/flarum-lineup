<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\Api\ApiPayloadValidator;

final class ApiPayloadValidatorTest extends TestCase
{
    private ApiPayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ApiPayloadValidator();
    }

    public function testItValidatesATeam(): void
    {
        $result = $this->validator->team([
            'id' => 123,
            'name' => 'Test FC',
            'code' => 'TFC',
            'country' => 'Austria',
            'founded' => 1900,
            'national' => false,
            'logo' => (
                'https://media.api-sports.io'
                .'/football/teams/123.png'
            ),
        ]);

        $this->assertSame(123, $result['apiTeamId']);
        $this->assertSame('Test FC', $result['name']);
        $this->assertSame('TFC', $result['code']);
        $this->assertSame('Austria', $result['country']);
        $this->assertSame(1900, $result['founded']);
        $this->assertFalse($result['isNational']);
        $this->assertSame(
            'https://media.api-sports.io'
            .'/football/teams/123.png',
            $result['logoUrl']
        );
    }

    public function testItValidatesAPlayer(): void
    {
        $result = $this->validator->player([
            'id' => 456,
            'name' => 'Test Player',
            'age' => 25,
            'number' => 10,
            'position' => 'Midfielder',
            'photo' => (
                'https://media.api-sports.io'
                .'/football/players/456.png'
            ),
        ]);

        $this->assertSame(456, $result['apiPlayerId']);
        $this->assertSame('Test Player', $result['name']);
        $this->assertSame(25, $result['age']);
        $this->assertSame(10, $result['shirtNumber']);
        $this->assertSame(
            'Midfielder',
            $result['position']
        );
    }

    public function testOptionalFieldsMayBeNull(): void
    {
        $team = $this->validator->team([
            'id' => 123,
            'name' => 'Test FC',
            'national' => false,
        ]);

        $player = $this->validator->player([
            'id' => 456,
            'name' => 'Test Player',
        ]);

        $this->assertNull($team['code']);
        $this->assertNull($team['country']);
        $this->assertNull($team['founded']);
        $this->assertNull($team['logoUrl']);

        $this->assertNull($player['age']);
        $this->assertNull($player['shirtNumber']);
        $this->assertNull($player['position']);
        $this->assertNull($player['photoUrl']);
    }

    /**
     * @dataProvider invalidTeamProvider
     *
     * @param array<string, mixed> $team
     */
    public function testItRejectsInvalidTeams(array $team): void
    {
        $this->expectException(RuntimeException::class);

        $this->validator->team($team);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidTeamProvider(): array
    {
        $valid = [
            'id' => 123,
            'name' => 'Test FC',
            'national' => false,
            'logo' => (
                'https://media.api-sports.io'
                .'/football/teams/123.png'
            ),
        ];

        return [
            'missing ID' => [
                array_merge($valid, ['id' => null]),
            ],
            'negative ID' => [
                array_merge($valid, ['id' => -1]),
            ],
            'overlong name' => [
                array_merge(
                    $valid,
                    ['name' => str_repeat('a', 151)]
                ),
            ],
            'control character' => [
                array_merge(
                    $valid,
                    ['name' => "Test\nFC"]
                ),
            ],
            'wrong national type' => [
                array_merge($valid, ['national' => 1]),
            ],
            'invalid founding year' => [
                array_merge($valid, ['founded' => 1700]),
            ],
            'wrong logo host' => [
                array_merge(
                    $valid,
                    [
                        'logo' => (
                            'https://example.test'
                            .'/football/teams/123.png'
                        ),
                    ]
                ),
            ],
            'logo ID mismatch' => [
                array_merge(
                    $valid,
                    [
                        'logo' => (
                            'https://media.api-sports.io'
                            .'/football/teams/999.png'
                        ),
                    ]
                ),
            ],
            'logo query string' => [
                array_merge(
                    $valid,
                    [
                        'logo' => (
                            'https://media.api-sports.io'
                            .'/football/teams/123.png?target=x'
                        ),
                    ]
                ),
            ],
        ];
    }

    /**
     * @dataProvider invalidPlayerProvider
     *
     * @param array<string, mixed> $player
     */
    public function testItRejectsInvalidPlayers(
        array $player
    ): void {
        $this->expectException(RuntimeException::class);

        $this->validator->player($player);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPlayerProvider(): array
    {
        $valid = [
            'id' => 456,
            'name' => 'Test Player',
            'age' => 25,
            'number' => 10,
            'position' => 'Midfielder',
            'photo' => (
                'https://media.api-sports.io'
                .'/football/players/456.png'
            ),
        ];

        return [
            'missing ID' => [
                array_merge($valid, ['id' => null]),
            ],
            'age too low' => [
                array_merge($valid, ['age' => 5]),
            ],
            'age too high' => [
                array_merge($valid, ['age' => 100]),
            ],
            'zero shirt number' => [
                array_merge($valid, ['number' => 0]),
            ],
            'overlong position' => [
                array_merge(
                    $valid,
                    ['position' => str_repeat('x', 51)]
                ),
            ],
            'photo ID mismatch' => [
                array_merge(
                    $valid,
                    [
                        'photo' => (
                            'https://media.api-sports.io'
                            .'/football/players/999.png'
                        ),
                    ]
                ),
            ],
            'photo path traversal' => [
                array_merge(
                    $valid,
                    [
                        'photo' => (
                            'https://media.api-sports.io'
                            .'/football/players/../teams/456.png'
                        ),
                    ]
                ),
            ],
        ];
    }
}
