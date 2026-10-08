import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
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

/**
 * The next step after a reconciliation that could not settle the campaign,
 * from its stable reason. Unknown reasons fall back to the server message.
 */
export function reconcileGuidance(failure: ApiFailure): {
  status: 'warning' | 'error';
  text: string;
  retryAfter: number | null;
} {
  const guidance: Record<string, string> = {
    in_progress: __(
      'Mailchimp may still be applying the last request, so its answer is not final yet. Reconcile again when the wait is over; nothing else needs doing.',
      'campaignbridge'
    ),
    missing: __(
      'Mailchimp no longer has this campaign; it may have been deleted there. Check Mailchimp’s campaign list and reports to confirm what was sent. CampaignBridge changes nothing locally and keeps delivery blocked.',
      'campaignbridge'
    ),
    untracked: __(
      'Mailchimp reports a status CampaignBridge does not track, such as a cancellation in progress or an archived campaign. Resolve it in Mailchimp, then reconcile again.',
      'campaignbridge'
    ),
    inconclusive: __(
      'Mailchimp’s current status does not show whether the last request took effect. Check the campaign in Mailchimp before doing anything else here.',
      'campaignbridge'
    ),
    contradiction: __(
      'Mailchimp disagrees with what CampaignBridge recorded for this campaign. CampaignBridge will not follow it automatically. Investigate in Mailchimp; the history below shows what CampaignBridge did.',
      'campaignbridge'
    ),
    duplicate_drafts: __(
      'Mailchimp holds more than one draft for this campaign. Delete the extra drafts in Mailchimp, keep one, then reconcile again.',
      'campaignbridge'
    ),
  };
  const text = failure.reason ? guidance[failure.reason] : undefined;

  return {
    status: failure.reason === 'in_progress' ? 'warning' : 'error',
    text: text ?? failure.message,
    retryAfter: failure.reason === 'in_progress' ? failure.retryAfter : null,
  };
}

/** How long ago an ISO time was, in words. */
export function since(iso: string, now: Date): string {
  const minutes = Math.max(
    0,
    Math.round((now.getTime() - new Date(iso).getTime()) / 60_000)
  );
  if (minutes < 1) return __('just now', 'campaignbridge');
  if (minutes < 60) {
    return sprintf(
      /* translators: %d: minutes. */
      _n('%d minute ago', '%d minutes ago', minutes, 'campaignbridge'),
      minutes
    );
  }
  const hours = Math.round(minutes / 60);
  if (hours < 48) {
    return sprintf(
      /* translators: %d: hours. */
      _n('%d hour ago', '%d hours ago', hours, 'campaignbridge'),
      hours
    );
  }
  const days = Math.round(hours / 24);
  return sprintf(
    /* translators: %d: days. */
    _n('%d day ago', '%d days ago', days, 'campaignbridge'),
    days
  );
}
