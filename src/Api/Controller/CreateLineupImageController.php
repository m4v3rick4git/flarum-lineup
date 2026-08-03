<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api\Controller;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Wss\FlarumLineup\Image\LineupImageRenderer;
use Wss\FlarumLineup\Lineup\FormationCatalog;
use Wss\FlarumLineup\Model\Player;
use Wss\FlarumLineup\Model\Team;

final class CreateLineupImageController implements
    RequestHandlerInterface
{
    private LineupImageRenderer $renderer;

    private FormationCatalog $formationCatalog;

    private Paths $paths;

    private UrlGenerator $urlGenerator;

    private LoggerInterface $logger;

    public function __construct(
        LineupImageRenderer $renderer,
        FormationCatalog $formationCatalog,
        Paths $paths,
        UrlGenerator $urlGenerator,
        LoggerInterface $logger
    ) {
        $this->renderer = $renderer;
        $this->formationCatalog = $formationCatalog;
        $this->paths = $paths;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function handle(
        ServerRequestInterface $request
    ): ResponseInterface {
        $actor = RequestUtil::getActor($request);

        $actor->assertCan(
            'wss-lineup.createLineup'
        );

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->validationError(
                '/',
                'A valid request body is required.'
            );
        }

        $teamId = filter_var(
            $body['teamId'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($teamId === false) {
            return $this->validationError(
                '/teamId',
                'A valid team ID is required.'
            );
        }

        $formation = $body['formation'] ?? null;

        if (
            !is_string($formation)
            || trim($formation) === ''
        ) {
            return $this->validationError(
                '/formation',
                'A formation is required.'
            );
        }

        $formation = trim($formation);

        if (!$this->formationCatalog->exists($formation)) {
            return $this->validationError(
                '/formation',
                'The requested formation is not supported.'
            );
        }

        $playerIds = $body['playerIds'] ?? null;

        if (!is_array($playerIds)) {
            return $this->validationError(
                '/playerIds',
                'Player IDs must be provided as an array.'
            );
        }

        $normalizedPlayerIds = [];

        foreach ($playerIds as $playerId) {
            $normalizedPlayerId = filter_var(
                $playerId,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($normalizedPlayerId === false) {
                return $this->validationError(
                    '/playerIds',
                    'Every player ID must be a positive integer.'
                );
            }

            $normalizedPlayerIds[] =
                (int) $normalizedPlayerId;
        }

        if (count($normalizedPlayerIds) !== 11) {
            return $this->validationError(
                '/playerIds',
                'Exactly eleven player IDs are required.'
            );
        }

        if (
            count(array_unique($normalizedPlayerIds))
            !== 11
        ) {
            return $this->validationError(
                '/playerIds',
                'Player IDs must be unique.'
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

        $playersById = Player::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->whereIn('id', $normalizedPlayerIds)
            ->get()
            ->keyBy('id');

        if ($playersById->count() !== 11) {
            return $this->validationError(
                '/playerIds',
                (
                    'All players must be active and belong '
                    .'to the selected team.'
                )
            );
        }

        $players = [];

        foreach ($normalizedPlayerIds as $playerId) {
            $player = $playersById->get($playerId);

            if (!$player instanceof Player) {
                return $this->validationError(
                    '/playerIds',
                    'A selected player could not be resolved.'
                );
            }

            $players[] = [
                'name' => (string) $player->name,
                'shirtNumber' => $player->shirt_number,
                'photoUrl' => $player->photo_url,
            ];
        }

        $filename = bin2hex(
            random_bytes(20)
        ).'.png';

        $relativePath =
            'assets/wss-lineup/'.$filename;

        $outputPath =
            $this->paths->public.'/'.$relativePath;

        try {
            $this->renderer->render(
                $outputPath,
                (string) $team->name,
                $team->logo_url,
                $formation,
                $players
            );
        } catch (Throwable $exception) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }

            $this->logger->error(
                'WSS Lineup image generation failed.',
                [
                    'actorId' => $actor->id,
                    'teamId' => $team->id,
                    'formation' => $formation,
                    'exception' => $exception,
                ]
            );

            return $this->errorResponse(
                500,
                'lineup_image_failed',
                'The lineup image could not be created.'
            );
        }

        return new JsonResponse([
            'success' => true,
            'url' => $this->urlGenerator
                ->to('forum')
                ->path($relativePath),
            'filename' => $filename,
        ]);
    }

    private function validationError(
        string $pointer,
        string $detail
    ): JsonResponse {
        return new JsonResponse(
            [
                'errors' => [
                    [
                        'status' => '422',
                        'code' => 'validation_error',
                        'source' => [
                            'pointer' => $pointer,
                        ],
                        'detail' => $detail,
                    ],
                ],
            ],
            422,
            [
                'Content-Type' =>
                    'application/vnd.api+json',
            ]
        );
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
                'Content-Type' =>
                    'application/vnd.api+json',
            ]
        );
    }
}
