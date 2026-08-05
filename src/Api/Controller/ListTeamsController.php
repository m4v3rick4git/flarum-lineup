<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\Model\Team;

final class ListTeamsController implements RequestHandlerInterface
{
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
                static function (Team $team): array {
                    return [
                        'id' => (int) $team->id,
                        'apiTeamId' => (int) $team->api_team_id,
                        'name' => (string) $team->name,
                        'code' => $team->code,
                        'country' => $team->country,
                        'founded' => $team->founded,
                        'logoUrl' => $team->logo_url,
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
