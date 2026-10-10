<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        $connection = $schema->getConnection();

        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table
                    ->string('provider', 50)
                    ->default('api-football')
                    ->after('api_team_id');

                $table
                    ->string('provider_team_id', 100)
                    ->nullable()
                    ->after('provider');
            }
        );

        $teams = $connection
            ->table('wss_lineup_teams')
            ->select([
                'id',
                'api_team_id',
            ])
            ->get();

        foreach ($teams as $team) {
            $connection
                ->table('wss_lineup_teams')
                ->where('id', $team->id)
                ->update([
                    'provider' => 'api-football',
                    'provider_team_id' => (
                        (string) $team->api_team_id
                    ),
                ]);
        }

        $missingProviderIds = $connection
            ->table('wss_lineup_teams')
            ->whereNull('provider_team_id')
            ->count();

        if ($missingProviderIds > 0) {
            throw new RuntimeException(
                'Team provider IDs could not be backfilled.'
            );
        }

        $tableName = $connection
            ->getQueryGrammar()
            ->wrapTable('wss_lineup_teams');

        $connection->statement(
            sprintf(
                'ALTER TABLE %s '
                .'MODIFY `provider_team_id` VARCHAR(100) NOT NULL',
                $tableName
            )
        );

        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'provider',
                        'provider_team_id',
                    ],
                    'wss_lineup_teams_provider_team_unique'
                );
            }
        );
    },

    'down' => function (Builder $schema): void {
        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'wss_lineup_teams_provider_team_unique'
                );

                $table->dropColumn([
                    'provider',
                    'provider_team_id',
                ]);
            }
        );
    },
];
