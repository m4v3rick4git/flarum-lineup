<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Image;

final class GeneratedImageReferenceExtractor
{
    private const PATTERN = <<<'REGEX'
~(?:https?://[^\s<>()"']+)?/assets/wss-lineup/([a-f0-9]{40}\.png)(?:[?#][^\s<>()"']*)?~i
REGEX;

    /**
     * @return array<int, string>
     */
    public function extract(string $content): array
    {
        $matchCount = preg_match_all(
            self::PATTERN,
            $content,
            $matches
        );

        if (
            $matchCount === false
            || $matchCount === 0
            || !isset($matches[1])
            || !is_array($matches[1])
        ) {
            return [];
        }

        $filenames = [];

        foreach ($matches[1] as $filename) {
            if (!is_string($filename)) {
                continue;
            }

            $filenames[] = strtolower($filename);
        }

        return array_values(
            array_unique($filenames)
        );
    }
}
