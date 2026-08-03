import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';

interface TeamSyncResult {
  success: boolean;
  leagueId: number;
  season: number;
  received: number;
  created: number;
  updated: number;
  deactivated: number;
}

export default class TeamSyncSettings extends Component {
  private syncing = false;
  private failed = false;
  private result: TeamSyncResult | null = null;

  view() {
    return (
      <div className="Form-group">
        <label>{app.translator.trans('m4v3rick4git-lineup.admin.team_sync.title')}</label>

        <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.team_sync.help')}</p>

        <button className="Button Button--primary" type="button" disabled={this.syncing} onclick={() => void this.synchronize()}>
          {app.translator.trans(
            this.syncing ? 'm4v3rick4git-lineup.admin.team_sync.syncing_button' : 'm4v3rick4git-lineup.admin.team_sync.sync_button'
          )}
        </button>

        {this.result && (
          <p className="helpText">
            {app.translator.trans('m4v3rick4git-lineup.admin.team_sync.success_message', {
              received: this.result.received,
              created: this.result.created,
              updated: this.result.updated,
              deactivated: this.result.deactivated,
            })}
          </p>
        )}

        {this.failed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.team_sync.error_message')}</p>}
      </div>
    );
  }

  private async synchronize(): Promise<void> {
    if (this.syncing) {
      return;
    }

    this.syncing = true;
    this.failed = false;
    this.result = null;

    try {
      this.result = await app.request<TeamSyncResult>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/wss-lineup/teams/sync`,
        body: {},
      });
    } catch {
      this.failed = true;
    } finally {
      this.syncing = false;
      m.redraw();
    }
  }
}
