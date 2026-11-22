-- READ ONLY. Run manually on an isolated copy to preview legacy data.
SELECT provider, COUNT(*) AS teams FROM wss_lineup_teams GROUP BY provider;
SELECT provider, COUNT(*) AS players FROM wss_lineup_players GROUP BY provider;
SELECT `key`, LENGTH(`value`) AS stored_length
FROM settings
WHERE `key` IN ('wss-lineup.api_key', 'wss-lineup.api_key_encrypted', 'wss-lineup.league_id', 'wss-lineup.season');
-- Never include the secret value in shared terminal output.
