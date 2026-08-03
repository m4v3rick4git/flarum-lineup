<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'wss_lineup_teams',
    function (Blueprint $table): void {
        $table->increments('id');

        $table
            ->unsignedInteger('api_team_id')
            ->unique();

        $table->string('name', 150);

        $table
            ->string('code', 20)
            ->nullable();

        $table
            ->string('country', 100)
            ->nullable();

        $table
            ->unsignedSmallInteger('founded')
            ->nullable();

        $table
            ->boolean('is_national')
            ->default(false);

        $table
            ->string('logo_url', 500)
            ->nullable();

        $table
            ->boolean('is_active')
            ->default(true);

        $table
            ->dateTime('last_synced_at')
            ->nullable();

        $table->timestamps();

        $table->index('name');
        $table->index('is_active');
    }
);
