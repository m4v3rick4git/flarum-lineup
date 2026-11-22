<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Sync;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;
use Wss\FlarumLineup\Model\Team;

final class SquadSynchronizationRunner
{
    public function __construct(
        private SquadSynchronizer $synchronizer,
        private DataProviderResolver $providers,
        private LoggerInterface $logger
    ) {
    }

    /** @return array{provider:string,teams:int,received:int,created:int,updated:int,deactivated:int,failed:int} */
    public function run(?callable $progress = null): array
    {
        $provider = $this->providers->resolve()->providerKey();
        $teams = Team::query()->where('provider', $provider)->where('is_active', true)->orderBy('id')->get();
        if ($teams->isEmpty()) {
            throw new RuntimeException('No active Bundesliga teams for synchronization.');
        }
        $result = ['provider'=>$provider, 'teams'=>0, 'received'=>0, 'created'=>0, 'updated'=>0, 'deactivated'=>0, 'failed'=>0];
        foreach ($teams as $index => $team) {
            if ($progress !== null) {
                $progress($index + 1, $teams->count(), (string) $team->name);
            }
            try {
                try {
                    $data = $this->synchronizer->synchronizeTeam($team);
                } catch (SyncAlreadyRunningException) {
                    sleep(5);
                    $data = $this->synchronizer->synchronizeTeam($team);
                }
                ++$result['teams'];
                foreach (['received','created','updated','deactivated'] as $field) {
                    $result[$field] += $data[$field];
                }
            } catch (Throwable $error) {
                ++$result['failed'];
                $this->logger->error('Lineup squad synchronization failed', [
                    'teamId'=>(int) $team->id, 'errorClass'=>$error::class,
                ]);
            }
            if ($index + 1 < $teams->count()) {
                sleep(1);
            }
        }
        return $result;
    }
}
