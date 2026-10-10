import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';

export default class BundesligaAtSettingsInfo extends Component {
  view() {
    return (
      <div className="Form-group">
        <label>{app.translator.trans('m4v3rick4git-lineup.admin.settings.bundesliga_at_title')}</label>
        <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.settings.bundesliga_at_help')}</p>
      </div>
    );
  }
}
