<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Listener;

use Flarum\Post\Event\Posted;
use Wss\FlarumLineup\Image\GeneratedImageClaimService;

final class HandlePostedGeneratedImages
{
    private GeneratedImageClaimService $claimService;

    public function __construct(
        GeneratedImageClaimService $claimService
    ) {
        $this->claimService = $claimService;
    }

    public function handle(Posted $event): void
    {
        $this->claimService->synchronizePost(
            $event->post
        );
    }
}
