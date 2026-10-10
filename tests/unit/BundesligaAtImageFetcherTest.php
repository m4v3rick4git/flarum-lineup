<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\BundesligaAtImageFetcher;

final class BundesligaAtImageFetcherTest extends TestCase
{
    private const TEAM_URL =
        'https://bundesliga-craftcms-production-bucket.'
        .'s3.eu-central-1.amazonaws.com/'
        .'craft-cms-oefbl/Klubs/Icons/6428.png';

    private const PLAYER_URL =
        'https://bundesliga-production-bucket.'
        .'s3.eu-central-1.amazonaws.com/'
        .'images/player-team-assignment/'
        .'e4199221-a6d2-4c0a-a4f3-10965a1048fb.png';

    private const PLAYER_OPTIMIZER_URL =
        'https://www.bundesliga.at/_next/image?'
        .'url=https%3A%2F%2Fbundesliga-production-bucket.'
        .'s3.eu-central-1.amazonaws.com%2Fimages%2F'
        .'player-team-assignment%2F'
        .'e4199221-a6d2-4c0a-a4f3-10965a1048fb.png'
        .'&w=256&q=75';

    public function testItDownloadsAValidTeamImageDirectly(): void
    {
        $png = $this->createPng();

        $client = $this->createMock(
            ClientInterface::class
        );

        $client
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                self::TEAM_URL,
                $this->callback(
                    static function (array $options): bool {
                        return (
                            ($options['allow_redirects'] ?? null) === false
                            && ($options['stream'] ?? null) === true
                        );
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => 'image/png',
                        'Content-Length' => (
                            (string) strlen($png)
                        ),
                    ],
                    $png
                )
            );

        $fetcher = $this->fetcher(
            $client
        );

        $image = $fetcher->fetch(
            self::TEAM_URL
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        imagedestroy($image);
    }

    public function testItUsesOfficialOptimizerForPlayerImages(): void
    {
        $png = $this->createPng();

        $client = $this->createMock(
            ClientInterface::class
        );

        $client
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                self::PLAYER_OPTIMIZER_URL,
                $this->callback(
                    static function (array $options): bool {
                        $headers = $options['headers'] ?? [];

                        return (
                            ($options['allow_redirects'] ?? null) === false
                            && ($options['stream'] ?? null) === true
                            && isset($headers['Accept'])
                            && is_string($headers['Accept'])
                            && str_contains(
                                $headers['Accept'],
                                'image/png'
                            )
                        );
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => 'image/png',
                    ],
                    $png
                )
            );

        $fetcher = $this->fetcher(
            $client
        );

        $image = $fetcher->fetch(
            self::PLAYER_URL
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        imagedestroy($image);
    }

    /**
     * @dataProvider invalidUrlProvider
     */
    public function testItRejectsUnexpectedUrls(
        string $url
    ): void {
        $client = $this->createMock(
            ClientInterface::class
        );

        $client
            ->expects($this->never())
            ->method('request');

        $fetcher = $this->fetcher(
            $client
        );

        $this->assertNull(
            $fetcher->fetch($url)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'foreign host' => [
                'https://example.test/image.png',
            ],
            'query string' => [
                self::TEAM_URL.'?target=internal',
            ],
            'optimizer supplied by caller' => [
                self::PLAYER_OPTIMIZER_URL,
            ],
            'wrong club path' => [
                'https://bundesliga-craftcms-production-bucket.'
                .'s3.eu-central-1.amazonaws.com/'
                .'other/6428.png',
            ],
            'wrong player path' => [
                'https://bundesliga-production-bucket.'
                .'s3.eu-central-1.amazonaws.com/'
                .'images/other/'
                .'e4199221-a6d2-4c0a-a4f3-10965a1048fb.png',
            ],
            'invalid player identifier' => [
                'https://bundesliga-production-bucket.'
                .'s3.eu-central-1.amazonaws.com/'
                .'images/player-team-assignment/'
                .'../../secret.png',
            ],
        ];
    }

    public function testItRejectsPrivateDnsAddress(): void
    {
        $client = $this->createMock(
            ClientInterface::class
        );

        $client
            ->expects($this->never())
            ->method('request');

        $fetcher = new BundesligaAtImageFetcher(
            $client,
            static fn (string $host): array => [
                '127.0.0.1',
            ]
        );

        $this->assertNull(
            $fetcher->fetch(
                self::PLAYER_URL
            )
        );
    }

    public function testItRejectsOversizedResponseByContentLength(): void
    {
        $client = $this->createMock(
            ClientInterface::class
        );

        $client
            ->expects($this->once())
            ->method('request')
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => 'image/png',
                        'Content-Length' => '2000001',
                    ],
                    ''
                )
            );

        $fetcher = $this->fetcher(
            $client
        );

        $this->assertNull(
            $fetcher->fetch(
                self::TEAM_URL
            )
        );
    }

    private function fetcher(
        ClientInterface $client
    ): BundesligaAtImageFetcher {
        return new BundesligaAtImageFetcher(
            $client,
            static fn (string $host): array => [
                '93.184.216.34',
            ]
        );
    }

    private function createPng(): string
    {
        $image = imagecreatetruecolor(
            20,
            20
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();

        imagedestroy($image);

        $this->assertIsString(
            $contents
        );

        return $contents;
    }
}
