import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

type ApiKeySource = 'database' | 'none';

interface ApiKeyStatus {
  configured: boolean;
  source: ApiKeySource;
  stored: boolean;
}

interface ApiTestResult {
  success: boolean;
  active: boolean;
  plan: string | null;
  requestsCurrent: number | null;
  requestsLimitDay: number | null;
}

export default class ApiKeySettings extends Component {
  private apiKey = '';
  private loading = true;
  private saving = false;
  private deleting = false;
  private testing = false;
  private failed = false;
  private saveFailed = false;
  private deleteFailed = false;
  private testFailed = false;
  private saved = false;
  private deleted = false;
  private status: ApiKeyStatus | null = null;
  private testResult: ApiTestResult | null = null;

  oninit(vnode: any): void {
    super.oninit(vnode);

    void this.loadStatus();
  }

  view() {
    return (
      <div className="Form-group">
        <label>{app.translator.trans('m4v3rick4git-lineup.admin.api_key.title')}</label>

        <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.help')}</p>

        {this.loading && <LoadingIndicator />}

        {!this.loading && this.failed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.status_error')}</p>}

        {!this.loading && !this.failed && this.status && (
          <div className="wss-lineup-api-key-status">
            <p>
              <strong>
                {app.translator.trans(
                  this.status.configured ? 'm4v3rick4git-lineup.admin.api_key.status_configured' : 'm4v3rick4git-lineup.admin.api_key.status_missing'
                )}
              </strong>
            </p>

            <p className="helpText">{app.translator.trans(`m4v3rick4git-lineup.admin.api_key.source_${this.status.source}`)}</p>

            <input
              className="FormControl"
              type="password"
              value={this.apiKey}
              autocomplete="new-password"
              placeholder={app.translator.trans('m4v3rick4git-lineup.admin.api_key.input_placeholder')}
              oninput={(event: Event) => {
                this.apiKey = (event.target as HTMLInputElement).value;
                this.saved = false;
                this.deleted = false;
                this.saveFailed = false;
                this.deleteFailed = false;
                this.testFailed = false;
                this.testResult = null;
              }}
            />

            <div style="margin-top: 12px">
              <button
                className="Button Button--primary"
                type="button"
                disabled={this.saving || this.deleting || this.testing || this.apiKey.trim() === ''}
                onclick={() => void this.save()}
              >
                {app.translator.trans(
                  this.saving ? 'm4v3rick4git-lineup.admin.api_key.saving_button' : 'm4v3rick4git-lineup.admin.api_key.save_button'
                )}
              </button>

              <button
                className="Button"
                type="button"
                style="margin-left: 8px"
                disabled={!this.status.configured || this.saving || this.deleting || this.testing}
                onclick={() => void this.testConnection()}
              >
                {app.translator.trans(
                  this.testing ? 'm4v3rick4git-lineup.admin.api_key.testing_button' : 'm4v3rick4git-lineup.admin.api_key.test_button'
                )}
              </button>

              {this.status.stored && (
                <button
                  className="Button Button--danger"
                  type="button"
                  style="margin-left: 8px"
                  disabled={this.saving || this.deleting || this.testing}
                  onclick={() => void this.deleteStoredKey()}
                >
                  {app.translator.trans(
                    this.deleting ? 'm4v3rick4git-lineup.admin.api_key.deleting_button' : 'm4v3rick4git-lineup.admin.api_key.delete_button'
                  )}
                </button>
              )}
            </div>

            {this.saved && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.saved_message')}</p>}

            {this.deleted && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.deleted_message')}</p>}

            {this.testResult && (
              <p className="helpText">
                {app.translator.trans('m4v3rick4git-lineup.admin.api_key.test_success', {
                  plan: this.testResult.plan ?? '-',
                  current: this.testResult.requestsCurrent ?? '-',
                  limit: this.testResult.requestsLimitDay ?? '-',
                })}
              </p>
            )}

            {this.saveFailed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.save_error')}</p>}

            {this.deleteFailed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.delete_error')}</p>}

            {this.testFailed && <p className="helpText">{app.translator.trans('m4v3rick4git-lineup.admin.api_key.test_error')}</p>}
          </div>
        )}
      </div>
    );
  }

  private endpoint(): string {
    return `${app.forum.attribute('apiUrl')}/wss-lineup/api-key`;
  }

  private async loadStatus(): Promise<void> {
    this.loading = true;
    this.failed = false;

    try {
      this.status = await app.request<ApiKeyStatus>({
        method: 'GET',
        url: this.endpoint(),
      });
    } catch {
      this.failed = true;
      this.status = null;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  private async save(): Promise<void> {
    const apiKey = this.apiKey.trim();

    if (apiKey === '' || this.saving || this.deleting || this.testing) {
      return;
    }

    this.saving = true;
    this.saved = false;
    this.deleted = false;
    this.saveFailed = false;
    this.deleteFailed = false;
    this.testFailed = false;
    this.testResult = null;

    try {
      this.status = await app.request<ApiKeyStatus>({
        method: 'POST',
        url: this.endpoint(),
        body: {
          apiKey,
        },
      });

      this.apiKey = '';
      this.saved = true;
    } catch {
      this.saveFailed = true;
    } finally {
      this.saving = false;
      m.redraw();
    }
  }

  private async testConnection(): Promise<void> {
    if (!this.status?.configured || this.saving || this.deleting || this.testing) {
      return;
    }

    this.testing = true;
    this.testFailed = false;
    this.testResult = null;

    try {
      this.testResult = await app.request<ApiTestResult>({
        method: 'POST',
        url: `${this.endpoint()}/test`,
      });
    } catch {
      this.testFailed = true;
    } finally {
      this.testing = false;
      m.redraw();
    }
  }

  private async deleteStoredKey(): Promise<void> {
    if (!this.status?.stored || this.saving || this.deleting || this.testing) {
      return;
    }

    const confirmed = window.confirm(app.translator.trans('m4v3rick4git-lineup.admin.api_key.delete_confirm') as string);

    if (!confirmed) {
      return;
    }

    this.deleting = true;
    this.saved = false;
    this.deleted = false;
    this.saveFailed = false;
    this.deleteFailed = false;
    this.testFailed = false;
    this.testResult = null;

    try {
      this.status = await app.request<ApiKeyStatus>({
        method: 'DELETE',
        url: this.endpoint(),
      });

      this.deleted = true;
    } catch {
      this.deleteFailed = true;
    } finally {
      this.deleting = false;
      m.redraw();
    }
  }
}
