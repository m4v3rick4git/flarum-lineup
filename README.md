# Flarum Lineup

A Flarum extension for creating football lineups and inserting them as generated images into posts.

The extension supports multiple data providers for Austrian Bundesliga team and squad data.

## Data providers

### API-Football

API-Football remains the default provider. It requires:

- API key
- Austrian Bundesliga league ID
- season start year

### Bundesliga.at

The Bundesliga.at provider reads the current club and squad pages from:

```text
https://www.bundesliga.at
```

No API key is required.

Provider-specific team and player identifiers are stored as strings and remain isolated from identifiers belonging to other providers.

## Features

- Selectable data provider in the Flarum administration
- Synchronize current teams and squads
- Cache team logos and player photos locally per provider
- Select eleven players from one team
- Choose from several common formations
- Generate a PNG lineup image
- Insert the generated image directly into the Flarum composer
- Control access through a dedicated Flarum permission
- German and English translations

## Requirements

- Flarum 1.8 within the 1.x release series
- PHP 8.1 or newer
- PHP GD extension with FreeType support
- PHP DOM extension

The extension bundles the DejaVu Sans fonts used by the lineup renderer.

The PHP process must be able to write to Flarum's `public/assets` directory.

## Installation

Install the extension with Composer from the Flarum root directory:

```sh
composer require m4v3rick4git/flarum-lineup:^0.1
php flarum migrate
php flarum cache:clear
```

Enable **Flarum Lineup** in the Flarum administration panel.

## Configuration

Open the extension settings in the Flarum administration panel and:

1. Select the data source.
2. Save the settings.
3. For API-Football, configure the API key, league ID and season.
4. Synchronize the teams.
5. Synchronize the squads.
6. Grant the **Create football lineups** permission to the desired user groups.

The selected provider is also used by the scheduled synchronization commands.

## Scheduled synchronization

Automatic team and squad synchronization can be enabled independently in the Flarum administration.

Each synchronization has its own schedule and supports:

- daily: time
- weekly: weekday and time
- monthly: day 1 through 28 and time

Times are interpreted in the `Europe/Vienna` timezone. Automatic synchronization is disabled by default. Enabling or changing a schedule arms the next future slot instead of immediately running a missed slot.

The manual team and squad synchronization buttons remain available at all times and do not change the automatic schedule.

The generated-image cleanup runs daily at 03:30.

## Generated images and cached assets

Generated lineup images are stored in:

```text
public/assets/wss-lineup/
```

Team logos and player photos are downloaded during synchronization, validated, converted to PNG and cached locally by provider:

```text
public/assets/wss-lineup/cache/teams/api-football/
public/assets/wss-lineup/cache/teams/bundesliga-at/

public/assets/wss-lineup/cache/players/api-football/
public/assets/wss-lineup/cache/players/bundesliga-at/
```

The provider identifier is part of the cache path. The provider's original team or player identifier is SHA-256 hashed for the cache filename.

Valid cached images are reused and refreshed after seven days. If a refresh fails, an existing valid cached image remains available. Bundesliga.at player portraits are normalized to a square, top-biased crop before being stored so faces remain visible in circular lineup thumbnails.

Generated lineup images are separate from the provider image cache.

## Security

The API-Football key is stored in the Flarum settings table and is used only server-side. It is not returned to the administration frontend after it has been saved. Protect access to the database and database backups accordingly.

Remote images are restricted to explicit provider-specific HTTPS hosts and paths before they are downloaded and decoded.

Bundesliga.at HTML is fetched only from the fixed official base URL. Squad paths are validated before requests are made.

Generated images, team logos and player photos are stored in the forum's public asset directory. Their URLs must not be treated as private or protected resources.

Do not commit API keys to the repository.

## Updating

```sh
composer update m4v3rick4git/flarum-lineup
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/m4v3rick4git/flarum-lineup)
- [GitHub](https://github.com/m4v3rick4git/flarum-lineup)

## License

Released under the MIT License.
