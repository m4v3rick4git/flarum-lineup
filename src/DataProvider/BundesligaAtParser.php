<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\DataProvider;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

final class BundesligaAtParser
{
    private DateTimeImmutable $today;

    public function __construct(
        ?DateTimeImmutable $today = null
    ) {
        $this->today = $today
            ?? new DateTimeImmutable('today');
    }

    /**
     * @return array<int, array{
     *     providerTeamId: string,
     *     name: string,
     *     code: string|null,
     *     country: string|null,
     *     founded: int|null,
     *     isNational: bool,
     *     logoUrl: string|null,
     *     squadPath: string
     * }>
     */
    public function parseTeams(
        string $html
    ): array {
        $xpath = $this->createXPath($html);

        /**
         * @var array<string, array{
         *     providerTeamId: string,
         *     name: string|null,
         *     code: string|null,
         *     logoUrl: string|null,
         *     squadPath: string
         * }>
         */
        $teams = [];

        $anchors = $xpath->query(
            '//a[@href]'
        );

        if ($anchors === false) {
            throw new RuntimeException(
                'The Bundesliga.at club page could not be queried.'
            );
        }

        foreach ($anchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim(
                $anchor->getAttribute('href')
            );

            if (
                preg_match(
                    '#^/(?:de/)?team/([a-z0-9-]+)/([1-9][0-9]*)/?$#D',
                    $href,
                    $match
                ) !== 1
            ) {
                continue;
            }

            $slug = $match[1];
            $providerTeamId = $match[2];
            $squadPath = sprintf(
                '/de/team/%s/%s/kader',
                $slug,
                $providerTeamId
            );

            if (
                isset($teams[$providerTeamId])
                && $teams[$providerTeamId]['squadPath']
                    !== $squadPath
            ) {
                throw new RuntimeException(
                    'Bundesliga.at returned conflicting team routes.'
                );
            }

            if (!isset($teams[$providerTeamId])) {
                $teams[$providerTeamId] = [
                    'providerTeamId' => $providerTeamId,
                    'name' => null,
                    'code' => null,
                    'logoUrl' => null,
                    'squadPath' => $squadPath,
                ];
            }

            $text = $this->normalizeText(
                $anchor->textContent
            );

            if (
                $teams[$providerTeamId]['code'] === null
                && preg_match(
                    '/^[A-Z0-9]{2,5}$/D',
                    $text
                ) === 1
            ) {
                $teams[$providerTeamId]['code'] = $text;
            }

            $images = $anchor->getElementsByTagName(
                'img'
            );

            foreach ($images as $image) {
                if (!$image instanceof DOMElement) {
                    continue;
                }

                $name = $this->normalizeText(
                    $image->getAttribute('alt')
                );

                if ($name !== '') {
                    $teams[$providerTeamId]['name'] =
                        $name;
                }

                $imageUrl = $this->imageUrl($image);

                if ($imageUrl !== null) {
                    $teams[$providerTeamId]['logoUrl'] =
                        $imageUrl;
                }
            }
        }

        $result = [];

        foreach ($teams as $team) {
            if (
                $team['name'] === null
                || trim($team['name']) === ''
            ) {
                continue;
            }

            $result[] = [
                'providerTeamId' => (
                    $team['providerTeamId']
                ),
                'name' => $team['name'],
                'code' => $team['code'],
                'country' => 'Austria',
                'founded' => null,
                'isNational' => false,
                'logoUrl' => $team['logoUrl'],
                'squadPath' => $team['squadPath'],
            ];
        }

        if ($result === []) {
            throw new RuntimeException(
                'Bundesliga.at returned no usable teams.'
            );
        }

        usort(
            $result,
            static fn (
                array $left,
                array $right
            ): int => strcasecmp(
                $left['name'],
                $right['name']
            )
        );

        return $result;
    }

