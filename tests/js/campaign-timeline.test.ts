import {
  reconcileGuidance,
  since,
} from '../../src/scripts/admin/campaigns/delivery';
import {
  actionLabel,
  eventDetails,
  resultLabel,
} from '../../src/scripts/admin/campaigns/timeline';

const failure = (reason: string | null, retryAfter: number | null = null) => ({
  code: 'campaignbridge_campaign_reconciliation_required',
  message: 'Server message.',
  diagnostics: [],
  currentVersion: null,
  reason,
  retryAfter,
});

describe('campaign timeline and recovery', () => {
  it('waits out an in-progress request instead of treating it as a failure', () => {
    expect(reconcileGuidance(failure('in_progress', 240))).toMatchObject({
      status: 'warning',
      retryAfter: 240,
    });
  });

  it.each([
    'missing',
    'untracked',
    'inconclusive',
    'contradiction',
    'duplicate_drafts',
  ])('explains %s as a problem to resolve in the provider', reason => {
    const guidance = reconcileGuidance(failure(reason, 99));
    expect(guidance.status).toBe('error');
    expect(guidance.retryAfter).toBeNull();
    expect(guidance.text).not.toBe('Server message.');
  });

  it('falls back to the server message for an unknown reason', () => {
    expect(reconcileGuidance(failure(null)).text).toBe('Server message.');
    expect(reconcileGuidance(failure('new_reason')).text).toBe(
      'Server message.'
    );
  });

  it('labels known actions and keeps unknown ones readable', () => {
    expect(actionLabel('campaign_send')).toBe('Sent to the provider');
    expect(actionLabel('campaign_future_thing')).toBe('future thing');
    expect(resultLabel('unknown')).toBe('Not confirmed');
  });

  it('summarizes only the named context fields operators need', () => {
    const details = eventDetails({
      id: 'audit-1',
      action: 'campaign_reconcile',
      result: 'success',
      actor: { id: 1, name: 'Avery' },
      context: {
        from_state: 'unknown',
        to_state: 'sending',
        observed_state: 'sending',
        unexplained: true,
        attempt_id: 'attempt-secret-id',
        fingerprint: 'sha256:abc',
      },
      created_at: '2030-01-01T00:00:00Z',
    });

    expect(details).toEqual([
      'Needs reconciliation → Sending',
      'Provider reported: sending',
      'Changed in the provider without a CampaignBridge request',
    ]);
    expect(details.join(' ')).not.toContain('attempt-secret-id');
    expect(details.join(' ')).not.toContain('sha256');
  });

  it('explains a recovered lock with the operation that stopped', () => {
    const event = {
      id: 'audit-1',
      action: 'campaign_lock_takeover',
      result: 'success',
      actor: { id: null, name: null },
      context: { operation: 'reconcile', interrupted_operation: 'send' },
      created_at: '2030-01-01T00:00:00Z',
    };
    expect(actionLabel(event.action)).toBe(
      'Recovered an interrupted operation'
    );
    expect(eventDetails(event)).toEqual(['Interrupted: send']);
  });

  it('describes how long ago something was checked', () => {
    const now = new Date('2030-01-01T12:00:00Z');
    expect(since('2030-01-01T11:59:40Z', now)).toBe('just now');
    expect(since('2030-01-01T11:55:00Z', now)).toBe('5 minutes ago');
    expect(since('2030-01-01T09:00:00Z', now)).toBe('3 hours ago');
    expect(since('2029-12-28T12:00:00Z', now)).toBe('4 days ago');
  });
});
