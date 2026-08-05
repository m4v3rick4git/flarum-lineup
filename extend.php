<?php

/*
 * This file is part of m4v3rick4git/flarum-lineup.
 *
 * Copyright (c) 2026 m4v3rick.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Wss\FlarumLineup;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Illuminate\Console\Scheduling\Event;
use Wss\FlarumLineup\Api\Controller\CreateLineupImageController;
use Wss\FlarumLineup\Api\Controller\DeleteApiKeyController;
use Wss\FlarumLineup\Api\Controller\ListPlayersController;
use Wss\FlarumLineup\Api\Controller\ListTeamsController;
use Wss\FlarumLineup\Api\Controller\StoreApiKeyController;
use Wss\FlarumLineup\Api\Controller\SynchronizeSquadsController;
use Wss\FlarumLineup\Api\Controller\SynchronizeTeamsController;
use Wss\FlarumLineup\Api\Controller\TestApiKeyController;
use Wss\FlarumLineup\Api\Controller\ShowApiKeyStatusController;
use Wss\FlarumLineup\Console\SynchronizeSquadsCommand;
use Wss\FlarumLineup\Console\SynchronizeTeamsCommand;
use Wss\FlarumLineup\Listener\HandlePostedGeneratedImages;
use Wss\FlarumLineup\Listener\HandleRevisedGeneratedImages;
use Wss\FlarumLineup\Provider\LineupServiceProvider;

return [
    (new Extend\Routes('api'))
        ->post(
            '/wss-lineup/images',
            'wss-lineup.images.create',
            CreateLineupImageController::class
        )
        ->get(
            '/wss-lineup/teams',
            'wss-lineup.teams.index',
            ListTeamsController::class
        )
        ->get(
            '/wss-lineup/players',
            'wss-lineup.players.index',
            ListPlayersController::class
        )
        ->post(
            '/wss-lineup/api-key/test',
            'wss-lineup.api-key.test',
            TestApiKeyController::class
        )
        ->get(
            '/wss-lineup/api-key',
            'wss-lineup.api-key.show',
            ShowApiKeyStatusController::class
        )
        ->post(
            '/wss-lineup/squads/sync',
            'wss-lineup.squads.sync',
            SynchronizeSquadsController::class
        )
        ->post(
            '/wss-lineup/teams/sync',
            'wss-lineup.teams.sync',
            SynchronizeTeamsController::class
        )
        ->post(
            '/wss-lineup/api-key',
            'wss-lineup.api-key.store',
            StoreApiKeyController::class
        )
        ->delete(
            '/wss-lineup/api-key',
            'wss-lineup.api-key.delete',
            DeleteApiKeyController::class
        ),
    (new Extend\Console())
        ->command(SynchronizeTeamsCommand::class)
        ->command(SynchronizeSquadsCommand::class)
        ->schedule(
            SynchronizeTeamsCommand::class,
            function (Event $event): void {
                $event
                    ->dailyAt('23:55')
                    ->timezone('Europe/Vienna')
                    ->withoutOverlapping(10);
            }
        )
        ->schedule(
            SynchronizeSquadsCommand::class,
            function (Event $event): void {
                $event
                    ->cron('0 0,8,16 * * *')
                    ->timezone('Europe/Vienna')
                    ->withoutOverlapping(30);
            }
        ),
    (new Extend\Event())
        ->listen(
            Posted::class,
            HandlePostedGeneratedImages::class
        )
        ->listen(
            Revised::class,
            HandleRevisedGeneratedImages::class
        ),
    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute(
            'canCreateLineup',
            function (ForumSerializer $serializer): bool {
                return $serializer
                    ->getActor()
                    ->can('wss-lineup.createLineup');
            }
        ),
    (new Extend\ServiceProvider())
        ->register(LineupServiceProvider::class),
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),
];
