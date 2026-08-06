<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;

interface RemoteImageSource
{
    public function fetch(
        ?string $url
    ): ?GdImage;
}
