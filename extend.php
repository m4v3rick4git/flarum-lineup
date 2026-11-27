<?php

declare(strict_types=1);

namespace Wss\FlarumLineup;

use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Illuminate\Console\Scheduling\Event;
use Flarum\Api\Context;
use Wss\FlarumLineup\Api\Controller\CreateLineupImageController;
use Wss\FlarumLineup\Api\Controller\ListPlayersController;
use Wss\FlarumLineup\Api\Controller\ListTeamsController;
use Wss\FlarumLineup\Api\Controller\SynchronizeSquadsController;
use Wss\FlarumLineup\Api\Controller\SynchronizeTeamsController;
use Wss\FlarumLineup\Console\AutomaticSynchronizeCommand;
use Wss\FlarumLineup\Console\CleanupGeneratedImagesCommand;
use Wss\FlarumLineup\Console\RefreshBundesligaPlayerImagesCommand;
use Wss\FlarumLineup\Console\SynchronizeSquadsCommand;
use Wss\FlarumLineup\Console\SynchronizeTeamsCommand;
use Wss\FlarumLineup\Listener\HandleDeletedGeneratedImages;
use Wss\FlarumLineup\Listener\HandlePostedGeneratedImages;
use Wss\FlarumLineup\Listener\HandleRevisedGeneratedImages;
use Wss\FlarumLineup\Provider\LineupServiceProvider;

return [
    (new Extend\Routes('api'))
        ->post('/wss-lineup/images', 'wss-lineup.images.create', CreateLineupImageController::class)
        ->get('/wss-lineup/teams', 'wss-lineup.teams.index', ListTeamsController::class)
        ->get('/wss-lineup/players', 'wss-lineup.players.index', ListPlayersController::class)
        ->post('/wss-lineup/teams/sync', 'wss-lineup.teams.sync', SynchronizeTeamsController::class)
        ->post('/wss-lineup/squads/sync', 'wss-lineup.squads.sync', SynchronizeSquadsController::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canCreateLineup')
                ->get(fn (object $forum, Context $context): bool =>
                    $context->getActor()->can('wss-lineup.createLineup')),
        ]),

    (new Extend\ServiceProvider())->register(LineupServiceProvider::class),

    (new Extend\Console())
        ->command(AutomaticSynchronizeCommand::class)
        ->command(CleanupGeneratedImagesCommand::class)
        ->command(RefreshBundesligaPlayerImagesCommand::class)
        ->command(SynchronizeTeamsCommand::class)
        ->command(SynchronizeSquadsCommand::class)
        ->schedule(CleanupGeneratedImagesCommand::class,
            fn (Event $event) => $event->dailyAt('03:30')->timezone('Europe/Vienna')->withoutOverlapping(15))
        ->schedule(AutomaticSynchronizeCommand::class,
            fn (Event $event) => $event->everyMinute()->timezone('Europe/Vienna')->withoutOverlapping(30)),

    (new Extend\Event())
        ->listen(Deleted::class, HandleDeletedGeneratedImages::class)
        ->listen(Posted::class, HandlePostedGeneratedImages::class)
        ->listen(Revised::class, HandleRevisedGeneratedImages::class),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),
];
