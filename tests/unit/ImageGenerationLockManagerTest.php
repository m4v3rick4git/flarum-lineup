<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\Image\ImageGenerationLockManager;
use Wss\FlarumLineup\Image\ImageGenerationThrottledException;

final class ImageGenerationLockManagerTest extends TestCase
{
    public function testItRunsAndReleasesTheLock(): void
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

        $manager = new ImageGenerationLockManager(
            $database
        );

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
            ->willReturn(
                (object) ['acquired' => 0]
            );

        $manager = new ImageGenerationLockManager(
            $database
        );

        try {
            $manager->run(
                static fn (): string => 'must not run'
            );

            $this->fail(
                'Expected throttling exception was not thrown.'
            );
        } catch (
            ImageGenerationThrottledException $exception
        ) {
            $this->assertSame(
                'busy',
                $exception->reason()
            );

            $this->assertSame(
                5,
                $exception->retryAfterSeconds()
            );
        }
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

        $manager = new ImageGenerationLockManager(
            $database
        );

        $this->expectException(RuntimeException::class);

        $manager->run(
            static function (): void {
                throw new RuntimeException(
                    'Rendering failed.'
                );
            }
        );
    }

    public function testNestedCallsReuseTheHeldLock(): void
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

        $manager = new ImageGenerationLockManager(
            $database
        );

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
