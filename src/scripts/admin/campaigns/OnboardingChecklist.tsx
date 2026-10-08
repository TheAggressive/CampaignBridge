import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { apiFailure } from './api';
import {
  dismissOnboarding,
  onboardingProgress,
  type OnboardingState,
} from './onboarding';

/**
 * First-run setup steps above the campaign list. Each step reflects stored
 * state, so a step reopens if its prerequisite is removed. The checklist can
 * be dismissed only once the required steps are done.
 */
export function OnboardingChecklist({
  initial,
  onCreateCampaign,
}: {
  initial: OnboardingState;
  onCreateCampaign: () => void;
}): JSX.Element | null {
  const [state, setState] = useState(initial);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!state.visible) return null;

  const dismiss = () => {
    setBusy(true);
    setError(null);
    dismissOnboarding()
      .then(setState)
      .catch(caught =>
        setError(
          apiFailure(
            caught,
            __('The checklist could not be dismissed.', 'campaignbridge')
          ).message
        )
      )
      .finally(() => setBusy(false));
  };

  return (
    <section
      className='cb-admin-card campaignbridge-campaigns__onboarding'
      aria-labelledby='campaignbridge-onboarding-heading'
    >
      <div className='campaignbridge-campaigns__onboarding-head'>
        <h2 id='campaignbridge-onboarding-heading'>
          {state.complete
            ? __('You’re set up', 'campaignbridge')
            : __('Get set up', 'campaignbridge')}
        </h2>
        <span className='campaignbridge-campaigns__onboarding-progress'>
          {onboardingProgress(state.steps)}
        </span>
      </div>
      {state.dismissed && !state.complete && (
        <p>
          {__(
            'A setup step is no longer complete, so the checklist is back.',
            'campaignbridge'
          )}
        </p>
      )}
      <ol className='campaignbridge-campaigns__onboarding-steps'>
        {state.steps.map(step => (
          <li
            key={step.id}
            className={`campaignbridge-campaigns__onboarding-step${step.done ? ' is-done' : ''}`}
          >
            <span
              className='campaignbridge-campaigns__onboarding-mark'
              aria-hidden='true'
            >
              {step.done ? '✓' : ''}
            </span>
            <span className='campaignbridge-campaigns__onboarding-label'>
              <span id={`campaignbridge-onboarding-${step.id}`}>
                {step.label}
              </span>
              <span className='screen-reader-text'>
                {step.done
                  ? __('(done)', 'campaignbridge')
                  : __('(to do)', 'campaignbridge')}
              </span>
              {step.optional && (
                <span className='campaignbridge-campaigns__onboarding-optional'>
                  {__('Optional for HTML export', 'campaignbridge')}
                </span>
              )}
            </span>
            {!step.done && step.url && (
              <a
                href={step.url}
                aria-describedby={`campaignbridge-onboarding-${step.id}`}
              >
                {__('Set up', 'campaignbridge')}
              </a>
            )}
            {!step.done && step.id === 'campaign' && (
              <Button variant='link' onClick={onCreateCampaign}>
                {__('Create a campaign', 'campaignbridge')}
              </Button>
            )}
          </li>
        ))}
      </ol>
      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
      {state.complete && (
        <Button variant='secondary' onClick={dismiss} isBusy={busy}>
          {__('Dismiss checklist', 'campaignbridge')}
        </Button>
      )}
    </section>
  );
}
