<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Api;

use RuntimeException;

final class ApiFootballRequestException extends RuntimeException
{
    private int $statusCode;

    private ?int $retryAfterSeconds;

    public function __construct(
        int $statusCode,
        ?int $retryAfterSeconds = null
    ) {
        parent::__construct(
            sprintf(
                'API-Football returned HTTP status %d.',
                $statusCode
            )
        );

        $this->statusCode = $statusCode;
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
