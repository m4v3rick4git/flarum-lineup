import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import extractText from 'flarum/common/utils/extractText';
import type EditorDriverInterface from 'flarum/common/utils/EditorDriverInterface';
import LineupModal from './components/LineupModal';

export default function addComposerLineupButton(): void {
  extend(TextEditor.prototype, 'toolbarItems', function (items) {
    if (!app.forum.attribute<boolean>('canCreateLineup')) {
      return;
    }

    items.add(
      'wss-lineup',
      <TextEditorButton
        icon="fas fa-users"
        onclick={() => {
          const editor = (
            this.attrs as {
              composer: {
                editor: EditorDriverInterface;
              };
            }
          ).composer.editor;

          app.modal.show(LineupModal, {
            onImageCreated: (imageUrl: string): void => {
              const imageAlt = extractText(app.translator.trans('m4v3rick4git-lineup.forum.composer.image_alt'));

              editor.insertAtCursor(`\n\n![${imageAlt}](${imageUrl})\n\n`, false);
            },
          });
        }}
      >
        {app.translator.trans('m4v3rick4git-lineup.forum.composer.button_tooltip')}
      </TextEditorButton>,
      20
    );
  });
}
