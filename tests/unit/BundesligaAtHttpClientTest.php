<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wss\FlarumLineup\DataProvider\BundesligaAtHttpClient;

final class BundesligaAtHttpClientTest extends TestCase
{
    public function testItFetchesClubsHtmlWithoutRedirects(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                '/de/klubs',
                $this->callback(
                    static function (array $options): bool {
                        return (
                            $options['allow_redirects']
                            ?? null
                        ) === false
                            && (
                                $options['stream']
                                ?? null
                            ) === true
                            && (
                                $options['decode_content']
                                ?? null
                            ) === false;
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => (
                            'text/html; charset=utf-8'
                        ),
                    ],
                    '<html>clubs</html>'
                )
            );

        $client = new BundesligaAtHttpClient(
            $httpClient
        );

        $this->assertSame(
            '<html>clubs</html>',
            $client->fetchClubsHtml()
        );
    }

    public function testItRejectsInvalidSquadPath(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $client = new BundesligaAtHttpClient(
            $httpClient
        );

        $this->expectException(
            RuntimeException::class
        );

        $client->fetchSquadHtml(
            'https://example.test/internal'
        );
    }

    public function testItRejectsRedirects(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(
                new Response(
                    307,
                    [
                        'Location' => (
                            'https://www.bundesliga.at/de'
                        ),
                    ]
                )
            );

        $client = new BundesligaAtHttpClient(
            $httpClient
        );

        $this->expectException(
            RuntimeException::class
        );

        $client->fetchClubsHtml();
    }

    public function testItRejectsUnexpectedContentType(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => 'application/json',
                    ],
                    '{}'
                )
            );

        $client = new BundesligaAtHttpClient(
            $httpClient
        );

        $this->expectException(
            RuntimeException::class
        );

        $client->fetchClubsHtml();
    }
}
