<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Listener;

use Flarum\Post\Event\Revised;
use Wss\FlarumLineup\Image\GeneratedImageClaimService;

final class HandleRevisedGeneratedImages
{
    private GeneratedImageClaimService $claimService;

    public function __construct(
        GeneratedImageClaimService $claimService
    ) {
        $this->claimService = $claimService;
    }

    public function handle(Revised $event): void
    {
        $this->claimService->synchronizePost(
            $event->post
        );
    }
}
