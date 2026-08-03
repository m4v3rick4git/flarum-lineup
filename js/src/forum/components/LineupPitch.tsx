import Component from 'flarum/common/Component';
import type Mithril from 'mithril';

interface PitchTeam {
  name: string;
  logoUrl: string | null;
}

interface PitchPlayer {
  id: number;
  name: string;
  shirtNumber: number | null;
  position: string | null;
  photoUrl: string | null;
}

interface FormationSlot {
  x: number;
  y: number;
}

interface FormationDefinition {
  label: string;
  slots: FormationSlot[];
}

export type FormationKey = '4-2-3-1' | '4-3-3' | '4-4-2' | '3-5-2' | '3-4-3' | '5-3-2';

const FORMATION_KEYS: FormationKey[] = ['4-2-3-1', '4-3-3', '4-4-2', '3-5-2', '3-4-3', '5-3-2'];

const FORMATIONS: Record<FormationKey, FormationDefinition> = {
  '4-2-3-1': {
    label: '4-2-3-1',
    slots: [
      { x: 50, y: 90 },
      { x: 14, y: 73 },
      { x: 38, y: 77 },
      { x: 62, y: 77 },
      { x: 86, y: 73 },
      { x: 37, y: 56 },
      { x: 63, y: 56 },
      { x: 18, y: 35 },
      { x: 50, y: 39 },
      { x: 82, y: 35 },
      { x: 50, y: 15 },
    ],
  },

  '4-3-3': {
    label: '4-3-3',
    slots: [
      { x: 50, y: 90 },
      { x: 14, y: 73 },
      { x: 38, y: 77 },
      { x: 62, y: 77 },
      { x: 86, y: 73 },
      { x: 25, y: 52 },
      { x: 50, y: 57 },
      { x: 75, y: 52 },
      { x: 18, y: 25 },
      { x: 50, y: 19 },
      { x: 82, y: 25 },
    ],
  },

  '4-4-2': {
    label: '4-4-2',
    slots: [
      { x: 50, y: 90 },
      { x: 14, y: 73 },
      { x: 38, y: 77 },
      { x: 62, y: 77 },
      { x: 86, y: 73 },
      { x: 14, y: 49 },
      { x: 38, y: 54 },
      { x: 62, y: 54 },
      { x: 86, y: 49 },
      { x: 38, y: 23 },
      { x: 62, y: 23 },
    ],
  },

  '3-5-2': {
    label: '3-5-2',
    slots: [
      { x: 50, y: 90 },
      { x: 25, y: 75 },
      { x: 50, y: 79 },
      { x: 75, y: 75 },
      { x: 11, y: 48 },
      { x: 34, y: 55 },
      { x: 50, y: 48 },
      { x: 66, y: 55 },
      { x: 89, y: 48 },
      { x: 38, y: 22 },
      { x: 62, y: 22 },
    ],
  },

  '3-4-3': {
    label: '3-4-3',
    slots: [
      { x: 50, y: 90 },
      { x: 25, y: 75 },
      { x: 50, y: 79 },
      { x: 75, y: 75 },
      { x: 14, y: 51 },
      { x: 38, y: 56 },
      { x: 62, y: 56 },
      { x: 86, y: 51 },
      { x: 18, y: 24 },
      { x: 50, y: 18 },
      { x: 82, y: 24 },
    ],
  },

  '5-3-2': {
    label: '5-3-2',
    slots: [
      { x: 50, y: 90 },
      { x: 9, y: 70 },
      { x: 29, y: 76 },
      { x: 50, y: 79 },
      { x: 71, y: 76 },
      { x: 91, y: 70 },
      { x: 25, y: 50 },
      { x: 50, y: 56 },
      { x: 75, y: 50 },
      { x: 38, y: 22 },
      { x: 62, y: 22 },
    ],
  },
};

interface LineupPitchAttrs {
  team: PitchTeam;
  players: PitchPlayer[];
  formation: FormationKey;
  selectedSlotIndex: number | null;
  onFormationChange: (formation: FormationKey) => void;
  onSelectSlot: (slotIndex: number) => void;
}

export default class LineupPitch extends Component<LineupPitchAttrs> {
  view(vnode: Mithril.Vnode<LineupPitchAttrs>) {
    const { team, players, formation, selectedSlotIndex, onFormationChange, onSelectSlot } = vnode.attrs;

    const definition = FORMATIONS[formation];

    return (
      <section className="LineupPitch" id="wss-lineup-pitch">
        <header className="LineupPitch-header">
          <div className="LineupPitch-team">
            {team.logoUrl && <img src={team.logoUrl} alt="" />}

            <strong>{team.name}</strong>
          </div>

          <select
            className="LineupPitch-formationSelect"
            value={formation}
            onchange={(event: Event) => onFormationChange((event.target as HTMLSelectElement).value as FormationKey)}
          >
            {FORMATION_KEYS.map((formationKey) => (
              <option value={formationKey} key={formationKey}>
                {FORMATIONS[formationKey].label}
              </option>
            ))}
          </select>
        </header>

        <div className="LineupPitch-field">
          <div className="LineupPitch-halfwayLine" />
          <div className="LineupPitch-centerCircle" />

          <div className="LineupPitch-penaltyArea LineupPitch-penaltyArea--top" />
          <div className="LineupPitch-goalArea LineupPitch-goalArea--top" />

          <div className="LineupPitch-penaltyArea LineupPitch-penaltyArea--bottom" />
          <div className="LineupPitch-goalArea LineupPitch-goalArea--bottom" />

          {players.map((player, slotIndex) => {
            const slot = definition.slots[slotIndex];

            if (!slot) {
              return null;
            }

            return (
              <button
                className={['LineupPitch-player', selectedSlotIndex === slotIndex ? 'is-selected' : ''].join(' ')}
                type="button"
                key={player.id}
                style={{
                  left: `${slot.x}%`,
                  top: `${slot.y}%`,
                }}
                onclick={() => onSelectSlot(slotIndex)}
              >
                <div className="LineupPitch-playerPhoto">
                  {player.photoUrl ? <img src={player.photoUrl} alt="" /> : <span>{player.shirtNumber ?? '–'}</span>}
                </div>

                <strong>{player.name}</strong>

                <span>{player.shirtNumber !== null ? `#${player.shirtNumber}` : '#–'}</span>
              </button>
            );
          })}
        </div>
      </section>
    );
  }
}
