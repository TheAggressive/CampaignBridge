import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { stateLabels } from './labels';
import type { CampaignState } from './types';

/** One audit event, as published by GET /campaigns/{id}/history. */
export interface HistoryEvent {
  id: string;
  action: string;
  result: 'success' | 'failure' | 'denied' | 'unknown' | string;
  actor: { id: number | null; name: string | null };
  context: Record<string, unknown>;
  created_at: string;
}

/** One delivery attempt, as published by GET /campaigns/{id}/attempts. */
export interface DeliveryAttempt {
  id: string;
  operation: string;
  status: 'pending' | 'succeeded' | 'failed' | 'unknown' | string;
  retryability: string;
  has_idempotency_key: boolean;
  remote_correlation: string | null;
  created_at: string;
  updated_at: string;
}

interface Page<T> {
  items: T[];
  pagination: { page: number; total: number; total_pages: number };
}

const NAMESPACE = '/campaignbridge/v1';

export function getHistory(
  id: string,
  page: number
): Promise<Page<HistoryEvent>> {
  return apiFetch({
    path: `${NAMESPACE}/campaigns/${encodeURIComponent(id)}/history?per_page=50&page=${page}`,
  });
}

export function getAttempts(id: string): Promise<Page<DeliveryAttempt>> {
  return apiFetch({
    path: `${NAMESPACE}/campaigns/${encodeURIComponent(id)}/attempts?per_page=100`,
  });
}

/** What happened, in the operator's words; unknown actions keep their name. */
export function actionLabel(action: string): string {
  const labels: Record<string, string> = {
    campaign_create: __('Created', 'campaignbridge'),
    campaign_duplicate: __('Duplicated', 'campaignbridge'),
    campaign_edit: __('Template changed', 'campaignbridge'),
    campaign_audience_select: __(
      'Delivery or audience changed',
      'campaignbridge'
    ),
    campaign_snapshot: __('Prepared for review', 'campaignbridge'),
    campaign_validate: __('Template checked', 'campaignbridge'),
    campaign_submit_review: __('Submitted for review', 'campaignbridge'),
    campaign_approve: __('Approved', 'campaignbridge'),
    campaign_revoke_approval: __('Approval revoked', 'campaignbridge'),
    campaign_provider_draft: __('Provider draft created', 'campaignbridge'),
    campaign_test_send: __('Test sent', 'campaignbridge'),
    campaign_schedule: __('Scheduled', 'campaignbridge'),
    campaign_unschedule: __('Unscheduled', 'campaignbridge'),
    campaign_send: __('Sent to the provider', 'campaignbridge'),
    campaign_reconcile: __('Reconciled with the provider', 'campaignbridge'),
    campaign_archive: __('Archived', 'campaignbridge'),
    campaign_read: __('Viewed', 'campaignbridge'),
    campaign_lock_takeover: __(
      'Recovered an interrupted operation',
      'campaignbridge'
    ),
  };

  return labels[action] ?? action.replace(/^campaign_/, '').replace(/_/g, ' ');
}

/** How the step ended. `unknown` means the provider did not confirm it. */
export function resultLabel(result: string): string {
  const labels: Record<string, string> = {
    success: __('Done', 'campaignbridge'),
    failure: __('Refused', 'campaignbridge'),
    denied: __('Not allowed', 'campaignbridge'),
    unknown: __('Not confirmed', 'campaignbridge'),
  };

  return labels[result] ?? result;
}

export function attemptStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    pending: __('In progress', 'campaignbridge'),
    succeeded: __('Succeeded', 'campaignbridge'),
    failed: __('Failed', 'campaignbridge'),
    unknown: __('Not confirmed', 'campaignbridge'),
  };

  return labels[status] ?? status;
}

export function operationLabel(operation: string): string {
  const labels: Record<string, string> = {
    create_draft: __('Create provider draft', 'campaignbridge'),
    update_draft: __('Update provider draft', 'campaignbridge'),
    test_send: __('Test', 'campaignbridge'),
    schedule: __('Schedule', 'campaignbridge'),
    unschedule: __('Unschedule', 'campaignbridge'),
    send: __('Send', 'campaignbridge'),
    reconcile: __('Reconcile', 'campaignbridge'),
  };

  return labels[operation] ?? operation.replace(/_/g, ' ');
}

/**
 * A short, safe summary of an event's recorded context. It reads only the
 * named fields operators need; everything else stays out of the page.
 */
export function eventDetails(event: HistoryEvent): string[] {
  const context = event.context;
  const states = stateLabels();
  const state = (value: unknown): string | null =>
    typeof value === 'string' && value in states
      ? states[value as CampaignState]
      : null;
  const details: string[] = [];

  const from = state(context.from_state);
  const to = state(context.to_state);
  if (from && to && from !== to) {
    details.push(
      sprintf(
        /* translators: 1: previous state, 2: new state. */
        __('%1$s → %2$s', 'campaignbridge'),
        from,
        to
      )
    );
  }
  if (
    typeof context.observed_state === 'string' &&
    event.action === 'campaign_reconcile'
  ) {
    details.push(
      sprintf(
        /* translators: %s: state the provider reported. */
        __('Provider reported: %s', 'campaignbridge'),
        context.observed_state
      )
    );
  }
  if (typeof context.scheduled_for === 'string') {
    details.push(
      sprintf(
        /* translators: %s: scheduled time. */
        __('For %s', 'campaignbridge'),
        new Date(context.scheduled_for).toLocaleString()
      )
    );
  }
  if (typeof context.destination_count === 'number') {
    details.push(
      sprintf(
        /* translators: %d: number of test recipients. */
        __('%d test recipient(s)', 'campaignbridge'),
        context.destination_count
      )
    );
  }
  if (context.unexplained === true) {
    details.push(
      __(
        'Changed in the provider without a CampaignBridge request',
        'campaignbridge'
      )
    );
  }
  if (
    event.action === 'campaign_lock_takeover' &&
    typeof context.interrupted_operation === 'string'
  ) {
    details.push(
      sprintf(
        /* translators: %s: the operation that stopped, such as "send". */
        __('Interrupted: %s', 'campaignbridge'),
        context.interrupted_operation.replace(/_/g, ' ')
      )
    );
  }
  if (typeof context.error_code === 'string') {
    details.push(
      sprintf(
        /* translators: %s: stable error code. */
        __('Reason: %s', 'campaignbridge'),
        context.error_code.replace(/_/g, ' ')
      )
    );
  }

  return details;
}
