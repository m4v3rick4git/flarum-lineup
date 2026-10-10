<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

interface RemoteImageCacheService
{
    public function cacheTeamLogo(
        string $provider,
        string $providerTeamId,
        ?string $remoteUrl,
        bool $forceRefresh = false
    ): ?string;

    public function cachePlayerPhoto(
        string $provider,
        string $providerPlayerId,
        ?string $remoteUrl,
        bool $forceRefresh = false
    ): ?string;
}
