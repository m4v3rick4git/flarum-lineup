<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Lineup;

final class DisplayName
{
    public static function fromFullName(string $fullName): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $fullName) ?? $fullName);
        if ($normalized === '') {
            return '';
        }
        $parts = explode(' ', $normalized);
        // "Gourna-Douath" stays one token; compound names with spaces
        // intentionally follow the agreed pragmatic "last word" rule.
        return (string) end($parts);
    }
}
