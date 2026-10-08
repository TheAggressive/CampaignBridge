import {
  deliveryOutcome,
  earliestSchedule,
  scheduleTime,
  toLocalInput,
} from '../../src/scripts/admin/campaigns/delivery';

const failure = (code: string) => ({
  code,
  message: 'Provider said no.',
  diagnostics: [],
  currentVersion: null,
});

describe('campaign delivery rules in the screen', () => {
  const now = new Date('2030-01-01T10:02:00Z');

  it('offers the first quarter hour at least ten minutes ahead', () => {
    const earliest = new Date(earliestSchedule(now));
    expect(earliest.getTime()).toBeGreaterThanOrEqual(
      now.getTime() + 10 * 60_000
    );
    expect(earliest.getUTCMinutes() % 15).toBe(0);
    expect(earliest.getTime() - now.getTime()).toBeLessThan(25 * 60_000);
  });

  // Local datetime-local values for instants relative to `now`, in any time zone.
  const at = (minutes: number) =>
    toLocalInput(new Date(now.getTime() + minutes * 60_000));

  it('accepts a quarter-hour time and sends it as UTC', () => {
    expect(scheduleTime(at(58), now)).toEqual({ utc: '2030-01-01T11:00:00Z' });
  });

  it('refuses times Mailchimp would refuse, before any request', () => {
    expect(scheduleTime('', now)).toHaveProperty('error');
    expect(scheduleTime(at(65), now)).toHaveProperty('error'); // 11:07 UTC
    expect(scheduleTime(at(-2), now)).toHaveProperty('error'); // in the past
    const later = new Date('2030-01-01T10:08:00Z');
    expect(
      scheduleTime(toLocalInput(new Date('2030-01-01T10:15:00Z')), later)
    ).toHaveProperty('error'); // 10:15 is under 10 minutes after 10:08
    expect(scheduleTime(at(366 * 24 * 60 - 2), now)).toHaveProperty('error');
  });

  it('never invites a retry after an unconfirmed schedule or send', () => {
    for (const operation of [
      'schedule',
      'unschedule',
      'send',
      'draft',
    ] as const) {
      expect(
        deliveryOutcome(
          failure('campaignbridge_campaign_reconciliation_required'),
          operation
        )
      ).toMatchObject({ status: 'warning', retry: false });
    }
  });

  it('lets an unconfirmed test be sent again after checking inboxes', () => {
    expect(
      deliveryOutcome(
        failure('campaignbridge_campaign_reconciliation_required'),
        'test'
      )
    ).toMatchObject({ status: 'warning', retry: true });
  });

  it('treats a provider refusal as definite and retryable with a new request', () => {
    const outcome = deliveryOutcome(
      failure('campaignbridge_campaign_provider_failed'),
      'send'
    );
    expect(outcome).toMatchObject({ status: 'error', retry: true });
    expect(outcome.message).toContain('nothing was sent');
  });
});
