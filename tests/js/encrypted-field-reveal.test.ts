import apiFetch from '@wordpress/api-fetch';
import { EncryptedFieldsHandler } from '../../src/scripts/admin/forms/encrypted-fields/EncryptedFieldsHandler';

jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));
jest.mock('@wordpress/data', () => ({
  dispatch: () => ({ createNotice: jest.fn() }),
}));
jest.mock('@wordpress/notices', () => ({ store: 'core/notices' }));

const mockedApiFetch = apiFetch as unknown as jest.Mock;

/** Mirrors Form_Field_Encrypted's masked markup for a field in the "providers" form. */
function renderField(): HTMLElement {
  document.body.innerHTML = `
    <form>
      <div class="campaignbridge-encrypted-field" data-field-id="providers_mailchimp_api_key" data-credential="mailchimp_api_key">
        <input type="hidden" name="providers[mailchimp_api_key]" value="" />
        <input type="text" class="campaignbridge-encrypted-field__display" readonly value="••••us10" />
        <div class="campaignbridge-encrypted-field__controls">
          <button type="button" class="campaignbridge-encrypted-field__reveal-btn">Reveal</button>
          <button type="button" class="campaignbridge-encrypted-field__hide-btn">Hide</button>
          <button type="button" class="campaignbridge-encrypted-field__edit-btn">Edit</button>
        </div>
        <input type="text" class="campaignbridge-encrypted-field__edit" />
        <div class="campaignbridge-encrypted-field__edit-controls">
          <button type="button" class="campaignbridge-encrypted-field__save-btn">Update</button>
          <button type="button" class="campaignbridge-encrypted-field__cancel-btn">Cancel</button>
        </div>
      </div>
    </form>`;

  return document.querySelector(
    '.campaignbridge-encrypted-field'
  ) as HTMLElement;
}

async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve();
  }
}

describe('encrypted field reveal', () => {
  beforeAll(() => {
    (
      globalThis as unknown as { campaignbridgeAdmin: unknown }
    ).campaignbridgeAdmin = {
      restUrl: '/wp-json/campaignbridge/v1/',
      nonce: 'test-nonce',
      security: { revealTimeout: 8000, maxRetries: 0, requestTimeout: 30000 },
      i18n: { loading: 'Loading...', saving: 'Saving...', save: 'Save' },
    };
    // One handler per page; it delegates clicks from the document.
    new EncryptedFieldsHandler();
  });

  beforeEach(() => {
    mockedApiFetch.mockReset();
  });

  it('asks the server for the allowlisted credential name, not the form-scoped field ID', async () => {
    const field = renderField();
    mockedApiFetch.mockResolvedValue({
      success: true,
      data: { decrypted: 'revealed-value-us10' },
    });

    (
      field.querySelector(
        '.campaignbridge-encrypted-field__reveal-btn'
      ) as HTMLButtonElement
    ).click();
    await settle();

    expect(mockedApiFetch).toHaveBeenCalledTimes(1);
    expect(mockedApiFetch.mock.calls[0][0]).toMatchObject({
      path: '/campaignbridge/v1/decrypt-field',
      method: 'POST',
      data: { field_id: 'mailchimp_api_key', _wpnonce: 'test-nonce' },
    });
    expect(
      (
        field.querySelector(
          '.campaignbridge-encrypted-field__display'
        ) as HTMLInputElement
      ).value
    ).toBe('revealed-value-us10');
  });

  it('shows a refused reveal beside the field instead of failing silently', async () => {
    const field = renderField();
    mockedApiFetch.mockRejectedValue({
      code: 'rate_limit_exceeded',
      message: 'Rate limit exceeded. Try again in 30 seconds.',
    });

    (
      field.querySelector(
        '.campaignbridge-encrypted-field__reveal-btn'
      ) as HTMLButtonElement
    ).click();
    await settle();

    const error = field.querySelector('.campaignbridge-encrypted-field__error');
    expect(error?.getAttribute('role')).toBe('alert');
    expect(error?.textContent).toContain('Rate limit exceeded');
    expect(
      (
        field.querySelector(
          '.campaignbridge-encrypted-field__display'
        ) as HTMLInputElement
      ).value
    ).toBe('••••us10');
    expect(console).toHaveErrored();
  });
});
