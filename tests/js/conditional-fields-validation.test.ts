import { FormValidator } from '../../src/scripts/admin/forms/conditional-fields/validation';

describe('conditional field validation', () => {
  it('normalizes whitespace and non-printing controls without parsing HTML', () => {
    const validator = new FormValidator();
    const value = '  <script>alert("server validates this")</script>\u0000  ';

    const result = validator.validateField('message', value);

    expect(result).toEqual({
      isValid: true,
      normalizedValue: '<script>alert("server validates this")</script>',
    });
  });

  it('applies wildcard and field-specific limits together', () => {
    const validator = new FormValidator();
    validator.setRules({
      '*': { maxLength: 10 },
      name: { minLength: 2 },
    });

    expect(validator.validateField('name', 'A').isValid).toBe(false);
    expect(validator.validateField('name', 'CampaignBridge').isValid).toBe(
      false
    );
    expect(validator.validateField('name', 'Email').isValid).toBe(true);
  });

  it('validates missing required fields in whole-form validation', () => {
    const validator = new FormValidator();
    validator.setRules({ subject: { required: true } });

    expect(validator.validateFormData({}).isValid).toBe(false);
  });

  it('evaluates global patterns deterministically', () => {
    const validator = new FormValidator();
    validator.addRule('code', { pattern: /^CB-\d+$/g });

    expect(validator.validateField('code', 'CB-12').isValid).toBe(true);
    expect(validator.validateField('code', 'CB-12').isValid).toBe(true);
  });
});
