<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Listener;

use Flarum\Post\Event\Deleted;
use Wss\FlarumLineup\Image\GeneratedImageClaimService;

final class HandleDeletedGeneratedImages
{
    private GeneratedImageClaimService $claimService;

    public function __construct(
        GeneratedImageClaimService $claimService
    ) {
        $this->claimService = $claimService;
    }

    public function handle(Deleted $event): void
    {
        $this->claimService->releasePost(
            (int) $event->post->id
        );
    }
}
