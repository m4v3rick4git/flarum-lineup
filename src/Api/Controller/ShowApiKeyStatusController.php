<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\Api\ApiKeyStore;

final class ShowApiKeyStatusController implements RequestHandlerInterface
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

        return new JsonResponse([
            'configured' => $this->apiKeyStore->isConfigured(),
            'source' => $this->apiKeyStore->source(),
            'stored' => $this->apiKeyStore->hasStoredKey(),
        ]);
    }
}
