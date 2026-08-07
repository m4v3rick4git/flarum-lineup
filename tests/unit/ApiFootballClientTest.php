<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Api\ApiFootballClient;
use Wss\FlarumLineup\Api\ApiFootballRequestException;
use Wss\FlarumLineup\Api\ApiKeyStore;
use Wss\FlarumLineup\Security\ApiKeyCipher;

final class ApiFootballClientTest extends TestCase
{
    private const ENCRYPTION_KEY =
        '0123456789abcdef0123456789abcdef'
        .'0123456789abcdef0123456789abcdef';

    public function testItFetchesAndMapsTeams(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

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
                                            'https://media.api-sports.io/football/teams/571.png'
                                        ),
                                    ],
                                ],
                                [
                                    'team' => [
                                        'id' => 572,
                                        'name' => 'Test Wien',
                                        'code' => null,
                                        'country' => 'Austria',
                                        'founded' => null,
                                        'national' => false,
                                        'logo' => null,
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->assertSame(
            [
                [
                    'apiTeamId' => 571,
                    'name' => 'Test Salzburg',
                    'code' => 'TSZ',
                    'country' => 'Austria',
                    'founded' => 1933,
                    'isNational' => false,
                    'logoUrl' => (
                        'https://media.api-sports.io/football/teams/571.png'
                    ),
                ],
                [
                    'apiTeamId' => 572,
                    'name' => 'Test Wien',
                    'code' => null,
                    'country' => 'Austria',
                    'founded' => null,
                    'isNational' => false,
                    'logoUrl' => null,
                ],
            ],
            $client->fetchTeams(218, 2026)
        );
    }

    public function testItFetchesAndMapsSquad(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

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
                                            'name' => 'Max Torwart',
                                            'age' => 28,
                                            'number' => 1,
                                            'position' => 'Goalkeeper',
                                            'photo' => (
                                                'https://media.api-sports.io/football/players/1001.png'
                                            ),
                                        ],
                                        [
                                            'id' => 1002,
                                            'name' => 'Simon Feldspieler',
                                            'age' => null,
                                            'number' => null,
                                            'position' => 'Defender',
                                            'photo' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->assertSame(
            [
                [
                    'apiPlayerId' => 1001,
                    'name' => 'Max Torwart',
                    'age' => 28,
                    'shirtNumber' => 1,
                    'position' => 'Goalkeeper',
                    'photoUrl' => (
                        'https://media.api-sports.io/football/players/1001.png'
                    ),
                ],
                [
                    'apiPlayerId' => 1002,
                    'name' => 'Simon Feldspieler',
                    'age' => null,
                    'shirtNumber' => null,
                    'position' => 'Defender',
                    'photoUrl' => null,
                ],
            ],
            $client->fetchSquad(571)
        );
    }

    public function testRateLimitStatusAndRetryDelayAreExposed(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(
                new Response(
                    429,
                    [
                        'Retry-After' => '12',
                    ],
                    json_encode(
                        [
                            'errors' => [
                                'rateLimit' => (
                                    'Too many requests.'
                                ),
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        try {
            $client->fetchSquad(571);
            $this->fail(
                'A rate-limit exception was expected.'
            );
        } catch (
            ApiFootballRequestException $exception
        ) {
            $this->assertSame(
                429,
                $exception->statusCode()
            );

            $this->assertSame(
                12,
                $exception->retryAfterSeconds()
            );

            $this->assertSame(
                'API-Football returned HTTP status 429.',
                $exception->getMessage()
            );
        }
    }

    public function testInvalidSquadTeamIdIsRejected(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient
            ->expects($this->never())
            ->method('request');

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->expectException(InvalidArgumentException::class);

        $client->fetchSquad(0);
    }

    public function testConnectionStatusIsStillParsed(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'status',
                $this->callback(
                    static function (array $options): bool {
                        return (
                            $options['headers']['x-apisports-key']
                                ?? null
                        ) === 'test-api-key'
                            && ($options['query'] ?? null) === [];
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
                                'subscription' => [
                                    'active' => true,
                                    'plan' => 'Free',
                                ],
                                'requests' => [
                                    'current' => 4,
                                    'limit_day' => 100,
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->assertSame(
            [
                'active' => true,
                'plan' => 'Free',
                'requestsCurrent' => 4,
                'requestsLimitDay' => 100,
            ],
            $client->testConnection()
        );
    }

    public function testInvalidLeagueIdIsRejected(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient
            ->expects($this->never())
            ->method('request');

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->expectException(InvalidArgumentException::class);

        $client->fetchTeams(0, 2026);
    }

    public function testInvalidSeasonIsRejected(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient
            ->expects($this->never())
            ->method('request');

        $client = new ApiFootballClient(
            $httpClient,
            $this->createApiKeyStore('test-api-key')
        );

        $this->expectException(InvalidArgumentException::class);

        $client->fetchTeams(218, 1800);
    }

    private function createApiKeyStore(
        ?string $apiKey
    ): ApiKeyStore {
        $cipher = new ApiKeyCipher(self::ENCRYPTION_KEY);
        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(ApiKeyStore::SETTING_KEY)
            ->willReturn(
                $apiKey !== null
                    ? $cipher->encrypt($apiKey)
                    : null
            );

        return new ApiKeyStore($settings, $cipher);
    }
}
