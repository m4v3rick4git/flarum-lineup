<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use GdImage;
use InvalidArgumentException;

final class CompositeRemoteImageSource implements
    RemoteImageSource
{
    /**
     * @var array<int, RemoteImageSource>
     */
    private array $sources;

    /**
     * @param array<int, RemoteImageSource> $sources
     */
    public function __construct(
        array $sources
    ) {
        if ($sources === []) {
            throw new InvalidArgumentException(
                'At least one remote image source is required.'
            );
        }

        foreach ($sources as $source) {
            if (!$source instanceof RemoteImageSource) {
                throw new InvalidArgumentException(
                    'Every remote image source must implement RemoteImageSource.'
                );
            }
        }

        $this->sources = array_values(
            $sources
        );
    }

    public function fetch(
        ?string $url
    ): ?GdImage {
        foreach ($this->sources as $source) {
            $image = $source->fetch($url);

            if ($image instanceof GdImage) {
                return $image;
            }
        }

        return null;
    }
}