    /**
     * @return array<int, array{
     *     providerPlayerId: string,
     *     name: string,
     *     age: int|null,
     *     shirtNumber: int|null,
     *     position: string|null,
     *     photoUrl: string|null
     * }>
     */
    public function parseSquad(
        string $html
    ): array {
        $xpath = $this->createXPath($html);

        $rows = $xpath->query(
            '//tr[.//a[contains(@href, "/spieler/")]]'
        );

        if ($rows === false) {
            throw new RuntimeException(
                'The Bundesliga.at squad page could not be queried.'
            );
        }

        /**
         * @var array<string, array{
         *     providerPlayerId: string,
         *     name: string,
         *     age: int|null,
         *     shirtNumber: int|null,
         *     position: string|null,
         *     photoUrl: string|null
         * }>
         */
        $players = [];

        foreach ($rows as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }

            $player = $this->parsePlayerRow(
                $xpath,
                $row
            );

            if ($player === null) {
                continue;
            }

            $providerPlayerId =
                $player['providerPlayerId'];

            if (isset($players[$providerPlayerId])) {
                throw new RuntimeException(
                    'Bundesliga.at returned a duplicate player ID.'
                );
            }

            $players[$providerPlayerId] = $player;
        }

        if ($players === []) {
            throw new RuntimeException(
                'Bundesliga.at returned no usable squad players.'
            );
        }

