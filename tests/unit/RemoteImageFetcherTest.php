<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use GdImage;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\RemoteImageFetcher;

final class RemoteImageFetcherTest extends TestCase
{
    private const VALID_URL =
        'https://media.api-sports.io'
        .'/football/players/123.png';

    public function testItDownloadsAndDecodesAValidImage(): void
    {
        $png = $this->createPng(20, 20);
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                self::VALID_URL,
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
                            ) === false
                            && (
                                $options['connect_timeout']
                                ?? null
                            ) === 3.0
                            && (
                                $options['timeout']
                                ?? null
                            ) === 5.0;
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [
                        'Content-Type' => 'image/png',
                        'Content-Length' => (string) strlen(
                            $png
                        ),
                    ],
                    $png
                )
            );

        $fetcher = $this->createFetcher($httpClient);
        $image = $fetcher->fetch(self::VALID_URL);

        $this->assertInstanceOf(GdImage::class, $image);
        $this->assertSame(20, imagesx($image));
        $this->assertSame(20, imagesy($image));

        imagedestroy($image);
    }

    /**
     * @dataProvider disallowedUrlProvider
     */
    public function testItRejectsDisallowedUrls(
        string $url
    ): void {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull($fetcher->fetch($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disallowedUrlProvider(): array
    {
        return [
            'HTTP scheme' => [
                'http://media.api-sports.io'
                .'/football/players/123.png',
            ],
            'foreign host' => [
                'https://example.test'
                .'/football/players/123.png',
            ],
            'host suffix attack' => [
                'https://media.api-sports.io.example.test'
                .'/football/players/123.png',
            ],
            'userinfo' => [
                'https://user:password@media.api-sports.io'
                .'/football/players/123.png',
            ],
            'foreign port' => [
                'https://media.api-sports.io:8443'
                .'/football/players/123.png',
            ],
            'query string' => [
                'https://media.api-sports.io'
                .'/football/players/123.png?target=internal',
            ],
            'fragment' => [
                'https://media.api-sports.io'
                .'/football/players/123.png#fragment',
            ],
            'wrong directory' => [
                'https://media.api-sports.io'
                .'/football/other/123.png',
            ],
            'path traversal' => [
                'https://media.api-sports.io'
                .'/football/players/../teams/123.png',
            ],
            'non-numeric identifier' => [
                'https://media.api-sports.io'
                .'/football/players/test.png',
            ],
            'SVG image' => [
                'https://media.api-sports.io'
                .'/football/players/123.svg',
            ],
        ];
    }

    /**
     * @dataProvider unsafeAddressProvider
     */
    public function testItRejectsUnsafeDnsAddresses(
        string $address
    ): void {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $fetcher = new RemoteImageFetcher(
            $httpClient,
            static fn (string $host): array => [$address]
        );

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeAddressProvider(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 private 10' => ['10.0.0.1'],
            'IPv4 private 172' => ['172.16.0.1'],
            'IPv4 private 192' => ['192.168.0.1'],
            'IPv4 link-local' => ['169.254.169.254'],
            'IPv6 loopback' => ['::1'],
            'IPv6 private' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
        ];
    }

    public function testItRejectsMissingDnsResults(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->never())
            ->method('request');

        $fetcher = new RemoteImageFetcher(
            $httpClient,
            static fn (string $host): array => []
        );

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItDoesNotFollowRedirects(): void
    {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(
                new Response(
                    302,
                    [
                        'Location' =>
                            'https://127.0.0.1/internal',
                    ]
                )
            );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsUnexpectedContentTypes(): void
    {
        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'text/html',
                ],
                '<html>not an image</html>'
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsEncodedResponses(): void
    {
        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Encoding' => 'gzip',
                ],
                'compressed-data'
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsAnOversizedContentLength(): void
    {
        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Length' => '5000001',
                ],
                ''
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsAnOversizedStreamBody(): void
    {
        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'image/png',
                ],
                str_repeat('a', 5_000_001)
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsInvalidImageData(): void
    {
        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'image/png',
                ],
                'not-a-real-png'
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    public function testItRejectsExcessiveImageDimensions(): void
    {
        $png = $this->createPng(2_001, 1);

        $httpClient = $this->clientReturning(
            new Response(
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Length' => (string) strlen(
                        $png
                    ),
                ],
                $png
            )
        );

        $fetcher = $this->createFetcher($httpClient);

        $this->assertNull(
            $fetcher->fetch(self::VALID_URL)
        );
    }

    private function createFetcher(
        ClientInterface $httpClient
    ): RemoteImageFetcher {
        return new RemoteImageFetcher(
            $httpClient,
            static fn (string $host): array => [
                '93.184.216.34',
                '2606:2800:220:1:248:1893:25c8:1946',
            ]
        );
    }

    private function clientReturning(
        Response $response
    ): ClientInterface {
        $httpClient = $this->createMock(
            ClientInterface::class
        );

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        return $httpClient;
    }

    private function createPng(
        int $width,
        int $height
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();

        imagedestroy($image);

        $this->assertIsString($contents);

        return $contents;
    }
}
