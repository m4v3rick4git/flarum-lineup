# Flarum Lineup

A Flarum extension for creating Austrian Bundesliga football lineups
and inserting them as generated PNG images into forum posts.

## Compatibility

| Extension version | Flarum | PHP |
|---|---|---|
| 0.2.x | 2.x | 8.3 or newer |
| 0.1.5 | 1.8.x | 8.1 or newer |

Version 0.2.0 introduces compatibility with Flarum 2.
It has been tested with Flarum 2.0.2 and PHP 8.4.

## Features

- Austrian Bundesliga team and squad data from Bundesliga.at
- No API key required
- Six football formations
- Selection of eleven players from one team
- Full player names in the selection interface
- Short player names on the pitch
- Locally cached team logos and player photos
- Server-side generation of 1200 x 1600 PNG images
- Direct insertion into the Flarum composer
- Dedicated permission for creating football lineups
- Manual and automatic team and squad synchronization
- Automatic cleanup of unused generated images
- German and English translations

## Requirements

- Flarum 2.x
- PHP 8.3 or newer
- PHP GD with FreeType support
- PHP DOM extension
- PHP cURL extension
- DejaVu Sans fonts installed on the server
- Write access to Flarum's public/assets directory

On Debian, the required fonts are available through the
fonts-dejavu-core package.

## Installation

From the Flarum root directory:

~~~sh
composer require m4v3rick4git/flarum-lineup:^0.2
php flarum migrate
php flarum cache:clear
~~~

Enable Flarum Lineup in the administration panel.

For Flarum 1.8 installations, continue using version 0.1.5.

## Configuration

1. Open the extension settings in the administration panel.
2. Synchronize teams.
3. Synchronize squads.
4. Grant the Create football lineups permission to the desired groups.
5. Configure automatic synchronization if required.

Bundesliga.at is the only active data provider in version 0.2.0.
The provider-neutral architecture is retained for future extensions.

## Scheduled synchronization

Team and squad synchronization can be configured independently:

- Daily: time
- Weekly: weekday and time
- Monthly: day 1 through 28 and time

Schedules use the Europe/Vienna timezone.

Automatic synchronization is disabled by default.
Manual synchronization does not change automatic schedules.

Generated-image cleanup is scheduled daily at 03:30.

Flarum's scheduler must be configured for automatic tasks to run.

## Generated images and cached assets

Version 0.2.x uses:

~~~text
public/assets/wss-lineup-2/
~~~

Existing images from version 0.1.x remain at:

~~~text
public/assets/wss-lineup/
~~~

Do not remove the legacy directory when upgrading.
Existing forum posts may still reference its images.

Team logos and player photos are cached locally as PNG files.
Generated lineup images are stored separately from provider caches.

## Upgrading from 0.1.5

Upgrade the extension as part of the Flarum 2 migration.
Back up the database and forum files before upgrading.

Version 0.2.0 retains the original extension ID, five historical
migrations and existing provider-neutral database tables.

- Existing lineup images and post references remain intact.
- Historical API-Football data is retained.
- API-Football is no longer an active provider.
- Legacy API keys and provider data are not automatically deleted.
- Synchronize teams and squads to populate the new image cache.

See [Security and migration](SECURITY-AND-MIGRATION.md)
for additional details.

## Security

- Server-side validation of permissions, teams and player selections
- Per-user image generation rate limits
- Restricted remote image sources and validated downloads
- Locally re-encoded PNG assets
- No automatic deletion of legacy lineup images

Generated images and cached assets are publicly accessible.
Their URLs must not be treated as private resources.

Ensure that the intended use of third-party logos and player photos
complies with applicable rights and attribution requirements.

## Development and testing

The Flarum 2 port has passed 129 PHPUnit tests with 1,006 assertions
under PHP 8.4, alongside local smoke and production upgrade tests.

See [Local development](INSTALL-LOCAL.md) for the isolated
WSL/Docker development environment.

## Links

- [Packagist](https://packagist.org/packages/m4v3rick4git/flarum-lineup)
- [GitHub](https://github.com/m4v3rick4git/flarum-lineup)

## License

MIT License.
