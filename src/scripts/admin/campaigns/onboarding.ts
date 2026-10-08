import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/** One setup step, derived on the server from what the site holds. */
export interface OnboardingStep {
  id: 'provider' | 'audience' | 'brand' | 'template' | 'campaign' | string;
  label: string;
  done: boolean;
  optional: boolean;
  url: string | null;
}

/** The checklist as published by GET /onboarding. */
export interface OnboardingState {
  steps: OnboardingStep[];
  complete: boolean;
  dismissed: boolean;
  visible: boolean;
}

/** Dismiss a completed checklist; the server refuses an incomplete one. */
export function dismissOnboarding(): Promise<OnboardingState> {
  return apiFetch<OnboardingState>({
    path: '/campaignbridge/v1/onboarding/dismiss',
    method: 'POST',
  });
}

/** How far through the required steps the site is, in words. */
export function onboardingProgress(steps: OnboardingStep[]): string {
  const required = steps.filter(step => !step.optional);
  const done = required.filter(step => step.done).length;

  return sprintf(
    /* translators: 1: required steps done, 2: required steps in total. */
    __('%1$d of %2$d required steps done', 'campaignbridge'),
    done,
    required.length
  );
}
