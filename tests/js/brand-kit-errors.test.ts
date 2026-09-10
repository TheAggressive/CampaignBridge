import { requestErrorMessage } from '../../src/scripts/admin/brand-kit/errors';

describe('requestErrorMessage', () => {
  it('reads the plain error objects returned by WordPress apiFetch', () => {
    expect(
      requestErrorMessage(
        {
          code: 'google_fonts_not_configured',
          message: 'Configure the Google Fonts API key.',
          data: { status: 400 },
        },
        'Fallback message.'
      )
    ).toBe('Configure the Google Fonts API key.');
  });

  it('uses the fallback when the rejection has no usable message', () => {
    expect(requestErrorMessage({ code: 'unknown' }, 'Fallback message.')).toBe(
      'Fallback message.'
    );
  });
});
