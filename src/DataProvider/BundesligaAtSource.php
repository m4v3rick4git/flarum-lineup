<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

interface BundesligaAtSource
{
    public function fetchClubsHtml(): string;

    public function fetchSquadHtml(
        string $squadPath
    ): string;
}
