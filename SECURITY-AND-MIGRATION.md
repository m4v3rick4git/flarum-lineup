# Security, migration and limitations (v0.2.0)

## Deliberate data compatibility

Keep the original `m4v3rick4git-lineup` extension ID and its five historical migration filenames. When upgrading an existing forum, Flarum's migration log should recognize them; on a fresh install, the old intermediate `api_team_id` schema exists only during historical migration execution and is removed by the final historical migration. The upgrade path has been tested under Flarum 2.0.2.

`wss_lineup_teams`, `wss_lineup_players`, `wss_lineup_generated_images` stay. No new DB columns are needed for the surname rule. `wss-lineup.data_provider` is ignored in the single-provider release; only Bundesliga.at sync and generation is available. Old API-Football rows remain inert until a separate, consented cleanup.

## Deferred cleanup (never automatic)

1. Make an isolated DB backup and confirm restore steps.
2. Execute `scripts/legacy-cleanup-preview.sql` and confirm `provider='api-football'` counts.
3. Revoke the old API key at the provider, if possible, rather than assuming removing an active DB row revokes backups.
4. After final tests, remove the `settings` row with key `wss-lineup.api_key` by approved administrative procedure. Avoid outputting the secret.
5. Delete API-Football teams/players separately only once referential relationships and backups are approved; never delete a generated image table entry because it references an old post.
6. Historic public image URLs remain intact indefinitely.

## Safety features

- Server-side checks for permissions, team/provider membership, exactly 11 distinct active players and supported formations.
- 5/minute and 50/day per-user PNG rate limits guarded by database locks.
- Bundesliga HTML max 2MB, no redirects, strict squad paths, bounded request time and controlled Content-Type; safe parser DOM.
- Image downloads: specific Bundesliga storage hostnames / URL paths only, no redirects, max body bytes, valid image formats and pixel limits.
- `CURLOPT_RESOLVE` pins a selected previously validated public IPv4 for Bundesliga images, preventing a separate DNS lookup during the fetch. Requires cURL PHP extension; fails closed without it.
- Cache is PNG re-encoded; creation is atomic via temp file + rename and random 40-hex filenames.
- The v2 cleanup query explicitly considers only files under `assets/wss-lineup-2/`, never old images.
- No API-Football key reading or requests from the new extension. The old API-Football database rows and historical migration labels remain until separately removed.

## Validation and remaining limitations

- 129 PHPUnit tests with 1,006 assertions passed under PHP 8.4.
- Local smoke tests and a production upgrade to Flarum 2.0.2
  were completed successfully.
- Existing lineup images were preserved. New lineup generation,
  provider synchronization and insertion into posts were tested.
- A separate comprehensive fresh-install validation is not documented.
- Fonts are a system package dependency (fonts-dejavu-core).
  Verify local PHP GD and FreeType support.
- Player photos and team logos may be subject to third-party rights.
  Confirm permitted reuse before publishing images.
- Local and production datasets may differ.
