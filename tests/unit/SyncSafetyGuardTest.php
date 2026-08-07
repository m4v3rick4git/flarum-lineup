<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\Sync\SyncSafetyGuard;

final class SyncSafetyGuardTest extends TestCase
{
    public function testItAcceptsAnInitialTeamSync(): void
    {
        $guard = new SyncSafetyGuard();

        $guard->assertTeamResponseIsComplete(12, 0);

        $this->addToAssertionCount(1);
    }

    public function testItAcceptsANormalTeamSync(): void
    {
        $guard = new SyncSafetyGuard();

        $guard->assertTeamResponseIsComplete(11, 12);

        $this->addToAssertionCount(1);
    }

    public function testItRejectsAnEmptyTeamList(): void
    {
        $guard = new SyncSafetyGuard();

        $this->expectException(RuntimeException::class);

        $guard->assertTeamResponseIsComplete(0, 12);
    }

    public function testItRejectsASeverelyReducedTeamList(): void
    {
        $guard = new SyncSafetyGuard();

        $this->expectException(RuntimeException::class);

        $guard->assertTeamResponseIsComplete(5, 12);
    }

    public function testItAcceptsANormalSquad(): void
    {
        $guard = new SyncSafetyGuard();

        $guard->assertSquadResponseIsComplete(25, 27);

        $this->addToAssertionCount(1);
    }

    public function testItAcceptsAnInitialSquad(): void
    {
        $guard = new SyncSafetyGuard();

        $guard->assertSquadResponseIsComplete(22, 0);

        $this->addToAssertionCount(1);
    }

    public function testItRejectsFewerThanElevenPlayers(): void
    {
        $guard = new SyncSafetyGuard();

        $this->expectException(RuntimeException::class);

        $guard->assertSquadResponseIsComplete(10, 0);
    }

    public function testItRejectsASeverelyReducedSquad(): void
    {
        $guard = new SyncSafetyGuard();

        $this->expectException(RuntimeException::class);

        $guard->assertSquadResponseIsComplete(12, 30);
    }
}
