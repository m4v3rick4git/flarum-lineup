<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use RuntimeException;

final class ImageGenerationThrottledException extends RuntimeException
{
    private string $reason;

    private int $retryAfterSeconds;

    public function __construct(
        string $reason,
        int $retryAfterSeconds,
        string $message
    ) {
        parent::__construct($message);

        $this->reason = $reason;
        $this->retryAfterSeconds = max(
            1,
            $retryAfterSeconds
        );
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
