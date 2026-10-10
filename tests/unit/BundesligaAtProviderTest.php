<?php

declare(strict_types=1);

namespace Wss\FlarumLineup\Tests\Unit;

use DateTimeImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wss\FlarumLineup\DataProvider\BundesligaAtParser;
use Wss\FlarumLineup\DataProvider\BundesligaAtProvider;
use Wss\FlarumLineup\DataProvider\BundesligaAtSource;

final class BundesligaAtProviderTest extends TestCase
{
    public function testItFetchesTeamsAndStoresRoutes(): void
    {
        $source = $this->createMock(
            BundesligaAtSource::class
        );

        $source
            ->expects($this->once())
            ->method('fetchClubsHtml')
            ->willReturn(
                $this->clubsHtml()
            );

        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->expects($this->once())
            ->method('set')
            ->with(
                BundesligaAtProvider::ROUTE_CACHE_SETTING,
                $this->callback(
                    static function (string $value): bool {
                        $decoded = json_decode(
                            $value,
                            true
                        );

                        return is_array($decoded)
                            && (
                                $decoded['6428']
                                ?? null
                            ) === (
                                '/de/team/'
                                .'fc-red-bull-salzburg/'
                                .'6428/kader'
                            );
                    }
                )
            );

        $provider = new BundesligaAtProvider(
            $source,
            new BundesligaAtParser(
                new DateTimeImmutable('2026-10-10')
            ),
            $settings
        );

        $teams = $provider->fetchTeams();

        $this->assertSame(
            BundesligaAtProvider::PROVIDER_KEY,
            $provider->providerKey()
        );

        $this->assertCount(1, $teams);

        $this->assertSame(
            '6428',
            $teams[0]['providerTeamId']
        );

        $this->assertSame(
            'FC Red Bull Salzburg',
            $teams[0]['name']
        );

        $this->assertArrayNotHasKey(
            'squadPath',
            $teams[0]
        );
    }

    public function testItFetchesSquadFromStoredRoute(): void
    {
        $source = $this->createMock(
            BundesligaAtSource::class
        );

        $source
            ->expects($this->never())
            ->method('fetchClubsHtml');

        $source
            ->expects($this->once())
            ->method('fetchSquadHtml')
            ->with(
                '/de/team/fc-red-bull-salzburg/6428/kader'
            )
            ->willReturn(
                $this->squadHtml()
            );

        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $settings
            ->method('get')
            ->with(
                BundesligaAtProvider::ROUTE_CACHE_SETTING
            )
            ->willReturn(
                json_encode(
                    [
                        '6428' => (
                            '/de/team/'
                            .'fc-red-bull-salzburg/'
                            .'6428/kader'
                        ),
                    ],
                    JSON_THROW_ON_ERROR
                )
            );

        $provider = new BundesligaAtProvider(
            $source,
            new BundesligaAtParser(
                new DateTimeImmutable('2026-10-10')
            ),
            $settings
        );

        $players = $provider->fetchSquad(
            '6428'
        );

        $this->assertCount(1, $players);

        $this->assertSame(
            '65754',
            $players[0]['providerPlayerId']
        );
    }

    public function testItRejectsInvalidTeamId(): void
    {
        $source = $this->createMock(
            BundesligaAtSource::class
        );

        $settings = $this->createMock(
            SettingsRepositoryInterface::class
        );

        $provider = new BundesligaAtProvider(
            $source,
            new BundesligaAtParser(),
            $settings
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $provider->fetchSquad(
            '../6428'
        );
    }

    private function clubsHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body>
<a href="/team/fc-red-bull-salzburg/6428">
<img
    alt="FC Red Bull Salzburg"
    src="/_next/image?url=https%3A%2F%2Fbundesliga-craftcms-production-bucket.s3.eu-central-1.amazonaws.com%2Fcraft-cms-oefbl%2FKlubs%2FIcons%2F6428.png&amp;w=128&amp;q=75"
>
</a>
<a href="/team/fc-red-bull-salzburg/6428">RBS</a>
</body>
</html>
HTML;
    }

    private function squadHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head><meta charset="utf-8"></head>
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
<td><a href="/spieler/christian-früchtl/65754">Christian Früchtl</a></td>
<td>Deutschland</td>
<td>28.01.2000</td>
</tr>
</table>
</body>
</html>
HTML;
    }
}
