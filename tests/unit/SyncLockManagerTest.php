<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\Sync\SyncAlreadyRunningException;
use Wss\FlarumLineup\Sync\SyncLockManager;

final class SyncLockManagerTest extends TestCase
{
    private const ACQUIRE_SQL = <<<'SQL'
SELECT GET_LOCK(
    CONCAT(
        'wss-lineup.sync.',
        LEFT(
            SHA2(COALESCE(DATABASE(), ''), 256),
            32
        )
    ),
    0
) AS acquired
SQL;

    private const RELEASE_SQL = <<<'SQL'
SELECT RELEASE_LOCK(
    CONCAT(
        'wss-lineup.sync.',
        LEFT(
            SHA2(COALESCE(DATABASE(), ''), 256),
            32
        )
    )
) AS released
SQL;

    public function testItRunsAndReleasesTheLock(): void
    {
        $database = $this->createMock(
            ConnectionInterface::class
        );

        $database
            ->expects($this->exactly(2))
            ->method('selectOne')
            ->withConsecutive(
                [self::ACQUIRE_SQL, [], false],
                [self::RELEASE_SQL, [], false]
            )
            ->willReturnOnConsecutiveCalls(
                (object) ['acquired' => 1],
                (object) ['released' => 1]
            );

        $manager = new SyncLockManager($database);

        $this->assertSame(
            'completed',
            $manager->run(
                static fn (): string => 'completed'
            )
        );
    }

    public function testItRejectsAnOccupiedLock(): void
    {
        $database = $this->createMock(
            ConnectionInterface::class
        );

        $database
            ->expects($this->once())
            ->method('selectOne')
            ->with(self::ACQUIRE_SQL, [], false)
            ->willReturn(
                (object) ['acquired' => 0]
            );

        $manager = new SyncLockManager($database);

        $this->expectException(
            SyncAlreadyRunningException::class
        );

        $manager->run(
            static fn (): string => 'must not run'
        );
    }

    public function testItReleasesAfterCallbackFailure(): void
    {
        $database = $this->createMock(
            ConnectionInterface::class
        );

        $database
            ->expects($this->exactly(2))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['acquired' => 1],
                (object) ['released' => 1]
            );

        $manager = new SyncLockManager($database);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test failure');

        $manager->run(
            static function (): void {
                throw new RuntimeException('Test failure');
            }
        );
    }

    public function testNestedRunsReuseTheHeldLock(): void
    {
        $database = $this->createMock(
            ConnectionInterface::class
        );

        $database
            ->expects($this->exactly(2))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['acquired' => 1],
                (object) ['released' => 1]
            );

        $manager = new SyncLockManager($database);

        $result = $manager->run(
            static function () use ($manager): string {
                return $manager->run(
                    static fn (): string => 'nested'
                );
            }
        );

        $this->assertSame('nested', $result);
    }
}
