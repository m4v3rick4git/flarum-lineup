<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Closure;
use GdImage;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class RemoteImageFetcher
{
    private const ALLOWED_HOST = 'media.api-sports.io';

    private const MAX_BYTES = 5_000_000;

    private const MAX_WIDTH = 2_000;

    private const MAX_HEIGHT = 2_000;

    private const MAX_PIXELS = 4_000_000;

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

    public function fetch(?string $url): ?GdImage
    {
        if (!$this->isAllowedUrl($url)) {
            return null;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower(
            (string) ($parts['host'] ?? '')
        );

        if (!$this->resolvesOnlyToPublicAddresses($host)) {
            return null;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'allow_redirects' => false,
                    'connect_timeout' => 3.0,
                    'timeout' => 5.0,
                    'http_errors' => false,
                    'stream' => true,
                    'decode_content' => false,
                    'headers' => [
                        'Accept' => (
                            'image/png,image/jpeg,image/webp'
                        ),
                        'Accept-Encoding' => 'identity',
                        'User-Agent' => 'WSS-Lineup/1.1',
                    ],
                ]
            );
        } catch (Throwable $exception) {
            return null;
        }

        if (!$this->isAllowedResponse($response)) {
            return null;
        }

        $contents = $this->readLimitedBody($response);

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
            || !in_array(
                $type,
                [
                    IMAGETYPE_JPEG,
                    IMAGETYPE_PNG,
                    IMAGETYPE_WEBP,
                ],
                true
            )
        ) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

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

    private function isAllowedUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        if (
            strtolower((string) ($parts['scheme'] ?? ''))
                !== 'https'
            || strtolower((string) ($parts['host'] ?? ''))
                !== self::ALLOWED_HOST
        ) {
            return false;
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return false;
        }

        if (
            isset($parts['port'])
            && (int) $parts['port'] !== 443
        ) {
            return false;
        }

        $path = (string) ($parts['path'] ?? '');

        return preg_match(
            '#^/football/(?:players|teams)/[1-9][0-9]*\.png$#',
            $path
        ) === 1;
    }

    private function resolvesOnlyToPublicAddresses(
        string $host
    ): bool {
        $addresses = ($this->dnsResolver)($host);

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

        if (
            !in_array(
                $contentType,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true
            )
        ) {
            return false;
        }

        $contentLength = trim(
            $response->getHeaderLine('Content-Length')
        );

        if (
            $contentLength !== ''
            && (
                !ctype_digit($contentLength)
                || (int) $contentLength > self::MAX_BYTES
            )
        ) {
            return false;
        }

        return true;
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
                    min(8_192, $remaining + 1)
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
