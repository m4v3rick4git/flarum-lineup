import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import LineupBuilder from './LineupBuilder';
import type Mithril from 'mithril';

export interface LineupModalAttrs extends IInternalModalAttrs {
  onImageCreated: (imageUrl: string) => void | Promise<void>;
}

export default class LineupModal extends Modal<LineupModalAttrs> {
  className(): string {
    return 'LineupModal';
  }

  title(): Mithril.Children {
    return app.translator.trans('m4v3rick4git-lineup.forum.composer_modal.title');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        {LineupBuilder.component({
          onImageCreated: async (imageUrl: string) => {
            await this.attrs.onImageCreated(imageUrl);
            this.hide();
          },
        })}
      </div>
    );
  }

  onsubmit(event: SubmitEvent): void {
    event.preventDefault();
  }
}
