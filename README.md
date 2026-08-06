# Flarum Lineup

A Flarum extension for creating football lineups and inserting them as generated images into posts.

The first release is designed for the Austrian Bundesliga and obtains team and squad data from API-Football.

## Features

- Synchronize teams and current squads from API-Football
- Select eleven players from one team
- Choose from several common formations
- Generate a PNG lineup image
- Insert the generated image directly into the Flarum composer
- Control access through a dedicated Flarum permission
- Store the API-Football key encrypted in the Flarum database
- Cache team logos and player photos locally
- German and English translations

## Requirements

- Flarum 1.8 within the 1.x release series
- PHP 8.1 or newer
- PHP GD extension with FreeType support
- PHP Sodium extension
- DejaVu Sans fonts at:

```text
/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf
/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf
```

The PHP process must be able to write to Flarum's `public/assets` directory.

An API-Football account and API key are required for synchronizing team and player data.

## Installation

Install the extension with Composer from the Flarum root directory:

```sh
composer require m4v3rick4git/flarum-lineup:^0.1
php flarum migrate
php flarum cache:clear
```

Enable **Flarum Lineup** in the Flarum administration panel.

## Encryption key

The extension requires the following environment variable:

```text
WSS_LINEUP_ENCRYPTION_KEY
```

Its value must contain exactly 64 hexadecimal characters, representing a random 32-byte key.

Generate a suitable key with PHP:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Add the generated value to the environment used by both the Flarum web process and CLI processes.

Example:

```env
WSS_LINEUP_ENCRYPTION_KEY=replace_with_your_64_character_hexadecimal_key
```

Keep this key secret, stable and backed up. Changing or losing it prevents the extension from decrypting an API key that was already stored.

## Configuration

Open the extension settings in the Flarum administration panel and:

1. Enter and save the API-Football API key.
2. Test the API connection.
3. Enter the API-Football league ID.
4. Enter the season.
5. Synchronize the teams.
6. Synchronize the squads.
7. Grant the **Create football lineups** permission to the desired user groups.

Team and squad synchronization is currently started manually from the extension settings.

## Generated images and cached assets

Generated lineup images are stored in:

```text
public/assets/wss-lineup/
```

Each generated image receives a random filename. Images inserted into a post are associated with that post.

Expired, unused images are removed by the scheduled cleanup process. Cleanup can also be started manually:

```sh
php flarum wss-lineup:cleanup-images
```

Team logos and player photos are downloaded during team and squad synchronization, validated, converted to PNG and cached locally under:

```text
public/assets/wss-lineup/cache/teams/
public/assets/wss-lineup/cache/players/
```

Valid cached images are reused and refreshed after seven days. If a refresh fails, an existing valid cached image remains available.

The browser and lineup renderer use these local files instead of loading images directly from API-Football.

All files below `public/assets/wss-lineup/` are publicly accessible through the forum web server. Random generated-image filenames make URLs difficult to guess, but they are not an access-control mechanism.

Do not include confidential or sensitive information in generated lineup images.

## Updating

```sh
composer update m4v3rick4git/flarum-lineup
php flarum migrate
php flarum cache:clear
```

## Security

The API-Football key is encrypted before it is stored in the Flarum settings table. The encryption key itself is not stored in the database and must be supplied through `WSS_LINEUP_ENCRYPTION_KEY`.

Remote images are restricted to the expected API-Football image host and paths, validated before decoding and stored locally as normalized PNG files.

Generated images, team logos and player photos are stored in the forum's public asset directory. Their URLs must not be treated as private or protected resources.

Do not commit API keys or the encryption key to the repository.

## Links

- [Packagist](https://packagist.org/packages/m4v3rick4git/flarum-lineup)
- [GitHub](https://github.com/m4v3rick4git/flarum-lineup)

## License

Released under the MIT License.
