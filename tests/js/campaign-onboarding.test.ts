import { onboardingProgress } from '../../src/scripts/admin/campaigns/onboarding';

const step = (id: string, done: boolean, optional = false) => ({
  id,
  label: id,
  done,
  optional,
  url: null,
});

describe('onboarding progress', () => {
  it('counts only the required steps', () => {
    expect(
      onboardingProgress([
        step('provider', true, true),
        step('audience', false, true),
        step('brand', true),
        step('template', false),
        step('campaign', false),
      ])
    ).toBe('1 of 3 required steps done');
  });

  it('does not let optional steps complete the checklist', () => {
    expect(
      onboardingProgress([step('provider', true, true), step('brand', false)])
    ).toBe('0 of 1 required steps done');
  });
});