        return array_values($players);
    }

    /**
     * @return array{
     *     providerPlayerId: string,
     *     name: string,
     *     age: int|null,
     *     shirtNumber: int|null,
     *     position: string|null,
     *     photoUrl: string|null
     * }|null
     */
    private function parsePlayerRow(
        DOMXPath $xpath,
        DOMElement $row
    ): ?array {
        $anchors = $xpath->query(
            './/a[@href]',
            $row
        );

        if ($anchors === false) {
            return null;
        }

        $providerPlayerId = null;
        $name = null;
        $photoUrl = null;

        foreach ($anchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim(
                $anchor->getAttribute('href')
            );

            if (
                preg_match(
                    '#^/(?:de/)?spieler/[^/]+/([1-9][0-9]*)/?$#uD',
                    $href,
                    $match
                ) !== 1
            ) {
                continue;
            }

            if (
                $providerPlayerId !== null
                && $providerPlayerId !== $match[1]
            ) {
                throw new RuntimeException(
                    'Bundesliga.at returned conflicting player IDs in one row.'
                );
            }

            $providerPlayerId = $match[1];

            $anchorText = $this->normalizeText(
                $anchor->textContent
            );

            if ($anchorText !== '') {
                $name = $anchorText;
            }

            $image = $anchor
                ->getElementsByTagName('img')
                ->item(0);

            if ($image instanceof DOMElement) {
                $imageName = $this->normalizeText(
                    $image->getAttribute('alt')
                );

                if ($imageName !== '') {
                    $name = $imageName;
                }

                $candidatePhotoUrl =
                    $this->imageUrl($image);

                if ($candidatePhotoUrl !== null) {
                    $photoUrl = $candidatePhotoUrl;
                }
            }
        }

        if (
            $providerPlayerId === null
            || $name === null
            || $name === ''
        ) {
            return null;
        }

        $section = $xpath->query(
            'preceding::h2[1]',
            $row
        );

        $sectionNode = $section !== false
            ? $section->item(0)
            : null;

        if (!$sectionNode instanceof DOMNode) {
            throw new RuntimeException(
                'Bundesliga.at returned a player without a position section.'
            );
        }

        $position = $this->positionFromSection(
            $this->normalizeText(
                $sectionNode->textContent
            )
        );

        $cells = $xpath->query(
            './/td',
            $row
        );

        $shirtNumber = null;
        $age = null;

        if ($cells !== false) {
            $firstCell = $cells->item(0);

            if ($firstCell instanceof DOMNode) {
                $shirtNumber =
                    $this->parseShirtNumber(
                        $this->normalizeText(
                            $firstCell->textContent
                        )
                    );
            }

            foreach ($cells as $cell) {
                if (!$cell instanceof DOMNode) {
                    continue;
                }

                $cellText = $this->normalizeText(
                    $cell->textContent
                );

                $birthDate = $this->parseBirthDate(
                    $cellText
                );

                if ($birthDate !== null) {
                    $age = $this->calculateAge(
                        $birthDate
                    );

                    break;
                }
            }
        }

        return [
            'providerPlayerId' => $providerPlayerId,
            'name' => $name,
            'age' => $age,
            'shirtNumber' => $shirtNumber,
            'position' => $position,
            'photoUrl' => $photoUrl,
        ];
    }

    private function createXPath(
        string $html
    ): DOMXPath {
        if (trim($html) === '') {
            throw new RuntimeException(
                'The Bundesliga.at HTML document is empty.'
            );
        }

        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(
            true
        );

        try {
            $loaded = $document->loadHTML(
                $html,
                LIBXML_NONET
                | LIBXML_NOERROR
                | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new RuntimeException(
                'The Bundesliga.at HTML document could not be parsed.'
            );
        }

        return new DOMXPath($document);
    }

    private function positionFromSection(
        string $section
    ): string {
        return match ($section) {
            'Tor' => 'Goalkeeper',
            'Abwehr' => 'Defender',
            'Mittelfeld' => 'Midfielder',
            'Angriff' => 'Attacker',
            default => throw new RuntimeException(
                sprintf(
                    'Unknown Bundesliga.at squad section "%s".',
                    $section
                )
            ),
        };
    }

    private function parseShirtNumber(
        string $value
    ): ?int {
        if (
            $value === ''
            || !ctype_digit($value)
        ) {
            return null;
        }

        $number = (int) $value;

        return $number <= 65_535
            ? $number
            : null;
    }

    private function parseBirthDate(
        string $value
    ): ?DateTimeImmutable {
        if (
            preg_match(
                '/^[0-9]{2}\.[0-9]{2}\.[0-9]{4}$/D',
                $value
            ) !== 1
        ) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!d.m.Y',
            $value
        );

        if (
            !$date instanceof DateTimeImmutable
            || $date->format('d.m.Y') !== $value
        ) {
            return null;
        }

        return $date;
    }

    private function calculateAge(
        DateTimeImmutable $birthDate
    ): ?int {
        if ($birthDate > $this->today) {
            return null;
        }

        $age = $birthDate
            ->diff($this->today)
            ->y;

        return $age >= 0 && $age <= 255
            ? $age
            : null;
    }

    private function imageUrl(
        DOMElement $image
    ): ?string {
        $candidates = [];

        $source = trim(
            $image->getAttribute('src')
        );

        if ($source !== '') {
            $candidates[] = $source;
        }

        $sourceSet = trim(
            $image->getAttribute('srcset')
        );

        if ($sourceSet !== '') {
            foreach (
                explode(',', $sourceSet)
                as $candidate
            ) {
                $parts = preg_split(
                    '/\s+/',
                    trim($candidate)
                );

                if (
                    is_array($parts)
                    && isset($parts[0])
                    && $parts[0] !== ''
                ) {
                    $candidates[] = $parts[0];
                }
            }
        }

        foreach ($candidates as $candidate) {
            $url = $this->unwrapImageUrl(
                $candidate
            );

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function unwrapImageUrl(
        string $candidate
    ): ?string {
        $candidate = html_entity_decode(
            trim($candidate),
            ENT_QUOTES | ENT_HTML5
        );

        $parts = parse_url($candidate);

        if (!is_array($parts)) {
            return null;
        }

        if (($parts['path'] ?? '') === '/_next/image') {
            parse_str(
                (string) ($parts['query'] ?? ''),
                $query
            );

            if (
                !isset($query['url'])
                || !is_string($query['url'])
                || trim($query['url']) === ''
            ) {
                return null;
            }

            $candidate = $query['url'];

            if ($this->isPlayerPlaceholderPath($candidate)) {
                return null;
            }

            $parts = parse_url($candidate);

            if (!is_array($parts)) {
                return null;
            }
        }

        if ($this->isPlayerPlaceholderPath($candidate)) {
            return null;
        }

        if (
            strtolower(
                (string) ($parts['scheme'] ?? '')
            ) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            return null;
        }

        $host = strtolower(
            (string) $parts['host']
        );

        $path = (string) (
            $parts['path'] ?? ''
        );

        if ($path === '') {
            return null;
        }

        return sprintf(
            'https://%s%s',
            $host,
            $path
        );
    }

    private function isPlayerPlaceholderPath(
        string $candidate
    ): bool {
        $decoded = html_entity_decode(
            trim($candidate),
            ENT_QUOTES | ENT_HTML5
        );

        $decoded = urldecode($decoded);

        $parts = parse_url($decoded);

        if (!is_array($parts)) {
            return false;
        }

        return (
            (string) ($parts['path'] ?? '')
        ) === '/icons/portrait_placeholder.png';
    }

    private function normalizeText(
        string $value
    ): string {
        $value = preg_replace(
            '/\s+/u',
            ' ',
            trim($value)
        );

        return is_string($value)
            ? $value
            : '';
    }
}
