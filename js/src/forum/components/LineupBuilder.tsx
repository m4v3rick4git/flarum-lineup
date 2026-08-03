import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import LineupPitch, { type FormationKey } from './LineupPitch';
import type Mithril from 'mithril';

export interface Team {
  id: number;
  name: string;
  code: string | null;
  logoUrl: string | null;
}

export interface Player {
  id: number;
  name: string;
  age: number | null;
  shirtNumber: number | null;
  position: string | null;
  photoUrl: string | null;
}

interface TeamsResponse {
  teams: Team[];
}

interface PlayersResponse {
  team: Team;
  players: Player[];
}

interface CreateLineupImageResponse {
  success: boolean;
  url: string;
  filename: string;
}

export interface LineupBuilderAttrs {
  onImageCreated: (imageUrl: string) => void | Promise<void>;
}

export default class LineupBuilder extends Component<LineupBuilderAttrs> {
  private loadingTeams = true;
  private teamsFailed = false;
  private teams: Team[] = [];

  private selectedTeam: Team | null = null;
  private loadingPlayers = false;
  private playersFailed = false;
  private players: Player[] = [];

  private selectedPlayerIds: number[] = [];
  private formation: FormationKey = '4-2-3-1';
  private selectedPitchSlotIndex: number | null = null;

  private generating = false;
  private generationFailed = false;

  oninit(vnode: Mithril.Vnode<LineupBuilderAttrs>): void {
    super.oninit(vnode);

    void this.loadTeams();
  }

  view() {
    return (
      <div className="LineupBuilder">
        {this.loadingTeams && LoadingIndicator.component()}

        {!this.loadingTeams && this.teamsFailed && <div className="Alert">{app.translator.trans('m4v3rick4git-lineup.forum.lineup.load_error')}</div>}

        {!this.loadingTeams && !this.teamsFailed && this.teams.length === 0 && (
          <div className="Alert">{app.translator.trans('m4v3rick4git-lineup.forum.lineup.no_teams')}</div>
        )}

        {!this.loadingTeams && !this.teamsFailed && this.teams.length > 0 && (
          <div className="LineupTeamGrid">
            {this.teams.map((team) => (
              <button
                className={['LineupTeamCard', this.selectedTeam?.id === team.id ? 'is-selected' : ''].join(' ')}
                type="button"
                key={team.id}
                onclick={() => void this.selectTeam(team)}
              >
                {team.logoUrl && <img className="LineupTeamCard-logo" src={team.logoUrl} alt="" />}

                <strong>{team.name}</strong>

                {team.code && <span>{team.code}</span>}
              </button>
            ))}
          </div>
        )}

        {this.selectedTeam && (
          <section className="LineupSquad">
            <header className="LineupSquad-header">
              <div>
                <h3>{this.selectedTeam.name}</h3>

                <p>{app.translator.trans('m4v3rick4git-lineup.forum.lineup.squad_description')}</p>
              </div>

              <strong className="LineupSquad-counter">
                {app.translator.trans('m4v3rick4git-lineup.forum.lineup.selection_count', {
                  selected: this.selectedPlayerIds.length,
                  maximum: 11,
                })}
              </strong>
            </header>

            {this.loadingPlayers && LoadingIndicator.component()}

            {!this.loadingPlayers && this.playersFailed && (
              <div className="Alert">{app.translator.trans('m4v3rick4git-lineup.forum.lineup.players_error')}</div>
            )}

            {!this.loadingPlayers && !this.playersFailed && this.players.length === 0 && (
              <div className="Alert">{app.translator.trans('m4v3rick4git-lineup.forum.lineup.no_players')}</div>
            )}

            {!this.loadingPlayers && !this.playersFailed && this.players.length > 0 && (
              <div className="LineupPlayerList">
                {this.players.map((player) => {
                  const selected = this.isPlayerSelected(player.id);

                  const selectionFull = this.selectedPlayerIds.length >= 11;

                  return (
                    <button
                      className={['LineupPlayer', selected ? 'is-selected' : ''].join(' ')}
                      type="button"
                      key={player.id}
                      disabled={selectionFull && !selected}
                      onclick={() => this.togglePlayer(player.id)}
                    >
                      {player.photoUrl && <img className="LineupPlayer-photo" src={player.photoUrl} alt="" />}

                      <div className="LineupPlayer-info">
                        <strong>{player.name}</strong>

                        <span>
                          {player.position ?? '–'}
                          {' · '}
                          {player.shirtNumber !== null ? `#${player.shirtNumber}` : '#–'}
                        </span>
                      </div>

                      {selected && <span className="LineupPlayer-check">✓</span>}
                    </button>
                  );
                })}
              </div>
            )}

            {this.selectedPlayerIds.length === 11 && (
              <>
                {LineupPitch.component({
                  team: this.selectedTeam,
                  players: this.getSelectedPlayers(),
                  formation: this.formation,
                  selectedSlotIndex: this.selectedPitchSlotIndex,
                  onFormationChange: (formation: FormationKey) => this.changeFormation(formation),
                  onSelectSlot: (slotIndex: number) => this.selectPitchSlot(slotIndex),
                })}

                {this.generationFailed && <div className="Alert">{app.translator.trans('m4v3rick4git-lineup.forum.lineup.generation_error')}</div>}

                <div className="LineupBuilder-actions">
                  <Button
                    type="button"
                    className="Button Button--primary"
                    icon="fas fa-image"
                    loading={this.generating}
                    disabled={this.generating}
                    onclick={() => void this.generateImage()}
                  >
                    {this.generating
                      ? app.translator.trans('m4v3rick4git-lineup.forum.lineup.generating_button')
                      : app.translator.trans('m4v3rick4git-lineup.forum.lineup.generate_button')}
                  </Button>
                </div>
              </>
            )}
          </section>
        )}
      </div>
    );
  }

