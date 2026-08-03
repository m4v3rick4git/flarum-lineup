<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Sync\TeamSynchronizer;

final class SynchronizeTeamsController implements RequestHandlerInterface
{
    private TeamSynchronizer $teamSynchronizer;

    private LoggerInterface $logger;

    public function __construct(
        TeamSynchronizer $teamSynchronizer,
        LoggerInterface $logger
    ) {
        $this->teamSynchronizer = $teamSynchronizer;
        $this->logger = $logger;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertAdmin();

        try {
            $result = $this->teamSynchronizer->synchronize();
        } catch (Throwable $exception) {
            $this->logger->error(
                'WSS Lineup team synchronization failed.',
                [
                    'exception' => $exception,
                ]
            );

            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'status' => '502',
                            'code' => 'team_sync_failed',
                            'detail' => (
                                'The team synchronization failed.'
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
            'leagueId' => $result['leagueId'],
            'season' => $result['season'],
            'received' => $result['received'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'deactivated' => $result['deactivated'],
        ]);
    }
}
