# Lineup v0.2.0 – lokale Flarum-2-Testumgebung

Stand: 22.11.2026

## Geltungsbereich

Diese Anleitung dokumentiert die bereits eingerichtete und
funktional getestete Entwicklungsumgebung.

- WSL-Projekt: `~/projects/flarum2-upgrade`
- Git-Repository: `packages/wss/flarum-lineup`
- Git-Branch: `flarum2`
- Flarum: 2.0.2
- PHP: 8.4.28
- URL: http://localhost:8082
- Composer-Paket: `m4v3rick4git/flarum-lineup`
- Extension-ID: `m4v3rick4git-lineup`

NIEMALS auf Produktion oder die Flarum-1-Referenz
unter `~/projects/flarum-extension-dev` anwenden.

Das ursprüngliche Importskript `apply-local-only.sh` wurde
entfernt. Es darf nicht erneut ausgeführt werden.

## Aktueller Installationsstand

Die Extension ist bereits über ein lokales Composer-Path-Repository
installiert und aktiviert.

Composer verwendet einen Symlink auf:

`/var/www/packages/wss/flarum-lineup`

Nicht erneut installieren, solange keine Änderung an den
Composer-Abhängigkeiten erforderlich ist.

Vor Composer-Änderungen müssen `app/composer.json`,
`app/composer.lock` und die lokale Testdatenbank gesichert werden.

Das Git-Repository benötigt im PHP-Container für Composer
gegebenenfalls eine temporäre Git-safe.directory-Ausnahme.

## JavaScript-Entwicklung

Node.js 22 wird ausschließlich in einem temporären
Docker-Container verwendet.

Der Container muss das GESAMTE Upgradeprojekt einbinden,
weil die TypeScript-Typdefinitionen aus dem Flarum-2-Core
verwendet werden.

```bash
cd ~/projects/flarum2-upgrade

docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/tmp/npm \
  -v "$PWD:/work" \
  -w /work/packages/wss/flarum-lineup/js \
  node:22-bookworm \
  sh -lc 'npm ci && npm run typecheck && npm run build'
```

Nach geänderten Frontend-Bundles den Flarum-Cache leeren:

```bash
cd ~/projects/flarum2-upgrade
docker compose exec -T php sh -lc \
  'cd /var/www/flarum && php flarum cache:clear'
```

## PHP-Prüfung

PHP steht im Flarum-2-Container zur Verfügung.

```bash
cd ~/projects/flarum2-upgrade

docker compose exec -T php \
  php /var/www/packages/wss/flarum-lineup/tests/run-smoke.php
```

Die fünf Konsolenklassen verwenden unter Flarum 2
die Methodensignatur `fire(): int`.

## Datenbank und Migrationen

Die bestehenden Tabellen bleiben erhalten:

- `wss_lineup_teams`
- `wss_lineup_players`
- `wss_lineup_generated_images`

Alle fünf historischen Lineup-Migrationen waren bei
der Aktivierung bereits registriert.

Keine pauschalen Migrationsbefehle ausführen.

Insbesondere NICHT verwenden:

- `php flarum migrate:reset`
- `php flarum extension:purge`

Bei einer neuen oder leeren Testdatenbank sind die
Migrationen gesondert zu prüfen.

API-Football-Altbestände bleiben vorerst erhalten.
Ihre spätere Entfernung benötigt eine eigene Sicherung
und einen getrennten, geprüften Migrationsplan.

## Bilder und Schriftarten

Die DejaVu-Schriftarten sind im PHP-Container vorhanden.

Neuer Bildcache:

`public/assets/wss-lineup-2/cache/`

Historischer Cache und bestehende Aufstellungsbilder:

`public/assets/wss-lineup/`

Historische Bilder dürfen nicht verschoben oder gelöscht werden.

Zum dokumentierten Testzeitpunkt:

- 349 Bundesliga.at-Spieler, alle aktiv
- 12 Bundesliga.at-Vereine
- 349 gültige Spielerporträts
- 12 gültige Vereinslogos
- 14 alte, in Beiträgen referenzierte PNGs weiterhin erreichbar

## Funktional geprüfte Bereiche

- Admin-Oberfläche und Berechtigungsregistrierung
- Aufstellungseditor und PNG-Generierung
- Manuelle Teamsynchronisierung
- Manuelle Kadersynchronisierung
- Bildcache und Nachladen neuer Spielerbilder
- Gastzugriffe werden verweigert
- Mitglieder dürfen Aufstellungen erstellen
- Mitglieder dürfen keine Synchronisierung starten
- Automatikbefehl bei deaktivierter Synchronisierung
- Bildbereinigung ohne vorhandene Löschkandidaten
- Historische Aufstellungsbilder weiterhin abrufbar

## Noch offen

- Bestehende PHPUnit-Tests auf Flarum 2 portieren
- Isolierte PHPUnit-Testumgebung einrichten
- Automatische Synchronisierung bei aktiver Konfiguration testen
- Bildbereinigung mit eigens angelegten Löschkandidaten testen
- Zusätzliche Eingabe- und Sicherheitstests
- Abschließende Regression und Release-Vorbereitung

Beide automatischen Synchronisierungen bleiben bis zur
gezielten Funktionsabnahme deaktiviert.

## Wiederherstellung

Vor weiteren Änderungen den Git-Arbeitsstand und die
Testdatenbank sichern.

Vorhandene Sicherungen liegen im lokalen Verzeichnis:

`~/projects/flarum2-upgrade/backups/`

Die zuletzt angelegte Quellcodesicherung lautet:

`lineup-v020-working-20261122-202839.tar.gz`

Ein Datenbank-Restore darf nur nach gesonderter Prüfung
und ausschließlich in der Flarum-2-Testumgebung erfolgen.
