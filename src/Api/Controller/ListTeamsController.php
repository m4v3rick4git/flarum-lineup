<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\Image\CachedImageAccess;
use Wss\FlarumLineup\Model\Team;

final class ListTeamsController implements RequestHandlerInterface
{
    private CachedImageAccess $cachedImageAccess;

    public function __construct(
        CachedImageAccess $cachedImageAccess
    ) {
        $this->cachedImageAccess = $cachedImageAccess;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        RequestUtil::getActor($request)->assertCan(
            'wss-lineup.createLineup'
        );

        $teams = Team::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(
                function (Team $team): array {
                    return [
                        'id' => (int) $team->id,
                        'apiTeamId' => (int) $team->api_team_id,
                        'name' => (string) $team->name,
                        'code' => $team->code,
                        'country' => $team->country,
                        'founded' => $team->founded,
                        'logoUrl' => $this->cachedImageAccess->teamLogoUrl(
                            (int) $team->api_team_id
                        ),
                    ];
                }
            )
            ->values()
            ->all();

        return new JsonResponse([
            'teams' => $teams,
        ]);
    }
}