  private async loadTeams(): Promise<void> {
    try {
      const response = await app.request<TeamsResponse>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}` + '/wss-lineup/teams',
      });

      this.teams = response.teams;
    } catch {
      this.teamsFailed = true;
    } finally {
      this.loadingTeams = false;
      m.redraw();
    }
  }

  private async selectTeam(team: Team): Promise<void> {
    this.selectedTeam = team;
    this.loadingPlayers = true;
    this.playersFailed = false;
    this.players = [];
    this.selectedPlayerIds = [];
    this.selectedPitchSlotIndex = null;
    this.generationFailed = false;

    try {
      const response = await app.request<PlayersResponse>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}` + `/wss-lineup/players?teamId=${team.id}`,
      });

      this.selectedTeam = response.team;
      this.players = response.players;
    } catch {
      this.playersFailed = true;
    } finally {
      this.loadingPlayers = false;
      m.redraw();
    }
  }

  private isPlayerSelected(playerId: number): boolean {
    return this.selectedPlayerIds.includes(playerId);
  }

  private togglePlayer(playerId: number): void {
    this.selectedPitchSlotIndex = null;
    this.generationFailed = false;

    if (this.isPlayerSelected(playerId)) {
      this.selectedPlayerIds = this.selectedPlayerIds.filter((selectedPlayerId) => selectedPlayerId !== playerId);

      return;
    }

    if (this.selectedPlayerIds.length >= 11) {
      return;
    }

    this.selectedPlayerIds = [...this.selectedPlayerIds, playerId];
  }

  private changeFormation(formation: FormationKey): void {
    this.formation = formation;
    this.selectedPitchSlotIndex = null;
    this.generationFailed = false;
  }

  private selectPitchSlot(slotIndex: number): void {
    this.generationFailed = false;

    if (this.selectedPitchSlotIndex === null) {
      this.selectedPitchSlotIndex = slotIndex;

      return;
    }

    if (this.selectedPitchSlotIndex === slotIndex) {
      this.selectedPitchSlotIndex = null;

      return;
    }

    const playerIds = [...this.selectedPlayerIds];

    const firstPlayerId = playerIds[this.selectedPitchSlotIndex];

    playerIds[this.selectedPitchSlotIndex] = playerIds[slotIndex];

    playerIds[slotIndex] = firstPlayerId;

    this.selectedPlayerIds = playerIds;
    this.selectedPitchSlotIndex = null;
  }

  private getSelectedPlayers(): Player[] {
    return this.selectedPlayerIds
      .map((playerId) => this.players.find((player) => player.id === playerId))
      .filter((player): player is Player => player !== undefined);
  }

  private async generateImage(): Promise<void> {
    if (!this.selectedTeam || this.selectedPlayerIds.length !== 11 || this.generating) {
      return;
    }

    this.generating = true;
    this.generationFailed = false;

    try {
      const response = await app.request<CreateLineupImageResponse>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}` + '/wss-lineup/images',
        body: {
          teamId: this.selectedTeam.id,
          formation: this.formation,
          playerIds: this.selectedPlayerIds,
        },
      });

      await this.attrs.onImageCreated(response.url);
    } catch {
      this.generationFailed = true;
    } finally {
      this.generating = false;
      m.redraw();
    }
  }
}
