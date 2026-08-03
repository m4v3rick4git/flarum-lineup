import app from 'flarum/admin/app';
import ApiKeySettings from './components/ApiKeySettings';
import SquadSyncSettings from './components/SquadSyncSettings';
import TeamSyncSettings from './components/TeamSyncSettings';

app.initializers.add('m4v3rick4git/flarum-lineup', () => {
  app.extensionData
    .for('m4v3rick4git-lineup')
    .registerPermission(
      {
        permission: 'wss-lineup.createLineup',
        icon: 'fas fa-users',
        label: app.translator.trans('m4v3rick4git-lineup.admin.permissions.create_lineup_label'),
      },
      'start',
      50
    )
    .registerSetting(() => ApiKeySettings.component(), 100)
    .registerSetting({
      setting: 'wss-lineup.league_id',
      label: app.translator.trans('m4v3rick4git-lineup.admin.settings.league_id_label'),
      help: app.translator.trans('m4v3rick4git-lineup.admin.settings.league_id_help'),
      type: 'number',
      min: 1,
    })
    .registerSetting({
      setting: 'wss-lineup.season',
      label: app.translator.trans('m4v3rick4git-lineup.admin.settings.season_label'),
      help: app.translator.trans('m4v3rick4git-lineup.admin.settings.season_help'),
      type: 'number',
      min: 2000,
      max: 2100,
    })
    .registerSetting(() => TeamSyncSettings.component(), -100)
    .registerSetting(() => SquadSyncSettings.component(), -200);
});
