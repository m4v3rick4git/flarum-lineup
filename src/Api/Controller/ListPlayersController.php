<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wss\FlarumLineup\Model\Player;
use Wss\FlarumLineup\Model\Team;

final class ListPlayersController implements RequestHandlerInterface
{
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

        $team = Team::query()
            ->whereKey($teamId)
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
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(
                static function (Player $player): array {
                    return [
                        'id' => (int) $player->id,
                        'name' => (string) $player->name,
                        'age' => $player->age,
                        'shirtNumber' => $player->shirt_number,
                        'position' => $player->position,
                        'photoUrl' => $player->photo_url,
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
                'logoUrl' => $team->logo_url,
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
