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
use Wss\FlarumLineup\Api\ApiFootballRequestException;
use Wss\FlarumLineup\Model\Team;
use Wss\FlarumLineup\Sync\SquadSynchronizer;
use Wss\FlarumLineup\Sync\SyncAlreadyRunningException;

final class SynchronizeSquadsController implements RequestHandlerInterface
{
    private SquadSynchronizer $squadSynchronizer;

    private LoggerInterface $logger;

    public function __construct(
        SquadSynchronizer $squadSynchronizer,
        LoggerInterface $logger
    ) {
        $this->squadSynchronizer = $squadSynchronizer;
        $this->logger = $logger;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $teamId = is_array($body)
            ? ($body['teamId'] ?? null)
            : null;

        if ($teamId !== null) {
            return $this->synchronizeSingleTeam($teamId);
        }

        try {
            $result = $this->squadSynchronizer->synchronizeAll();
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }

        return new JsonResponse([
            'success' => true,
            'teams' => $result['teams'],
            'received' => $result['received'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'deactivated' => $result['deactivated'],
        ]);
    }

    /**
     * @param mixed $teamId
     */
    private function synchronizeSingleTeam(
        $teamId
    ): ResponseInterface {
        $normalizedTeamId = filter_var(
            $teamId,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($normalizedTeamId === false) {
            return $this->validationError(
                'A valid team ID is required.'
            );
        }

        $team = Team::query()
            ->where('id', $normalizedTeamId)
            ->where('is_active', true)
            ->first();

        if (!$team instanceof Team) {
            return $this->validationError(
                'The requested active team was not found.'
            );
        }

        try {
            $result = $this->squadSynchronizer
                ->synchronizeTeam($team);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }

        return new JsonResponse([
            'success' => true,
            'teams' => 1,
            'teamId' => $result['teamId'],
            'apiTeamId' => $result['apiTeamId'],
            'teamName' => $result['teamName'],
            'received' => $result['received'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'deactivated' => $result['deactivated'],
        ]);
    }

    private function errorResponse(
        Throwable $exception
    ): ResponseInterface {
        if ($exception instanceof SyncAlreadyRunningException) {
            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'status' => '409',
                            'code' => 'sync_already_running',
                            'detail' => (
                                'Another WSS Lineup synchronization '
                                .'is already running.'
                            ),
                        ],
                    ],
                ],
                409,
                [
                    'Content-Type' => 'application/vnd.api+json',
                ]
            );
        }

        $this->logger->error(
            'WSS Lineup squad synchronization failed.',
            [
                'exception' => $exception,
            ]
        );

        if (
            $exception instanceof ApiFootballRequestException
            && $exception->statusCode() === 429
        ) {
            $retryAfter = $exception->retryAfterSeconds();

            $headers = [
                'Content-Type' => 'application/vnd.api+json',
            ];

            if ($retryAfter !== null) {
                $headers['Retry-After'] = (string) $retryAfter;
            }

            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'status' => '429',
                            'code' => 'api_rate_limited',
                            'detail' => (
                                'API-Football rate limit reached.'
                            ),
                            'meta' => [
                                'retryAfter' => $retryAfter,
                            ],
                        ],
                    ],
                ],
                429,
                $headers
            );
        }

        return new JsonResponse(
            [
                'errors' => [
                    [
                        'status' => '502',
                        'code' => 'squad_sync_failed',
                        'detail' => (
                            'The squad synchronization failed.'
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
                            'pointer' => '/teamId',
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
