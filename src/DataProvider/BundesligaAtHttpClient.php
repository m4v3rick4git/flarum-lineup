<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class BundesligaAtHttpClient implements
    BundesligaAtSource
{
    private const CLUBS_PATH = '/de/klubs';

    private const MAX_HTML_BYTES = 2_000_000;

    private ClientInterface $httpClient;

    public function __construct(
        ClientInterface $httpClient
    ) {
        $this->httpClient = $httpClient;
    }

    public function fetchClubsHtml(): string
    {
        return $this->requestHtml(
            self::CLUBS_PATH
        );
    }

    public function fetchSquadHtml(
        string $squadPath
    ): string {
        if (
            preg_match(
                '#^/de/team/[a-z0-9-]+/[1-9][0-9]*/kader$#D',
                $squadPath
            ) !== 1
        ) {
            throw new RuntimeException(
                'The Bundesliga.at squad path is invalid.'
            );
        }

        return $this->requestHtml(
            $squadPath
        );
    }

    private function requestHtml(
        string $path
    ): string {
        try {
            $response = $this->httpClient->request(
                'GET',
                $path,
                [
                    'allow_redirects' => false,
                    'connect_timeout' => 5.0,
                    'timeout' => 12.0,
                    'http_errors' => false,
                    'stream' => true,
                    'decode_content' => false,
                    'headers' => [
                        'Accept' => (
                            'text/html,application/xhtml+xml'
                        ),
                        'Accept-Encoding' => 'identity',
                        'User-Agent' => 'WSS-Lineup/0.2',
                    ],
                ]
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The Bundesliga.at request failed.',
                0,
                $exception
            );
        }

        $this->assertValidResponse(
            $response
        );

        $html = $this->readLimitedBody(
            $response
        );

        if ($html === null) {
            throw new RuntimeException(
                'The Bundesliga.at response body is invalid.'
            );
        }

        return $html;
    }

    private function assertValidResponse(
        ResponseInterface $response
    ): void {
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                sprintf(
                    'Bundesliga.at returned HTTP %d.',
                    $response->getStatusCode()
                )
            );
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
            throw new RuntimeException(
                'Bundesliga.at returned an encoded response.'
            );
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
                    'text/html',
                    'application/xhtml+xml',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Bundesliga.at returned an unexpected content type.'
            );
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
                    > self::MAX_HTML_BYTES
            )
        ) {
            throw new RuntimeException(
                'The Bundesliga.at response is too large.'
            );
        }
    }

    private function readLimitedBody(
        ResponseInterface $response
    ): ?string {
        $body = $response->getBody();
        $contents = '';

        try {
            while (!$body->eof()) {
                $remaining = self::MAX_HTML_BYTES
                    - strlen($contents);

                if ($remaining < 0) {
                    return null;
                }

                $chunk = $body->read(
                    min(
                        16_384,
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
                    > self::MAX_HTML_BYTES
                ) {
                    return null;
                }
            }
        } catch (Throwable $exception) {
            return null;
        }

        return trim($contents) !== ''
            ? $contents
            : null;
    }
}
