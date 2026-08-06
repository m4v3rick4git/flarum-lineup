<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

interface RemoteImageCacheService
{
    public function cacheTeamLogo(
        int $apiTeamId,
        ?string $remoteUrl
    ): ?string;

    public function cachePlayerPhoto(
        int $apiPlayerId,
        ?string $remoteUrl
    ): ?string;
}
