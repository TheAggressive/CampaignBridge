import {
  campaignQueryArgs,
  newIdempotencyKey,
  withQuery,
} from '../../src/scripts/admin/campaigns/api';
import { viewToQuery } from '../../src/scripts/admin/campaigns/view';

describe('campaigns screen queries', () => {
  it('maps DataViews filters onto the supported server filters only', () => {
    expect(
      viewToQuery({
        type: 'table',
        page: 3,
        perPage: 50,
        filters: [
          { field: 'state', operator: 'isAny', value: ['draft', 'sent'] },
          { field: 'provider', operator: 'is', value: 'none' },
          { field: 'owner', operator: 'is', value: 7 },
          { field: 'audience', operator: 'is', value: 'ignored' },
        ],
      })
    ).toEqual({
      page: 3,
      perPage: 50,
      states: ['draft', 'sent'],
      provider: 'none',
      ownerUserId: 7,
    });
  });

  it('defaults to the first page without filters', () => {
    expect(viewToQuery({ type: 'table', filters: [] })).toEqual({
      page: 1,
      perPage: 20,
      states: [],
      provider: null,
      ownerUserId: null,
    });
  });

  it('omits empty filters and repeats array values as key[] pairs', () => {
    const args = campaignQueryArgs({
      page: 1,
      perPage: 20,
      states: ['draft', 'ready_for_review'],
      provider: null,
      ownerUserId: null,
    });

    expect(args).toEqual({
      page: 1,
      per_page: 20,
      state: ['draft', 'ready_for_review'],
    });
    expect(withQuery('/campaignbridge/v1/campaigns', args)).toBe(
      '/campaignbridge/v1/campaigns?page=1&per_page=20&state%5B%5D=draft&state%5B%5D=ready_for_review'
    );
  });

  it('encodes values so a filter cannot add parameters', () => {
    expect(withQuery('/x', { provider: 'a&owner_user_id=1' })).toBe(
      '/x?provider=a%26owner_user_id%3D1'
    );
  });

  it('builds bounded, prefixed idempotency keys that differ per intent', () => {
    const first = newIdempotencyKey('duplicate');
    const second = newIdempotencyKey('duplicate');

    expect(first).toMatch(/^duplicate-/);
    expect(first.length).toBeLessThanOrEqual(64);
    expect(first).not.toBe(second);
  });
});
