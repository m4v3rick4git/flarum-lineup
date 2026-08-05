<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\Image\GeneratedImageClaimService;
use Wss\FlarumLineup\Listener\HandlePostedGeneratedImages;
use Wss\FlarumLineup\Listener\HandleRevisedGeneratedImages;

final class GeneratedImageListenersTest extends TestCase
{
    public function testPostedEventSynchronizesThePost(): void
    {
        $post = new CommentPost();

        $claimService = $this->createMock(
            GeneratedImageClaimService::class
        );

        $claimService
            ->expects($this->once())
            ->method('synchronizePost')
            ->with($post);

        $listener = new HandlePostedGeneratedImages(
            $claimService
        );

        $listener->handle(
            new Posted($post)
        );
    }

    public function testRevisedEventSynchronizesThePost(): void
    {
        $post = new CommentPost();

        $claimService = $this->createMock(
            GeneratedImageClaimService::class
        );

        $claimService
            ->expects($this->once())
            ->method('synchronizePost')
            ->with($post);

        $listener = new HandleRevisedGeneratedImages(
            $claimService
        );

        $listener->handle(
            new Revised(
                $post,
                $this->createMock(
                    \Flarum\User\User::class
                ),
                'Old content'
            )
        );
    }
}
