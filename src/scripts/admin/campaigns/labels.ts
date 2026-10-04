import { __ } from '@wordpress/i18n';
import type { CampaignState } from './types';

/** Operator-facing names for every campaign state, in lifecycle order. */
export function stateLabels(): Record<CampaignState, string> {
  return {
    draft: __('Draft', 'campaignbridge'),
    ready_for_review: __('Ready for review', 'campaignbridge'),
    approved: __('Approved', 'campaignbridge'),
    provider_draft: __('In provider', 'campaignbridge'),
    scheduled: __('Scheduled', 'campaignbridge'),
    sending: __('Sending', 'campaignbridge'),
    sent: __('Sent', 'campaignbridge'),
    failed: __('Failed', 'campaignbridge'),
    cancelled: __('Cancelled', 'campaignbridge'),
    unknown: __('Needs reconciliation', 'campaignbridge'),
    archived: __('Archived', 'campaignbridge'),
  };
}

/** Provider name for a campaign; campaigns without one are HTML exports. */
export function providerLabel(
  provider: string | null,
  labels: Record<string, string>
): string {
  if (provider === null) {
    return __('HTML export', 'campaignbridge');
  }

  return labels[provider] ?? provider;
}
