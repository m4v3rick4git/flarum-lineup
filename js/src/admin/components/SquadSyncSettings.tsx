import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';

interface Team {
  id: number;
  name: string;
}

interface TeamListResponse {
  teams: Team[];
}

interface SquadSyncResult {
  success: boolean;
  teams: number;
  teamId: number;
  teamName: string;
  received: number;
  created: number;
  updated: number;
  deactivated: number;
}

interface RequestError {
  status?: number;
}

interface AggregateResult {
  teams: number;
  received: number;
  created: number;
  updated: number;
  deactivated: number;
}

export default class SquadSyncSettings extends Component {
  private syncing = false;
  private failed = false;
  private result: AggregateResult | null = null;
  private currentTeam = '';
  private completedTeams = 0;
  private totalTeams = 0;

  view() {
    return (
      <div className="Form-group">
        <label>{app.translator.trans('m4v3rick4git-lineup.admin.squad_sync.title')}</label>

        <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.squad_sync.help')}</p>

        <button className="Button Button--primary" type="button" disabled={this.syncing} onclick={() => void this.synchronize()}>
          {app.translator.trans(
            this.syncing ? 'm4v3rick4git-lineup.admin.squad_sync.syncing_button' : 'm4v3rick4git-lineup.admin.squad_sync.sync_button'
          )}
        </button>

        {this.syncing && this.currentTeam && (
          <p className="helpText">
            {app.translator.trans('m4v3rick4git-lineup.admin.squad_sync.progress_message', {
              team: this.currentTeam,
              completed: this.completedTeams,
              total: this.totalTeams,
            })}
          </p>
        )}

        {this.result && (
          <p className="helpText">
            {app.translator.trans('m4v3rick4git-lineup.admin.squad_sync.success_message', {
              teams: this.result.teams,
              received: this.result.received,
              created: this.result.created,
              updated: this.result.updated,
              deactivated: this.result.deactivated,
            })}
          </p>
        )}

        {this.failed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.squad_sync.error_message')}</p>}
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
    this.currentTeam = '';
    this.completedTeams = 0;
    this.totalTeams = 0;

    const aggregate: AggregateResult = {
      teams: 0,
      received: 0,
      created: 0,
      updated: 0,
      deactivated: 0,
    };

    try {
      const teamResponse = await app.request<TeamListResponse>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/wss-lineup/teams`,
      });

      const teams = teamResponse.teams;

      if (!Array.isArray(teams) || teams.length === 0) {
        throw new Error('No active teams are available.');
      }

      this.totalTeams = teams.length;
      m.redraw();

      for (const [index, team] of teams.entries()) {
        this.currentTeam = team.name;
        this.completedTeams = index;
        m.redraw();

        const teamResult = await this.synchronizeTeam(team.id);

        aggregate.teams += 1;
        aggregate.received += teamResult.received;
        aggregate.created += teamResult.created;
        aggregate.updated += teamResult.updated;
        aggregate.deactivated += teamResult.deactivated;

        this.completedTeams = index + 1;
        m.redraw();

        if (index < teams.length - 1) {
          await this.delay(8000);
        }
      }

      this.result = aggregate;
    } catch {
      this.failed = true;
    } finally {
      this.syncing = false;
      this.currentTeam = '';
      m.redraw();
    }
  }

  private async synchronizeTeam(teamId: number): Promise<SquadSyncResult> {
    try {
      return await this.requestTeamSync(teamId);
    } catch (error: unknown) {
      const requestError = error as RequestError;

      if (requestError.status !== 429) {
        throw error;
      }

      await this.delay(60000);

      return this.requestTeamSync(teamId);
    }
  }

  private requestTeamSync(teamId: number): Promise<SquadSyncResult> {
    return app.request<SquadSyncResult>({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/wss-lineup/squads/sync`,
      body: {
        teamId,
      },
    });
  }

  private delay(milliseconds: number): Promise<void> {
    return new Promise((resolve) => {
      window.setTimeout(resolve, milliseconds);
    });
  }
}
