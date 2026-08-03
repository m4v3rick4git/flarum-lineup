<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'wss_lineup_players',
    function (Blueprint $table): void {
        $table->increments('id');

        $table->unsignedInteger('team_id');

        $table
            ->string('provider', 50)
            ->default('api-football');

        $table->string('provider_player_id', 100);

        $table->string('name', 150);

        $table
            ->unsignedTinyInteger('age')
            ->nullable();

        $table
            ->unsignedSmallInteger('shirt_number')
            ->nullable();

        $table
            ->string('position', 50)
            ->nullable();

        $table
            ->string('photo_url', 500)
            ->nullable();

        $table
            ->boolean('is_active')
            ->default(true);

        $table
            ->dateTime('last_synced_at')
            ->nullable();

        $table->timestamps();

        $table
            ->foreign('team_id')
            ->references('id')
            ->on('wss_lineup_teams')
            ->onDelete('cascade');

        $table->unique(
            ['provider', 'provider_player_id'],
            'wss_lineup_players_provider_player_unique'
        );

        $table->index(
            ['team_id', 'is_active'],
            'wss_lineup_players_team_active_index'
        );

        $table->index('name');
        $table->index('position');
    }
);
