<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;

interface CachedImageAccess
{
    public function teamLogoUrl(
        string $provider,
        string $providerTeamId
    ): ?string;

    public function playerPhotoUrl(
        string $provider,
        string $providerPlayerId
    ): ?string;

    public function load(
        ?string $url
    ): ?GdImage;
}
