import type { View } from '@wordpress/dataviews/wp';
import type { CampaignQuery, CampaignState } from './types';

/**
 * Translate the DataViews view into the server-side campaign query. Only the
 * filters the API supports are read; everything else stays client display.
 */
export function viewToQuery(view: View): CampaignQuery {
  const filters = view.filters ?? [];
  const value = (field: string): unknown =>
    filters.find(filter => filter.field === field)?.value;

  const states = value('state');
  const provider = value('provider');
  const owner = value('owner');

  return {
    page: view.page ?? 1,
    perPage: view.perPage ?? 20,
    states: Array.isArray(states) ? (states as CampaignState[]) : [],
    provider: typeof provider === 'string' && provider !== '' ? provider : null,
    ownerUserId:
      typeof owner === 'number' && owner > 0
        ? owner
        : typeof owner === 'string' && /^\d+$/.test(owner)
          ? Number(owner)
          : null,
  };
}
