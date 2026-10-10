import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ApiKeySettings from './components/ApiKeySettings';
import AutomaticSyncSettings from './components/AutomaticSyncSettings';
import BundesligaAtSettingsInfo from './components/BundesligaAtSettingsInfo';
import SquadSyncSettings from './components/SquadSyncSettings';
import TeamSyncSettings from './components/TeamSyncSettings';

function selectedProvider(page: ExtensionPage): string {
  const value = page.setting('wss-lineup.data_provider', 'api-football')();

  return typeof value === 'string' && value.trim() !== '' ? value : 'api-football';
}

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
    .registerSetting(
      {
        setting: 'wss-lineup.data_provider',
        label: app.translator.trans('m4v3rick4git-lineup.admin.settings.data_provider_label'),
        help: app.translator.trans('m4v3rick4git-lineup.admin.settings.data_provider_help'),
        type: 'select',
        options: {
          'api-football': 'API-Football',
          'bundesliga-at': 'Bundesliga.at',
        },
        default: 'api-football',
      },
      120
    )
    .registerSetting(function (this: ExtensionPage) {
      return selectedProvider(this) === 'api-football' ? ApiKeySettings.component() : null;
    }, 110)
    .registerSetting(function (this: ExtensionPage) {
      if (selectedProvider(this) !== 'api-football') {
        return null;
      }

      return this.buildSettingComponent({
        setting: 'wss-lineup.league_id',
        label: app.translator.trans('m4v3rick4git-lineup.admin.settings.league_id_label'),
        help: app.translator.trans('m4v3rick4git-lineup.admin.settings.league_id_help'),
        type: 'number',
        min: 1,
      });
    }, 100)
    .registerSetting(function (this: ExtensionPage) {
      if (selectedProvider(this) !== 'api-football') {
        return null;
      }

      return this.buildSettingComponent({
        setting: 'wss-lineup.season',
        label: app.translator.trans('m4v3rick4git-lineup.admin.settings.season_label'),
        help: app.translator.trans('m4v3rick4git-lineup.admin.settings.season_help'),
        type: 'number',
        min: 2000,
        max: 2100,
      });
    }, 90)
    .registerSetting(function (this: ExtensionPage) {
      if (selectedProvider(this) !== 'bundesliga-at') {
        return null;
      }

      return BundesligaAtSettingsInfo.component();
    }, 80)
    .registerSetting(() => TeamSyncSettings.component(), -100)
    .registerSetting(function (this: ExtensionPage) {
      return AutomaticSyncSettings.component({ page: this, target: 'teams' });
    }, -110)
    .registerSetting(() => SquadSyncSettings.component(), -200)
    .registerSetting(function (this: ExtensionPage) {
      return AutomaticSyncSettings.component({ page: this, target: 'squads' });
    }, -210);
});
