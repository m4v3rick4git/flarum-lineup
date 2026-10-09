<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

interface DataProviderInterface
{
    public function providerKey(): string;

    /**
     * @return array<int, array{
     *     providerTeamId: string,
     *     name: string,
     *     code: string|null,
     *     country: string|null,
     *     founded: int|null,
     *     isNational: bool,
     *     logoUrl: string|null
     * }>
     */
    public function fetchTeams(): array;

    /**
     * @return array<int, array{
     *     providerPlayerId: string,
     *     name: string,
     *     age: int|null,
     *     shirtNumber: int|null,
     *     position: string|null,
     *     photoUrl: string|null
     * }>
     */
    public function fetchSquad(string $providerTeamId): array;
}
