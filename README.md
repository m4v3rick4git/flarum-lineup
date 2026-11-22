# Flarum Lineup v0.2.0 — development preview (Flarum 2.x)

Source package for **Flarum Core 2.x**, built for `m4v3rick4git/flarum-lineup` and the extension ID `m4v3rick4git-lineup`. This is a development candidate, **not a production release**.

## Functional scope

- Bundesliga.at is the only active provider. The PHP `DataProviderInterface` and resolver preserve a future multi-provider architecture.
- Six formations, team/squad selection, server-side 1200×1600 PNG and composer insertion.
- Full names in selection; last whitespace-delimited token on the pitch and in the PNG (hyphens stay intact).
- New files in `public/assets/wss-lineup-2/`, old `public/assets/wss-lineup/` and old post links remain unchanged.
- Reuses **all five historical migrations** and the existing provider-neutral three tables. **No automatic SQL deletion** of old API-Football teams/players or tokens.
- Existing Bundesliga parser, image normalization, rate limits, sync guards and claim/cleanup listeners retained and adapted for the single-provider architecture.

## Start here

Read [INSTALL-LOCAL.md](INSTALL-LOCAL.md) and [SECURITY-AND-MIGRATION.md](SECURITY-AND-MIGRATION.md) before doing anything. All instructions apply only to the isolated WSL2/Docker environment on port 8082. In particular do not copy to PRD or to the 8080 reference environment.

The release is incomplete until the PHP dependencies are solved in the real Flarum 2.0.2 container, the frontend is built using Node Docker, the extension is enabled and a browser/API migration smoke test passes. This environment was not available for live integration testing here.

## Source integrity

The code was created from the archived v0.1.5 source on commit `bed8ff83db4072af965acc4b4b424cb1ed2cb3cb` as a new v0.2.0 codebase. The original main branch and production system are not modified by creating this ZIP.
