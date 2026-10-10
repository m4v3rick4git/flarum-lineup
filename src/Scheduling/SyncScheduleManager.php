<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;

final class SyncScheduleManager
{
    public const TARGET_TEAMS = 'teams';

    public const TARGET_SQUADS = 'squads';

    private const TIMEZONE = 'Europe/Vienna';

    private SettingsRepositoryInterface $settings;

    public function __construct(
        SettingsRepositoryInterface $settings
    ) {
        $this->settings = $settings;
    }

    public function claimDue(
        string $target,
        ?DateTimeImmutable $now = null
    ): ?string {
        $this->assertTarget($target);

        $now = $this->viennaTime($now);
        $configuration = $this->configuration($target);
        $configurationHash = hash(
            'sha256',
            json_encode(
                $configuration,
                JSON_THROW_ON_ERROR
            )
        );

        $latestSlot = $this->latestScheduledSlot(
            $target,
            $now
        );

        $storedHash = $this->stringSetting(
            $this->internalKey($target, 'config_hash')
        );

        if ($storedHash !== $configurationHash) {
            $this->settings->set(
                $this->internalKey($target, 'config_hash'),
                $configurationHash
            );

            $this->settings->set(
                $this->internalKey($target, 'last_slot'),
                $latestSlot
            );

            $this->settings->set(
                $this->internalKey($target, 'last_error'),
                ''
            );

            return null;
        }

        if (!$configuration['enabled']) {
            return null;
        }

        $lastSlot = $this->stringSetting(
            $this->internalKey($target, 'last_slot')
        );

        if ($lastSlot === $latestSlot) {
            return null;
        }

        $this->settings->set(
            $this->internalKey($target, 'last_slot'),
            $latestSlot
        );

        $this->settings->set(
            $this->internalKey($target, 'last_attempt_at'),
            $now->format(DATE_ATOM)
        );

        $this->settings->set(
            $this->internalKey($target, 'last_error'),
            ''
        );

        return $latestSlot;
    }

    public function latestScheduledSlot(
        string $target,
        DateTimeImmutable $now
    ): string {
        $this->assertTarget($target);

        $now = $this->viennaTime($now);
        $configuration = $this->configuration($target);

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $configuration['time'])
        );

        if ($configuration['frequency'] === 'daily') {
            $slot = $now->setTime($hour, $minute, 0);

            if ($slot > $now) {
                $slot = $slot->modify('-1 day');
            }

            return $slot->format('Y-m-d\TH:iP');
        }

        if ($configuration['frequency'] === 'weekly') {
            $currentWeekday = (int) $now->format('N');
            $daysBack = $currentWeekday
                - $configuration['weekday'];

            if ($daysBack < 0) {
                $daysBack += 7;
            }

            $slot = $now
                ->modify(sprintf('-%d days', $daysBack))
                ->setTime($hour, $minute, 0);

            if ($slot > $now) {
                $slot = $slot->modify('-7 days');
            }

            return $slot->format('Y-m-d\TH:iP');
        }

        $slot = $now
            ->setDate(
                (int) $now->format('Y'),
                (int) $now->format('n'),
                $configuration['monthDay']
            )
            ->setTime($hour, $minute, 0);

        if ($slot > $now) {
            $previousMonth = $now->modify(
                'first day of previous month'
            );

            $slot = $previousMonth
                ->setDate(
                    (int) $previousMonth->format('Y'),
                    (int) $previousMonth->format('n'),
                    $configuration['monthDay']
                )
                ->setTime($hour, $minute, 0);
        }

        return $slot->format('Y-m-d\TH:iP');
    }

    public function recordSuccess(
        string $target,
        ?DateTimeImmutable $now = null
    ): void {
        $this->assertTarget($target);

        $this->settings->set(
            $this->internalKey($target, 'last_success_at'),
            $this->viennaTime($now)->format(DATE_ATOM)
        );

        $this->settings->set(
            $this->internalKey($target, 'last_error'),
            ''
        );
    }

    public function recordSkipped(
        string $target
    ): void {
        $this->assertTarget($target);

        $this->settings->set(
            $this->internalKey($target, 'last_error'),
            ''
        );
    }

    public function recordFailure(
        string $target,
        string $message
    ): void {
        $this->assertTarget($target);

        $this->settings->set(
            $this->internalKey($target, 'last_error'),
            substr(trim($message), 0, 500)
        );
    }

    /**
     * @return array{
     *     enabled: bool,
     *     frequency: string,
     *     time: string,
     *     weekday: int,
     *     monthDay: int
     * }
     */
    private function configuration(
        string $target
    ): array {
        $defaultFrequency = $target === self::TARGET_TEAMS
            ? 'monthly'
            : 'daily';

        $defaultTime = $target === self::TARGET_TEAMS
            ? '04:00'
            : '04:15';

        $frequency = $this->stringSetting(
            $this->publicKey($target, 'frequency'),
            $defaultFrequency
        );

        if (
            !in_array(
                $frequency,
                ['daily', 'weekly', 'monthly'],
                true
            )
        ) {
            $frequency = $defaultFrequency;
        }

        $time = $this->stringSetting(
            $this->publicKey($target, 'time'),
            $defaultTime
        );

        if (!$this->isValidTime($time)) {
            $time = $defaultTime;
        }

        $weekday = $this->integerSetting(
            $this->publicKey($target, 'weekday'),
            1,
            1,
            7
        );

        $monthDay = $this->integerSetting(
            $this->publicKey($target, 'month_day'),
            2,
            1,
            28
        );

        return [
            'enabled' => $this->booleanSetting(
                $this->publicKey($target, 'enabled')
            ),
            'frequency' => $frequency,
            'time' => $time,
            'weekday' => $weekday,
            'monthDay' => $monthDay,
        ];
    }

    private function publicKey(
        string $target,
        string $suffix
    ): string {
        return sprintf(
            'wss-lineup.auto_sync.%s.%s',
            $target,
            $suffix
        );
    }

    private function internalKey(
        string $target,
        string $suffix
    ): string {
        return sprintf(
            'wss-lineup.auto_sync.%s._%s',
            $target,
            $suffix
        );
    }

    private function booleanSetting(
        string $key
    ): bool {
        $value = $this->settings->get($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(
            strtolower(trim($value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    private function stringSetting(
        string $key,
        string $default = ''
    ): string {
        $value = $this->settings->get($key);

        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : $default;
    }

    private function integerSetting(
        string $key,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $value = filter_var(
            $this->settings->get($key),
            FILTER_VALIDATE_INT
        );

        if (
            $value === false
            || $value < $minimum
            || $value > $maximum
        ) {
            return $default;
        }

        return $value;
    }

    private function isValidTime(
        string $time
    ): bool {
        if (
            preg_match(
                '/^(\d{2}):(\d{2})$/D',
                $time,
                $matches
            ) !== 1
        ) {
            return false;
        }

        return (int) $matches[1] <= 23
            && (int) $matches[2] <= 59;
    }

    private function viennaTime(
        ?DateTimeImmutable $now
    ): DateTimeImmutable {
        $timezone = new DateTimeZone(self::TIMEZONE);

        if ($now === null) {
            return new DateTimeImmutable(
                'now',
                $timezone
            );
        }

        return $now->setTimezone($timezone);
    }

    private function assertTarget(
        string $target
    ): void {
        if (
            !in_array(
                $target,
                [
                    self::TARGET_TEAMS,
                    self::TARGET_SQUADS,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown synchronization schedule target.'
            );
        }
    }
}
