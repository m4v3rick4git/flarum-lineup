<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\DataProvider\DataProviderResolver;
use Wss\FlarumLineup\Image\CachedImageAccess;
use Wss\FlarumLineup\Model\Player;
use Wss\FlarumLineup\Model\Team;

final class ListPlayersController implements RequestHandlerInterface
{
    private CachedImageAccess $cachedImageAccess;

    private DataProviderResolver $dataProviderResolver;

    public function __construct(
        CachedImageAccess $cachedImageAccess,
        DataProviderResolver $dataProviderResolver
    ) {
        $this->cachedImageAccess = $cachedImageAccess;
        $this->dataProviderResolver = $dataProviderResolver;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertCan(
            'wss-lineup.createLineup'
        );

        $query = $request->getQueryParams();

        $teamId = filter_var(
            $query['teamId'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($teamId === false) {
            return $this->errorResponse(
                400,
                'invalid_team_id',
                'A valid team ID is required.'
            );
        }

        $providerKey = $this->dataProviderResolver
            ->resolve()
            ->providerKey();

        $team = Team::query()
            ->whereKey($teamId)
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->first();

        if (!$team instanceof Team) {
            return $this->errorResponse(
                404,
                'team_not_found',
                'The requested team was not found.'
            );
        }

        $players = Player::query()
            ->where('team_id', $team->id)
            ->where('provider', $providerKey)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(
                function (Player $player): array {
                    return [
                        'id' => (int) $player->id,
                        'name' => (string) $player->name,
                        'age' => $player->age,
                        'shirtNumber' => $player->shirt_number,
                        'position' => $player->position,
                        'photoUrl' => (
                            $this->cachedImageAccess->playerPhotoUrl(
                                (string) $player->provider,
                                (string) $player->provider_player_id
                            )
                        ),
                    ];
                }
            )
            ->values()
            ->all();

        return new JsonResponse([
            'team' => [
                'id' => (int) $team->id,
                'name' => (string) $team->name,
                'code' => $team->code,
                'logoUrl' => (
                    $this->cachedImageAccess->teamLogoUrl(
                        (string) $team->provider,
                        (string) $team->provider_team_id
                    )
                ),
            ],
            'players' => $players,
        ]);
    }

    private function errorResponse(
        int $status,
        string $code,
        string $detail
    ): JsonResponse {
        return new JsonResponse(
            [
                'errors' => [
                    [
                        'status' => (string) $status,
                        'code' => $code,
                        'detail' => $detail,
                    ],
                ],
            ],
            $status,
            [
                'Content-Type' => 'application/vnd.api+json',
            ]
        );
    }
}
