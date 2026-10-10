<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'wss_lineup_teams_api_team_id_unique'
                );
            }
        );

        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table->dropColumn('api_team_id');
            }
        );
    },

    'down' => function (Builder $schema): void {
        $connection = $schema->getConnection();

        $teams = $connection
            ->table('wss_lineup_teams')
            ->select([
                'id',
                'provider',
                'provider_team_id',
            ])
            ->get();

        $apiTeamIds = [];

        foreach ($teams as $team) {
            if ($team->provider !== 'api-football') {
                throw new RuntimeException(
                    'The migration cannot be rolled back while non-API-Football teams exist.'
                );
            }

            $providerTeamId = (string) $team->provider_team_id;
            $apiTeamId = filter_var(
                $providerTeamId,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($apiTeamId === false || $apiTeamId > 4294967295) {
                throw new RuntimeException(
                    'The migration cannot be rolled back because a provider team ID cannot be converted to an unsigned integer.'
                );
            }

            $apiTeamIds[(int) $team->id] = $apiTeamId;
        }

        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table
                    ->unsignedInteger('api_team_id')
                    ->nullable()
                    ->after('id');
            }
        );

        foreach ($apiTeamIds as $teamId => $apiTeamId) {
            $connection
                ->table('wss_lineup_teams')
                ->where('id', $teamId)
                ->update([
                    'api_team_id' => $apiTeamId,
                ]);
        }

        $tableName = $connection
            ->getQueryGrammar()
            ->wrapTable('wss_lineup_teams');

        $connection->statement(
            sprintf(
                'ALTER TABLE %s MODIFY `api_team_id` INT UNSIGNED NOT NULL',
                $tableName
            )
        );

        $schema->table(
            'wss_lineup_teams',
            function (Blueprint $table): void {
                $table->unique('api_team_id');
            }
        );
    },
];