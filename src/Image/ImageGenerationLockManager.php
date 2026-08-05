<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ImageGenerationLockManager
{
    private const ACQUIRE_SQL = <<<'SQL'
SELECT GET_LOCK(
    CONCAT(
        'wss-lineup.image-generation.',
        LEFT(
            SHA2(COALESCE(DATABASE(), ''), 256),
            24
        )
    ),
    0
) AS acquired
SQL;

    private const RELEASE_SQL = <<<'SQL'
SELECT RELEASE_LOCK(
    CONCAT(
        'wss-lineup.image-generation.',
        LEFT(
            SHA2(COALESCE(DATABASE(), ''), 256),
            24
        )
    )
) AS released
SQL;

    private ConnectionInterface $database;

    private ?LoggerInterface $logger;

    private int $depth = 0;

    public function __construct(
        ConnectionInterface $database,
        ?LoggerInterface $logger = null
    ) {
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $callback
     *
     * @return TResult
     */
    public function run(callable $callback)
    {
        if ($this->depth > 0) {
            return $callback();
        }

        $result = $this->database->selectOne(
            self::ACQUIRE_SQL,
            [],
            false
        );

        if ($this->resultValue($result, 'acquired') !== 1) {
            throw new ImageGenerationThrottledException(
                'busy',
                5,
                'Another lineup image is currently being generated.'
            );
        }

        $this->depth = 1;

        try {
            return $callback();
        } finally {
            $this->depth = 0;
            $this->release();
        }
    }

    private function release(): void
    {
        try {
            $result = $this->database->selectOne(
                self::RELEASE_SQL,
                [],
                false
            );

            if (
                $this->resultValue($result, 'released') !== 1
                && $this->logger !== null
            ) {
                $this->logger->warning(
                    'WSS Lineup image-generation lock was not released.'
                );
            }
        } catch (Throwable $exception) {
            if ($this->logger !== null) {
                $this->logger->warning(
                    'WSS Lineup image-generation lock release failed.',
                    [
                        'exceptionClass' => $exception::class,
                    ]
                );
            }
        }
    }

    /**
     * @param mixed $result
     */
    private function resultValue(
        $result,
        string $property
    ): ?int {
        if (
            is_object($result)
            && isset($result->{$property})
        ) {
            return (int) $result->{$property};
        }

        if (
            is_array($result)
            && array_key_exists($property, $result)
        ) {
            return (int) $result[$property];
        }

        return null;
    }
}
