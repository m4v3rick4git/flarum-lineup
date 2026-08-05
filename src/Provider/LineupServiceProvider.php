<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Api\ApiKeyStore;
use Wss\FlarumLineup\Image\LineupImageRenderer;
use Wss\FlarumLineup\Image\RemoteImageFetcher;
use Wss\FlarumLineup\Lineup\FormationCatalog;
use Wss\FlarumLineup\Security\ApiKeyCipher;
use Wss\FlarumLineup\Sync\SquadSynchronizer;
use Wss\FlarumLineup\Sync\SyncLockManager;
use Wss\FlarumLineup\Sync\SyncSafetyGuard;
use Wss\FlarumLineup\Sync\TeamSynchronizer;

final class LineupServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            ApiKeyCipher::class,
            function (Container $container): ApiKeyCipher {
                $encryptionKey = trim(
                    (string) getenv('WSS_LINEUP_ENCRYPTION_KEY')
                );

                if ($encryptionKey === '') {
                    throw new RuntimeException(
                        'WSS_LINEUP_ENCRYPTION_KEY is not configured.'
                    );
                }

                return new ApiKeyCipher($encryptionKey);
            }
        );

        $this->container->singleton(
            ApiKeyStore::class,
            function (Container $container): ApiKeyStore {
                return new ApiKeyStore(
                    $container->make(
                        SettingsRepositoryInterface::class
                    ),
                    $container->make(ApiKeyCipher::class)
                );
            }
        );

        $this->container->singleton(
            ApiFootballClient::class,
            function (Container $container): ApiFootballClient {
                $httpClient = new Client([
                    'base_uri' => (
                        'https://v3.football.api-sports.io/'
                    ),
                    'connect_timeout' => 5.0,
                    'timeout' => 10.0,
                    'http_errors' => false,
                    'allow_redirects' => false,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);

                return new ApiFootballClient(
                    $httpClient,
                    $container->make(ApiKeyStore::class)
                );
            }
        );

        $this->container->singleton(
            FormationCatalog::class,
            static function (
                Container $container
            ): FormationCatalog {
                return new FormationCatalog();
            }
        );

        $this->container->singleton(
            RemoteImageFetcher::class,
            static function (
                Container $container
            ): RemoteImageFetcher {
                return new RemoteImageFetcher(
                    new Client([
                        'connect_timeout' => 3.0,
                        'timeout' => 5.0,
                        'http_errors' => false,
                        'allow_redirects' => false,
                    ])
                );
            }
        );
        $this->container->singleton(
            LineupImageRenderer::class,
            static function (
                Container $container
            ): LineupImageRenderer {
                return new LineupImageRenderer(
                    $container->make(
                        FormationCatalog::class
                    ),
                    $container->make(
                        RemoteImageFetcher::class
                    )
                );
            }
        );

        $this->container->singleton(
            SyncLockManager::class,
            function (
                Container $container
            ): SyncLockManager {
                return new SyncLockManager(
                    $container->make(
                        ConnectionInterface::class
                    ),
                    $container->make(
                        LoggerInterface::class
                    )
                );
            }
        );

        $this->container->singleton(
            TeamSynchronizer::class,
            function (Container $container): TeamSynchronizer {
                return new TeamSynchronizer(
                    $container->make(ApiFootballClient::class),
                    $container->make(
                        SettingsRepositoryInterface::class
                    ),
                    $container->make(ConnectionInterface::class),
                    $container->make(SyncSafetyGuard::class),
                    $container->make(SyncLockManager::class)
                );
            }
        );

        $this->container->singleton(
            SquadSynchronizer::class,
            function (Container $container): SquadSynchronizer {
                return new SquadSynchronizer(
                    $container->make(ApiFootballClient::class),
                    $container->make(ConnectionInterface::class),
                    $container->make(SyncSafetyGuard::class),
                    $container->make(SyncLockManager::class)
                );
            }
        );
    }
}
