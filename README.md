# Flarum Lineup

![License](https://img.shields.io/badge/license-MIT-blue.svg)
[![Latest Stable Version](https://img.shields.io/packagist/v/m4v3rick4git/flarum-lineup.svg)](https://packagist.org/packages/m4v3rick4git/flarum-lineup)
[![Total Downloads](https://img.shields.io/packagist/dt/m4v3rick4git/flarum-lineup.svg)](https://packagist.org/packages/m4v3rick4git/flarum-lineup)

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
- German and English translations

## Requirements

- Flarum 1.2 or newer within the 1.x release series
- PHP GD extension with FreeType support
- PHP Sodium extension
- `allow_url_fopen` enabled
- DejaVu Sans fonts at:

~~~text
/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf
/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf
~~~

The PHP process must be able to write to Flarum's `public/assets` directory.

An API-Football account and API key are required for synchronizing team and player data.

## Installation

Install the extension with Composer from the Flarum root directory:

~~~sh
composer require m4v3rick4git/flarum-lineup:^0.1
php flarum migrate
php flarum cache:clear
~~~

Enable **Flarum Lineup** in the Flarum administration panel.

## Encryption key

The extension requires the following environment variable:

~~~text
WSS_LINEUP_ENCRYPTION_KEY
~~~

Its value must contain exactly 64 hexadecimal characters, representing a random 32-byte key.

Generate a suitable key with PHP:

~~~sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
~~~

Add the generated value to the environment used by both the Flarum web process and CLI processes.

Example:

~~~env
WSS_LINEUP_ENCRYPTION_KEY=replace_with_your_64_character_hexadecimal_key
~~~

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

## Generated images

Generated PNG files are stored in:

~~~text
public/assets/wss-lineup/
~~~

The directory is created automatically when the first image is generated, provided that the PHP process has write access to `public/assets`.

Player photos and team logos are downloaded over HTTPS while the lineup image is generated. Failed image downloads are replaced with a fallback representation.

## Updating

~~~sh
composer update m4v3rick4git/flarum-lineup
php flarum migrate
php flarum cache:clear
~~~

## Security

The API-Football key is encrypted before it is stored in the Flarum settings table. The encryption key itself is not stored in the database and must be supplied through `WSS_LINEUP_ENCRYPTION_KEY`.

Do not commit API keys or the encryption key to the repository.

## Links

- [Packagist](https://packagist.org/packages/m4v3rick4git/flarum-lineup)
- [GitHub](https://github.com/m4v3rick4git/flarum-lineup)

## License

Released under the MIT License.
