import app from 'flarum/forum/app';
import addComposerLineupButton from './addComposerLineupButton';

app.initializers.add('m4v3rick4git/flarum-lineup', () => {
  addComposerLineupButton();
});
