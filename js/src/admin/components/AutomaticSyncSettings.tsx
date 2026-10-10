import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Component from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';

type ScheduleTarget = 'teams' | 'squads';

interface AutomaticSyncSettingsAttrs {
  page: ExtensionPage;
  target: ScheduleTarget;
}

export default class AutomaticSyncSettings extends Component {
  view() {
    const attrs = this.attrs as AutomaticSyncSettingsAttrs;
    const page = attrs.page;
    const target = attrs.target;

    const enabledSetting = page.setting(this.key(target, 'enabled'), '0');
    const frequencySetting = page.setting(this.key(target, 'frequency'), target === 'teams' ? 'monthly' : 'daily');
    const timeSetting = page.setting(this.key(target, 'time'), target === 'teams' ? '04:00' : '04:15');
    const weekdaySetting = page.setting(this.key(target, 'weekday'), '1');
    const monthDaySetting = page.setting(this.key(target, 'month_day'), '2');

    const enabled = this.isEnabled(enabledSetting());
    const frequency = String(frequencySetting() || (target === 'teams' ? 'monthly' : 'daily'));

    return (
      <div className="Form-group">
        <label>
          {app.translator.trans(
            target === 'teams' ? 'm4v3rick4git-lineup.admin.auto_sync.team_title' : 'm4v3rick4git-lineup.admin.auto_sync.squad_title'
          )}
        </label>

        <p className="helpText">
          {app.translator.trans(
            target === 'teams' ? 'm4v3rick4git-lineup.admin.auto_sync.team_help' : 'm4v3rick4git-lineup.admin.auto_sync.squad_help'
          )}
        </p>

        <Switch
          state={enabled}
          onchange={(checked: boolean) => {
            enabledSetting(checked ? '1' : '0');
          }}
        >
          {app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.enabled_label')}
        </Switch>

        {enabled && (
          <div>
            <div className="Form-group">
              <label>{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.frequency_label')}</label>
              <select
                className="FormControl"
                value={frequency}
                onchange={(event: Event) => {
                  frequencySetting((event.currentTarget as HTMLSelectElement).value);
                }}
              >
                <option value="daily">{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.frequency_daily')}</option>
                <option value="weekly">{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.frequency_weekly')}</option>
                <option value="monthly">{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.frequency_monthly')}</option>
              </select>
            </div>

            {frequency === 'weekly' && (
              <div className="Form-group">
                <label>{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.weekday_label')}</label>
                <select
                  className="FormControl"
                  value={String(weekdaySetting() || '1')}
                  onchange={(event: Event) => {
                    weekdaySetting((event.currentTarget as HTMLSelectElement).value);
                  }}
                >
                  {this.weekdayOptions()}
                </select>
              </div>
            )}

            {frequency === 'monthly' && (
              <div className="Form-group">
                <label>{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.month_day_label')}</label>
                <input
                  className="FormControl"
                  type="number"
                  min="1"
                  max="28"
                  value={String(monthDaySetting() || '2')}
                  onchange={(event: Event) => {
                    monthDaySetting((event.currentTarget as HTMLInputElement).value);
                  }}
                />
              </div>
            )}

            <div className="Form-group">
              <label>{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.time_label')}</label>
              <input
                className="FormControl"
                type="time"
                value={String(timeSetting() || (target === 'teams' ? '04:00' : '04:15'))}
                onchange={(event: Event) => {
                  timeSetting((event.currentTarget as HTMLInputElement).value);
                }}
              />
              <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.auto_sync.timezone_help')}</p>
            </div>
          </div>
        )}
      </div>
    );
  }

  private key(target: ScheduleTarget, suffix: string): string {
    return `wss-lineup.auto_sync.${target}.${suffix}`;
  }

  private isEnabled(value: unknown): boolean {
    if (value === true || value === 1) {
      return true;
    }

    if (typeof value !== 'string') {
      return false;
    }

    return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase().trim());
  }

  private weekdayOptions() {
    return [
      ['1', 'monday'],
      ['2', 'tuesday'],
      ['3', 'wednesday'],
      ['4', 'thursday'],
      ['5', 'friday'],
      ['6', 'saturday'],
      ['7', 'sunday'],
    ].map(([value, key]) => <option value={value}>{app.translator.trans(`m4v3rick4git-lineup.admin.auto_sync.weekday_${key}` as any)}</option>);
  }
}
