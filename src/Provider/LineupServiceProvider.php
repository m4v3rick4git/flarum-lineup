<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Api\ApiKeyStore;
use Wss\FlarumLineup\Image\LineupImageRenderer;
use Wss\FlarumLineup\Lineup\FormationCatalog;
use Wss\FlarumLineup\Security\ApiKeyCipher;
use Wss\FlarumLineup\Sync\SquadSynchronizer;
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
            LineupImageRenderer::class,
            static function (
                Container $container
            ): LineupImageRenderer {
                return new LineupImageRenderer(
                    $container->make(
                        FormationCatalog::class
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
                    $container->make(ConnectionInterface::class)
                );
            }
        );

        $this->container->singleton(
            SquadSynchronizer::class,
            function (Container $container): SquadSynchronizer {
                return new SquadSynchronizer(
                    $container->make(ApiFootballClient::class),
                    $container->make(ConnectionInterface::class)
                );
            }
        );
    }
}
