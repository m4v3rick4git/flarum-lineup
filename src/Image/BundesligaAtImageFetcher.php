<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Closure;
use GdImage;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class BundesligaAtImageFetcher implements
    RemoteImageSource
{
    private const CLUB_IMAGE_HOST =
        'bundesliga-craftcms-production-bucket.'
        .'s3.eu-central-1.amazonaws.com';

    private const PLAYER_IMAGE_HOST =
        'bundesliga-production-bucket.'
        .'s3.eu-central-1.amazonaws.com';

    private const OPTIMIZER_HOST =
        'www.bundesliga.at';

    private const OPTIMIZER_PATH =
        '/_next/image';

    private const PLAYER_IMAGE_WIDTH = 256;

    private const PLAYER_IMAGE_QUALITY = 75;

    private const MAX_BYTES = 2_000_000;

    private const MAX_WIDTH = 1_024;

    private const MAX_HEIGHT = 1_536;

    private const MAX_PIXELS = 1_572_864;

    private ClientInterface $httpClient;

    /**
     * @var Closure(string): array<int, string>
     */
    private Closure $dnsResolver;

    /**
     * @param Closure(string): array<int, string>|null $dnsResolver
     */
    public function __construct(
        ClientInterface $httpClient,
        ?Closure $dnsResolver = null
    ) {
        $this->httpClient = $httpClient;

        $this->dnsResolver = $dnsResolver
            ?? static function (string $host): array {
                $records = @dns_get_record(
                    $host,
                    DNS_A | DNS_AAAA
                );

                if (!is_array($records)) {
                    return [];
                }

                $addresses = [];

                foreach ($records as $record) {
                    if (
                        isset($record['ip'])
                        && is_string($record['ip'])
                    ) {
                        $addresses[] = $record['ip'];
                    }

                    if (
                        isset($record['ipv6'])
                        && is_string($record['ipv6'])
                    ) {
                        $addresses[] = $record['ipv6'];
                    }
                }

                return array_values(
                    array_unique($addresses)
                );
            };
    }

    public function fetch(
        ?string $url
    ): ?GdImage {
        if (!$this->isAllowedSourceUrl($url)) {
            return null;
        }

        $request = $this->buildRequest($url);

        if ($request === null) {
            return null;
        }

        $requestParts = parse_url(
            $request['url']
        );

        if (!is_array($requestParts)) {
            return null;
        }

        $requestHost = strtolower(
            (string) ($requestParts['host'] ?? '')
        );

        if (
            !$this->resolvesOnlyToPublicAddresses(
                $requestHost
            )
        ) {
            return null;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $request['url'],
                [
                    'allow_redirects' => false,
                    'connect_timeout' => 3.0,
                    'timeout' => 8.0,
                    'http_errors' => false,
                    'stream' => true,
                    'decode_content' => false,
                    'headers' => [
                        'Accept' => $request['accept'],
                        'Accept-Encoding' => 'identity',
                        'User-Agent' => 'WSS-Lineup/0.2',
                    ],
                ]
            );
        } catch (Throwable $exception) {
            return null;
        }

        if (!$this->isAllowedResponse($response)) {
            return null;
        }

        $contents = $this->readLimitedBody(
            $response
        );

        if ($contents === null) {
            return null;
        }

        $imageInformation = @getimagesizefromstring(
            $contents
        );

        if (!is_array($imageInformation)) {
            return null;
        }

        $width = $imageInformation[0] ?? null;
        $height = $imageInformation[1] ?? null;
        $type = $imageInformation[2] ?? null;

        if (
            !is_int($width)
            || !is_int($height)
            || $width < 1
            || $height < 1
            || $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
            || ($width * $height) > self::MAX_PIXELS
        ) {
            return null;
        }

        if (
            !is_int($type)
            || !$this->isSupportedImageType($type)
        ) {
            return null;
        }

        $image = @imagecreatefromstring(
            $contents
        );

        if (!$image instanceof GdImage) {
            return null;
        }

        if (
            imagesx($image) !== $width
            || imagesy($image) !== $height
        ) {
            imagedestroy($image);

            return null;
        }

        return $image;
    }

    /**
     * @return array{url: string, accept: string}|null
     */
    private function buildRequest(
        string $sourceUrl
    ): ?array {
        $parts = parse_url($sourceUrl);

        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower(
            (string) ($parts['host'] ?? '')
        );

        if ($host === self::PLAYER_IMAGE_HOST) {
            $query = http_build_query(
                [
                    'url' => $sourceUrl,
                    'w' => self::PLAYER_IMAGE_WIDTH,
                    'q' => self::PLAYER_IMAGE_QUALITY,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );

            return [
                'url' => (
                    'https://'
                    .self::OPTIMIZER_HOST
                    .self::OPTIMIZER_PATH
                    .'?'
                    .$query
                ),
                'accept' => $this->acceptHeader(),
            ];
        }

        return [
            'url' => $sourceUrl,
            'accept' => $this->acceptHeader(),
        ];
    }

    private function acceptHeader(): string
    {
        if ($this->supportsWebp()) {
            return 'image/webp,image/png,image/jpeg';
        }

        return 'image/png,image/jpeg';
    }

    private function isAllowedSourceUrl(
        ?string $url
    ): bool {
        if ($url === null || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        if (
            strtolower(
                (string) ($parts['scheme'] ?? '')
            ) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            return false;
        }

        $host = strtolower(
            (string) ($parts['host'] ?? '')
        );

        $path = (string) (
            $parts['path'] ?? ''
        );

        if ($host === self::CLUB_IMAGE_HOST) {
            return preg_match(
                '#^/craft-cms-oefbl/Klubs/Icons/'
                .'[1-9][0-9]*\.(?:png|jpe?g|webp)$#iD',
                $path
            ) === 1;
        }

        if ($host === self::PLAYER_IMAGE_HOST) {
            return preg_match(
                '#^/images/player-team-assignment/'
                .'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-'
                .'[0-9a-f]{4}-[0-9a-f]{12}'
                .'\.(?:png|jpe?g|webp)$#iD',
                $path
            ) === 1;
        }

        return false;
    }

    private function resolvesOnlyToPublicAddresses(
        string $host
    ): bool {
        $addresses = ($this->dnsResolver)(
            $host
        );

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (
                filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE
                    | FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedResponse(
        ResponseInterface $response
    ): bool {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contentEncoding = strtolower(
            trim(
                $response->getHeaderLine(
                    'Content-Encoding'
                )
            )
        );

        if (
            $contentEncoding !== ''
            && $contentEncoding !== 'identity'
        ) {
            return false;
        }

        $contentType = strtolower(
            trim(
                explode(
                    ';',
                    $response->getHeaderLine(
                        'Content-Type'
                    ),
                    2
                )[0]
            )
        );

        if (!$this->isSupportedContentType($contentType)) {
            return false;
        }

        $contentLength = trim(
            $response->getHeaderLine(
                'Content-Length'
            )
        );

        if (
            $contentLength !== ''
            && (
                !ctype_digit($contentLength)
                || (int) $contentLength
                    > self::MAX_BYTES
            )
        ) {
            return false;
        }

        return true;
    }

    private function isSupportedContentType(
        string $contentType
    ): bool {
        if (
            $contentType === 'image/jpeg'
            || $contentType === 'image/png'
        ) {
            return true;
        }

        return $contentType === 'image/webp'
            && $this->supportsWebp();
    }

    private function isSupportedImageType(
        int $type
    ): bool {
        if (
            $type === IMAGETYPE_JPEG
            || $type === IMAGETYPE_PNG
        ) {
            return true;
        }

        return $type === IMAGETYPE_WEBP
            && $this->supportsWebp();
    }

    private function supportsWebp(): bool
    {
        return defined('IMG_WEBP')
            && (imagetypes() & IMG_WEBP) === IMG_WEBP;
    }

    private function readLimitedBody(
        ResponseInterface $response
    ): ?string {
        $body = $response->getBody();
        $contents = '';

        try {
            while (!$body->eof()) {
                $remaining = self::MAX_BYTES
                    - strlen($contents);

                if ($remaining < 0) {
                    return null;
                }

                $chunk = $body->read(
                    min(
                        8_192,
                        $remaining + 1
                    )
                );

                if ($chunk === '') {
                    if ($body->eof()) {
                        break;
                    }

                    return null;
                }

                $contents .= $chunk;

                if (
                    strlen($contents)
                    > self::MAX_BYTES
                ) {
                    return null;
                }
            }
        } catch (Throwable $exception) {
            return null;
        }

        return $contents !== ''
            ? $contents
            : null;
    }
}
