import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import type { ApiFailure, Campaign } from './types';

const NAMESPACE = '/campaignbridge/v1';

/** The provider's normalized view of a campaign; never a provider payload. */
export interface RemoteReference {
  provider: string;
  remote_id: string;
  observed_state: string;
  observed_at: string;
  reconciled_at: string | null;
}

export interface DeliveryResult {
  campaign: Campaign;
  remote: RemoteReference | null;
  idempotent_replay?: boolean;
}

function path(campaign: Campaign, suffix: string): string {
  return `${NAMESPACE}/campaigns/${encodeURIComponent(campaign.id)}${suffix}`;
}

export function getRemote(campaign: Campaign): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({ path: path(campaign, '/remote') });
}

/**
 * Make sure the audience's merge fields are cached, so name tokens can map
 * to the provider during handoff. A cached, fresh list is left alone.
 */
export async function ensureMergeFields(campaign: Campaign): Promise<void> {
  if (!campaign.provider || !campaign.audience_reference) return;
  const base = `${NAMESPACE}/providers/${encodeURIComponent(campaign.provider)}/discovery/merge_fields`;
  const query = `?audience=${encodeURIComponent(campaign.audience_reference)}`;
  const cached = await apiFetch<{ items: unknown[]; stale: boolean }>({
    path: `${base}${query}`,
  });
  if (!cached.items.length || cached.stale) {
    await apiFetch({ path: `${base}/refresh${query}`, method: 'POST' });
  }
}

export function createProviderDraft(
  campaign: Campaign,
  idempotencyKey: string
): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/provider-draft'),
    method: 'POST',
    data: {
      expected_version: campaign.version,
      idempotency_key: idempotencyKey,
    },
  });
}

export function sendTest(
  campaign: Campaign,
  recipients: string[],
  format: 'html' | 'text',
  idempotencyKey: string
): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/test-send'),
    method: 'POST',
    data: { recipients, format, idempotency_key: idempotencyKey },
  });
}

export function scheduleCampaign(
  campaign: Campaign,
  scheduledFor: string,
  confirmedAudience: string,
  idempotencyKey: string
): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/schedule'),
    method: 'POST',
    data: {
      expected_version: campaign.version,
      scheduled_for: scheduledFor,
      confirm_audience_reference: confirmedAudience,
      idempotency_key: idempotencyKey,
    },
  });
}

export function unscheduleCampaign(
  campaign: Campaign,
  idempotencyKey: string
): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/unschedule'),
    method: 'POST',
    data: {
      expected_version: campaign.version,
      idempotency_key: idempotencyKey,
    },
  });
}

export function sendCampaign(
  campaign: Campaign,
  confirmedAudience: string,
  idempotencyKey: string
): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/send'),
    method: 'POST',
    data: {
      expected_version: campaign.version,
      confirm_audience_reference: confirmedAudience,
      idempotency_key: idempotencyKey,
    },
  });
}

export function reconcileCampaign(campaign: Campaign): Promise<DeliveryResult> {
  return apiFetch<DeliveryResult>({
    path: path(campaign, '/reconcile'),
    method: 'POST',
  });
}

/** Mailchimp schedules on quarter hours, at least 10 minutes ahead. */
export const SCHEDULE_STEP_MINUTES = 15;
const SCHEDULE_LEAD_MINUTES = 10;

/** The earliest schedulable time after `now`, as a local datetime-local value. */
export function earliestSchedule(now: Date): string {
  const earliest = new Date(now.getTime() + SCHEDULE_LEAD_MINUTES * 60_000);
  const step = SCHEDULE_STEP_MINUTES * 60_000;
  const rounded = new Date(Math.ceil(earliest.getTime() / step) * step);

  return toLocalInput(rounded);
}

/** A Date as the value of an `<input type="datetime-local">`. */
export function toLocalInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * Validate a local datetime-local value and return the UTC time to send, or
 * the reason it cannot be scheduled.
 */
export function scheduleTime(
  value: string,
  now: Date
): { utc: string } | { error: string } {
  const date = new Date(value);
  if (!value || Number.isNaN(date.getTime())) {
    return { error: __('Choose a date and time.', 'campaignbridge') };
  }
  if (
    date.getUTCMinutes() % SCHEDULE_STEP_MINUTES !== 0 ||
    date.getUTCSeconds() !== 0
  ) {
    return {
      error: __(
        'Mailchimp schedules on the quarter hour (:00, :15, :30, :45 UTC).',
        'campaignbridge'
      ),
    };
  }
  if (date.getTime() < now.getTime() + SCHEDULE_LEAD_MINUTES * 60_000) {
    return {
      error: __(
        'Choose a time at least 10 minutes from now.',
        'campaignbridge'
      ),
    };
  }
  if (date.getTime() > now.getTime() + 365 * 24 * 60 * 60_000) {
    return { error: __('Choose a time within one year.', 'campaignbridge') };
  }

  return { utc: date.toISOString().replace(/\.\d{3}Z$/, 'Z') };
}

const RECONCILIATION_REQUIRED =
  'campaignbridge_campaign_reconciliation_required';
const PROVIDER_FAILED = 'campaignbridge_campaign_provider_failed';

/**
 * What a failed delivery request means for the operator, from its stable code.
 * `retry` says whether a new attempt (with a new key) is safe.
 */
export function deliveryOutcome(
  failure: ApiFailure,
  operation: 'draft' | 'test' | 'schedule' | 'unschedule' | 'send'
): { status: 'error' | 'warning'; message: string; retry: boolean } {
  if (failure.code === RECONCILIATION_REQUIRED) {
    if (operation === 'test') {
      return {
        status: 'warning',
        message: __(
          'Mailchimp did not confirm the test. Check the test inboxes before sending another one.',
          'campaignbridge'
        ),
        retry: true,
      };
    }

    return {
      status: 'warning',
      message:
        operation === 'send'
          ? __(
              'Mailchimp did not confirm the send, so it may have reached the audience. It will not be retried. Reconcile to find out what Mailchimp did.',
              'campaignbridge'
            )
          : __(
              'Mailchimp did not confirm the request. It will not be retried. Reconcile to find out what Mailchimp did before doing anything else.',
              'campaignbridge'
            ),
      retry: false,
    };
  }
  if (failure.code === PROVIDER_FAILED) {
    return {
      status: 'error',
      message: `${__('Mailchimp refused the request; nothing was sent.', 'campaignbridge')} ${failure.message}`,
      retry: true,
    };
  }

  return { status: 'error', message: failure.message, retry: true };
}
