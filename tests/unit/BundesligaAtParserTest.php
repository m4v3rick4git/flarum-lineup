<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\DataProvider\BundesligaAtParser;

final class BundesligaAtParserTest extends TestCase
{
    public function testItParsesTeams(): void
    {
        $parser = new BundesligaAtParser(
            new DateTimeImmutable('2026-10-10')
        );

        $teams = $parser->parseTeams(
            $this->clubsHtml()
        );

        $this->assertCount(2, $teams);

        $this->assertSame(
            [
                'providerTeamId' => '6428',
                'name' => 'FC Red Bull Salzburg',
                'code' => 'RBS',
                'country' => 'Austria',
                'founded' => null,
                'isNational' => false,
                'logoUrl' => (
                    'https://bundesliga-craftcms-production-bucket.'
                    .'s3.eu-central-1.amazonaws.com/'
                    .'craft-cms-oefbl/Klubs/Icons/6428.png'
                ),
                'squadPath' => (
                    '/de/team/fc-red-bull-salzburg/6428/kader'
                ),
            ],
            $teams[0]
        );

        $this->assertSame(
            '6465',
            $teams[1]['providerTeamId']
        );
        $this->assertSame(
            'LASK',
            $teams[1]['name']
        );
        $this->assertSame(
            'ASK',
            $teams[1]['code']
        );
    }

    public function testItParsesSquadPlayers(): void
    {
        $parser = new BundesligaAtParser(
            new DateTimeImmutable('2026-10-10')
        );

        $players = $parser->parseSquad(
            $this->squadHtml()
        );

        $this->assertCount(2, $players);

        $this->assertSame(
            [
                'providerPlayerId' => '65754',
                'name' => 'Christian Früchtl',
                'age' => 26,
                'shirtNumber' => 1,
                'position' => 'Goalkeeper',
                'photoUrl' => (
                    'https://bundesliga-production-bucket.'
                    .'s3.eu-central-1.amazonaws.com/'
                    .'images/player-team-assignment/'
                    .'e4199221-a6d2-4c0a-a4f3-10965a1048fb.png'
                ),
            ],
            $players[0]
        );

        $this->assertSame(
            '48061',
            $players[1]['providerPlayerId']
        );
        $this->assertSame(
            'Stefan Lainer',
            $players[1]['name']
        );
        $this->assertSame(
            34,
            $players[1]['age']
        );
        $this->assertSame(
            22,
            $players[1]['shirtNumber']
        );
        $this->assertSame(
            'Defender',
            $players[1]['position']
        );
    }

    public function testItTreatsOfficialPortraitPlaceholderAsMissingPhoto(): void
    {
        $parser = new BundesligaAtParser(
            new DateTimeImmutable('2026-10-10')
        );

        $html = <<<'HTML'
<!doctype html>
<html>
<body>
<h2>Tor</h2>
<table>
    <tr>
        <td>99</td>
        <td>
            <a href="/spieler/elias-mueller/68000">
                <img
                    alt="Elias Müller"
                    src="https://www.bundesliga.at/_next/image?url=%2Ficons%2Fportrait_placeholder.png&amp;w=128&amp;q=75"
                >
            </a>
        </td>
        <td>
            <a href="/spieler/elias-mueller/68000">
                Elias Müller
            </a>
        </td>
        <td>Österreich</td>
        <td>31.08.2005</td>
    </tr>
</table>
</body>
</html>
HTML;

        $players = $parser->parseSquad($html);

        $this->assertCount(1, $players);
        $this->assertSame(
            '68000',
            $players[0]['providerPlayerId']
        );
        $this->assertNull(
            $players[0]['photoUrl']
        );
    }

    public function testItRejectsUnknownPositionSection(): void
    {
        $parser = new BundesligaAtParser(
            new DateTimeImmutable('2026-10-10')
        );

        $this->expectException(
            \RuntimeException::class
        );

        $parser->parseSquad(
            str_replace(
                '<h2>Tor</h2>',
                '<h2>Unknown</h2>',
                $this->squadHtml()
            )
        );
    }

    private function clubsHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
</head>
<body>
<a href="/team/fc-red-bull-salzburg/6428">
    <img
        alt="FC Red Bull Salzburg"
        src="/_next/image?url=https%3A%2F%2Fbundesliga-craftcms-production-bucket.s3.eu-central-1.amazonaws.com%2Fcraft-cms-oefbl%2FKlubs%2FIcons%2F6428.png%3Ftimestamp%3D1780904565624&amp;w=128&amp;q=75"
    >
</a>
<a href="/team/fc-red-bull-salzburg/6428">RBS</a>
<a href="/team/fc-red-bull-salzburg/6428">
    FC Red Bull Salzburg
    RBS
</a>

<a href="/team/lask/6465">
    <img
        alt="LASK"
        src="/_next/image?url=https%3A%2F%2Fbundesliga-craftcms-production-bucket.s3.eu-central-1.amazonaws.com%2Fcraft-cms-oefbl%2FKlubs%2FIcons%2F6465.png&amp;w=128&amp;q=75"
    >
</a>
<a href="/team/lask/6465">ASK</a>
</body>
</html>
HTML;
    }

    private function squadHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
</head>
<body>
<h2>Tor</h2>
<table>
    <tr>
        <td>1</td>
        <td>
            <a href="/spieler/christian-früchtl/65754">
                <img
                    alt="Christian Früchtl"
                    src="/_next/image?url=https%3A%2F%2Fbundesliga-production-bucket.s3.eu-central-1.amazonaws.com%2Fimages%2Fplayer-team-assignment%2Fe4199221-a6d2-4c0a-a4f3-10965a1048fb.png&amp;w=256&amp;q=75"
                >
            </a>
        </td>
        <td>
            <a href="/spieler/christian-früchtl/65754">
                Christian Früchtl
            </a>
        </td>
        <td>Deutschland</td>
        <td>28.01.2000</td>
    </tr>
</table>

<h2>Abwehr</h2>
<table>
    <tr>
        <td>22</td>
        <td>
            <a href="/spieler/stefan-lainer/48061">
                <img
                    alt="Stefan   Lainer"
                    src="/_next/image?url=https%3A%2F%2Fbundesliga-production-bucket.s3.eu-central-1.amazonaws.com%2Fimages%2Fplayer-team-assignment%2Fb60dc2b8-1e96-41d3-99f6-f38dc6b8be10.webp&amp;w=256&amp;q=75"
                >
            </a>
        </td>
        <td>
            <a href="/spieler/stefan-lainer/48061">
                Stefan Lainer
            </a>
        </td>
        <td>Österreich</td>
        <td>27.08.1992</td>
    </tr>
</table>
</body>
</html>
HTML;
    }
}
