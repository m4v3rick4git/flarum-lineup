<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;

interface CachedImageAccess
{
    public function teamLogoUrl(
        int $apiTeamId
    ): ?string;

    public function playerPhotoUrl(
        int $apiPlayerId
    ): ?string;

    public function load(
        ?string $url
    ): ?GdImage;
}
