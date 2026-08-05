<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Illuminate\Database\ConnectionInterface;
use Wss\FlarumLineup\Model\GeneratedImage;

final class GeneratedImageClaimManager implements
    GeneratedImageClaimService
{
    private GeneratedImageReferenceExtractor $extractor;

    private ConnectionInterface $database;

    public function __construct(
        GeneratedImageReferenceExtractor $extractor,
        ConnectionInterface $database
    ) {
        $this->extractor = $extractor;
        $this->database = $database;
    }

    public function synchronizePost(
        CommentPost $post
    ): void {
        $postId = (int) $post->id;
        $actorId = (int) $post->user_id;

        if ($postId <= 0 || $actorId <= 0) {
            return;
        }

        $filenames = $this->extractor->extract(
            (string) $post->content
        );

        $this->database->transaction(
            function () use (
                $postId,
                $actorId,
                $filenames
            ): void {
                $this->releaseRemovedImages(
                    $postId,
                    $filenames
                );

                $this->claimReferencedImages(
                    $postId,
                    $actorId,
                    $filenames
                );
            }
        );
    }

    /**
     * @param array<int, string> $filenames
     */
    private function releaseRemovedImages(
        int $postId,
        array $filenames
    ): void {
        $query = GeneratedImage::query()
            ->where('post_id', $postId)
            ->where(
                'status',
                GeneratedImage::STATUS_CLAIMED
            );

        if ($filenames !== []) {
            $query->whereNotIn(
                'filename',
                $filenames
            );
        }

        $now = Carbon::now();

        $query->update([
            'post_id' => null,
            'status' => GeneratedImage::STATUS_READY,
            'claimed_at' => null,
            'expires_at' => $now->copy()->addDay(),
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<int, string> $filenames
     */
    private function claimReferencedImages(
        int $postId,
        int $actorId,
        array $filenames
    ): void {
        if ($filenames === []) {
            return;
        }

        $now = Carbon::now();

        GeneratedImage::query()
            ->where('actor_id', $actorId)
            ->whereIn('filename', $filenames)
            ->whereIn(
                'status',
                [
                    GeneratedImage::STATUS_READY,
                    GeneratedImage::STATUS_CLAIMED,
                ]
            )
            ->where(
                function ($query) use ($postId): void {
                    $query
                        ->whereNull('post_id')
                        ->orWhere('post_id', $postId);
                }
            )
            ->update([
                'post_id' => $postId,
                'status' => GeneratedImage::STATUS_CLAIMED,
                'claimed_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
