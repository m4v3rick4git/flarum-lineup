<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use RuntimeException;

final class SyncSafetyGuard
{
    private const MINIMUM_SQUAD_SIZE = 11;

    public function assertTeamResponseIsComplete(
        int $received,
        int $existingActive
    ): void {
        if ($received < 1) {
            throw new RuntimeException(
                'API-Football returned an empty team list.'
            );
        }

        if (
            $existingActive > 0
            && $received < (int) ceil($existingActive / 2)
        ) {
            throw new RuntimeException(
                'The team response is unexpectedly incomplete.'
            );
        }
    }

    public function assertSquadResponseIsComplete(
        int $received,
        int $existingActive
    ): void {
        if ($received < self::MINIMUM_SQUAD_SIZE) {
            throw new RuntimeException(
                'The squad response contains fewer than 11 players.'
            );
        }

        if (
            $existingActive >= self::MINIMUM_SQUAD_SIZE
            && $received < (int) ceil($existingActive / 2)
        ) {
            throw new RuntimeException(
                'The squad response is unexpectedly incomplete.'
            );
        }
    }
}
