<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Wss\FlarumLineup\Api\ApiFootballClient;

final class TestApiKeyController implements RequestHandlerInterface
{
    private ApiFootballClient $apiFootballClient;

    public function __construct(
        ApiFootballClient $apiFootballClient
    ) {
        $this->apiFootballClient = $apiFootballClient;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertAdmin();

        try {
            $status = $this->apiFootballClient->testConnection();
        } catch (Throwable $exception) {
            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'status' => '502',
                            'code' => 'api_connection_failed',
                            'detail' => (
                                'The API-Football connection test failed.'
                            ),
                        ],
                    ],
                ],
                502,
                [
                    'Content-Type' => 'application/vnd.api+json',
                ]
            );
        }

        return new JsonResponse([
            'success' => true,
            'active' => $status['active'],
            'plan' => $status['plan'],
            'requestsCurrent' => $status['requestsCurrent'],
            'requestsLimitDay' => $status['requestsLimitDay'],
        ]);
    }
}
