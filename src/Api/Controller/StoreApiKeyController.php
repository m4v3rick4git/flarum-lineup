<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\Api\ApiKeyStore;

final class StoreApiKeyController implements RequestHandlerInterface
{
    private ApiKeyStore $apiKeyStore;

    public function __construct(ApiKeyStore $apiKeyStore)
    {
        $this->apiKeyStore = $apiKeyStore;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $apiKey = is_array($body)
            ? ($body['apiKey'] ?? null)
            : null;

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return $this->validationError(
                'A non-empty API key is required.'
            );
        }

        $apiKey = trim($apiKey);

        if (strlen($apiKey) > 512) {
            return $this->validationError(
                'The API key must not exceed 512 characters.'
            );
        }

        $this->apiKeyStore->store($apiKey);

        return new JsonResponse([
            'configured' => true,
            'source' => ApiKeyStore::SOURCE_DATABASE,
            'stored' => true,
        ]);
    }

    private function validationError(
        string $detail
    ): ResponseInterface {
        return new JsonResponse(
            [
                'errors' => [
                    [
                        'status' => '422',
                        'code' => 'validation_error',
                        'source' => [
                            'pointer' => '/apiKey',
                        ],
                        'detail' => $detail,
                    ],
                ],
            ],
            422,
            [
                'Content-Type' => 'application/vnd.api+json',
            ]
        );
    }
}
