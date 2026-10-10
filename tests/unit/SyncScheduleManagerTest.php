<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Scheduling\SyncScheduleManager;

final class SyncScheduleManagerTest extends TestCase
{
    public function testDailyScheduleArmsBeforeFirstAutomaticRun(): void
    {
        $settings = [
            'wss-lineup.auto_sync.squads.enabled' => '1',
            'wss-lineup.auto_sync.squads.frequency' => 'daily',
            'wss-lineup.auto_sync.squads.time' => '04:15',
        ];

        $manager = $this->manager($settings);

        $this->assertNull(
            $manager->claimDue(
                SyncScheduleManager::TARGET_SQUADS,
                $this->time('2026-10-10 10:00:00')
            )
        );

        $this->assertSame(
            '2026-10-10T04:15+02:00',
            $settings[
                'wss-lineup.auto_sync.squads._last_slot'
            ]
        );
    }

    public function testDailyScheduleRunsOnceForTheNextSlot(): void
    {
        $settings = [
            'wss-lineup.auto_sync.squads.enabled' => '1',
            'wss-lineup.auto_sync.squads.frequency' => 'daily',
            'wss-lineup.auto_sync.squads.time' => '04:15',
        ];

        $manager = $this->manager($settings);

        $manager->claimDue(
            SyncScheduleManager::TARGET_SQUADS,
            $this->time('2026-10-10 10:00:00')
        );

        $slot = $manager->claimDue(
            SyncScheduleManager::TARGET_SQUADS,
            $this->time('2026-10-11 04:15:00')
        );

        $this->assertSame(
            '2026-10-11T04:15+02:00',
            $slot
        );

        $this->assertNull(
            $manager->claimDue(
                SyncScheduleManager::TARGET_SQUADS,
                $this->time('2026-10-11 04:59:00')
            )
        );
    }

    public function testWeeklyScheduleUsesConfiguredWeekdayAndTime(): void
    {
        $settings = [
            'wss-lineup.auto_sync.teams.frequency' => 'weekly',
            'wss-lineup.auto_sync.teams.weekday' => '3',
            'wss-lineup.auto_sync.teams.time' => '03:30',
        ];

        $manager = $this->manager($settings);

        $this->assertSame(
            '2026-10-07T03:30+02:00',
            $manager->latestScheduledSlot(
                SyncScheduleManager::TARGET_TEAMS,
                $this->time('2026-10-10 19:00:00')
            )
        );
    }

    public function testMonthlyScheduleUsesDayOneToTwentyEight(): void
    {
        $settings = [
            'wss-lineup.auto_sync.teams.frequency' => 'monthly',
            'wss-lineup.auto_sync.teams.month_day' => '10',
            'wss-lineup.auto_sync.teams.time' => '04:00',
        ];

        $manager = $this->manager($settings);

        $this->assertSame(
            '2026-10-10T04:00+02:00',
            $manager->latestScheduledSlot(
                SyncScheduleManager::TARGET_TEAMS,
                $this->time('2026-10-10 19:00:00')
            )
        );

        $this->assertSame(
            '2026-09-10T04:00+02:00',
            $manager->latestScheduledSlot(
                SyncScheduleManager::TARGET_TEAMS,
                $this->time('2026-10-05 19:00:00')
            )
        );
    }

    public function testDisabledScheduleNeverClaimsARun(): void
    {
        $settings = [
            'wss-lineup.auto_sync.teams.enabled' => '0',
            'wss-lineup.auto_sync.teams.frequency' => 'daily',
            'wss-lineup.auto_sync.teams.time' => '04:00',
        ];

        $manager = $this->manager($settings);

        $this->assertNull(
            $manager->claimDue(
                SyncScheduleManager::TARGET_TEAMS,
                $this->time('2026-10-10 10:00:00')
            )
        );

        $this->assertNull(
            $manager->claimDue(
                SyncScheduleManager::TARGET_TEAMS,
                $this->time('2026-10-11 10:00:00')
            )
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function manager(
        array &$state
    ): SyncScheduleManager {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->willReturnCallback(
                static function (string $key) use (&$state) {
                    return $state[$key] ?? null;
                }
            );

        $settings
            ->method('set')
            ->willReturnCallback(
                static function (
                    string $key,
                    $value
                ) use (&$state): void {
                    $state[$key] = $value;
                }
            );

        return new SyncScheduleManager($settings);
    }

    private function time(
        string $value
    ): DateTimeImmutable {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone('Europe/Vienna')
        );
    }
}
