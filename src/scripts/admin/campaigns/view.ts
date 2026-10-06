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

const CAMPAIGN_ID = /^[a-z0-9][a-z0-9_-]{0,63}$/;

/** The campaign named by the screen URL, when it is a valid identifier. */
export function campaignFromUrl(search: string): string | null {
  const id = new URLSearchParams(search).get('campaign');

  return id !== null && CAMPAIGN_ID.test(id) ? id : null;
}

/** The review page URL for one campaign. */
export function campaignUrl(screenUrl: string, id: string): string {
  return `${screenUrl}&campaign=${encodeURIComponent(id)}`;
}
