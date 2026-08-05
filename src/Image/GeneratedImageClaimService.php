<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Flarum\Post\CommentPost;

interface GeneratedImageClaimService
{
    public function synchronizePost(
        CommentPost $post
    ): void;
}
