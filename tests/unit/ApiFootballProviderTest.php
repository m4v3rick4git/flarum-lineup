<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Api\ApiKeyStore;
use Wss\FlarumLineup\DataProvider\ApiFootballProvider;

final class ApiFootballProviderTest extends TestCase
{
    public function testItReturnsItsProviderKey(): void
    {
        $provider = new ApiFootballProvider(
            $this->createApiFootballClient(
                $this->createMock(ClientInterface::class)
            ),
            $this->createProviderSettings(218, 2026)
        );

        $this->assertSame(
            'api-football',
            $provider->providerKey()
        );
    }

    public function testItFetchesAndMapsTeams(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'teams',
                $this->callback(
                    static function (array $options): bool {
                        return (
                            $options['headers']['x-apisports-key']
                                ?? null
                        ) === 'test-api-key'
                            && (
                                $options['query']['league']
                                    ?? null
                            ) === 218
                            && (
                                $options['query']['season']
                                    ?? null
                            ) === 2026;
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode(
                        [
                            'errors' => [],
                            'response' => [
                                [
                                    'team' => [
                                        'id' => 571,
                                        'name' => 'Test Salzburg',
                                        'code' => 'TSZ',
                                        'country' => 'Austria',
                                        'founded' => 1933,
                                        'national' => false,
                                        'logo' => (
                                            'https://media.api-sports.io/'
                                            .'football/teams/571.png'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $provider = new ApiFootballProvider(
            $this->createApiFootballClient($httpClient),
            $this->createProviderSettings(218, 2026)
        );

        $this->assertSame(
            [
                [
                    'providerTeamId' => '571',
                    'name' => 'Test Salzburg',
                    'code' => 'TSZ',
                    'country' => 'Austria',
                    'founded' => 1933,
                    'isNational' => false,
                    'logoUrl' => (
                        'https://media.api-sports.io/'
                        .'football/teams/571.png'
                    ),
                ],
            ],
            $provider->fetchTeams()
        );
    }

    public function testItFetchesAndMapsSquad(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'players/squads',
                $this->callback(
                    static function (array $options): bool {
                        return (
                            $options['headers']['x-apisports-key']
                                ?? null
                        ) === 'test-api-key'
                            && (
                                $options['query']['team']
                                    ?? null
                            ) === 571;
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode(
                        [
                            'errors' => [],
                            'response' => [
                                [
                                    'team' => [
                                        'id' => 571,
                                        'name' => 'Test Salzburg',
                                    ],
                                    'players' => [
                                        [
                                            'id' => 1001,
                                            'name' => 'Test Player',
                                            'age' => 24,
                                            'number' => 10,
                                            'position' => 'Midfielder',
                                            'photo' => (
                                                'https://media.api-sports.io/'
                                                .'football/players/1001.png'
                                            ),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $provider = new ApiFootballProvider(
            $this->createApiFootballClient($httpClient),
            $this->createProviderSettings(218, 2026)
        );

        $this->assertSame(
            [
                [
                    'providerPlayerId' => '1001',
                    'name' => 'Test Player',
                    'age' => 24,
                    'shirtNumber' => 10,
                    'position' => 'Midfielder',
                    'photoUrl' => (
                        'https://media.api-sports.io/'
                        .'football/players/1001.png'
                    ),
                ],
            ],
            $provider->fetchSquad('571')
        );
    }

    public function testInvalidProviderTeamIdIsRejected(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $provider = new ApiFootballProvider(
            $this->createApiFootballClient($httpClient),
            $this->createProviderSettings(218, 2026)
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $provider->fetchSquad('invalid');
    }

    public function testInvalidSeasonIsRejected(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $provider = new ApiFootballProvider(
            $this->createApiFootballClient($httpClient),
            $this->createProviderSettings(218, 1800)
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $provider->fetchTeams();
    }

    public function testMissingLeagueIdIsRejected(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->willReturnCallback(
                static function (string $key) {
                    if ($key === 'wss-lineup.season') {
                        return '2026';
                    }

                    return null;
                }
            );

        $provider = new ApiFootballProvider(
            $this->createApiFootballClient($httpClient),
            $settings
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $provider->fetchTeams();
    }

    private function createApiFootballClient(
        ClientInterface $httpClient
    ): ApiFootballClient {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn('test-api-key');

        return new ApiFootballClient(
            $httpClient,
            new ApiKeyStore($settings)
        );
    }

    private function createProviderSettings(
        ?int $leagueId,
        ?int $season
    ): SettingsRepositoryInterface {
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->willReturnCallback(
                static function (string $key) use (
                    $leagueId,
                    $season
                ) {
                    if ($key === 'wss-lineup.league_id') {
                        return $leagueId;
                    }

                    if ($key === 'wss-lineup.season') {
                        return $season;
                    }

                    return null;
                }
            );

        return $settings;
    }
}
